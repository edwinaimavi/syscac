<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guarantor;
use App\Models\Member;
use App\Models\MemberGuarantor;
use App\Services\LoanEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class GuarantorController extends Controller
{
    private const DEFAULT_AVATAR = 'https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg';

    public function __construct(private readonly LoanEligibilityService $eligibility)
    {
        $this->middleware('can:avales.index')->only(['index', 'list', 'summary', 'nextCode', 'verifyDni']);
        $this->middleware('can:avales.create')->only(['store']);
        $this->middleware('can:avales.edit')->only(['edit', 'update']);
        $this->middleware('can:avales.show')->only(['show']);
        $this->middleware('can:avales.anular')->only(['annul']);
    }

    public function index()
    {
        return view('admin.guarantors.index', [
            'nextCode' => Guarantor::nextCode(),
            'members' => Member::eligibleGuarantors()->orderBy('full_name')->get(['id', 'code', 'dni', 'first_name', 'last_name', 'full_name']),
        ]);
    }

    public function list()
    {
        $guarantors = MemberGuarantor::with(['member:id,code,dni,full_name,status', 'guarantorMember:id,code,dni,full_name,status', 'guarantor.member:id,code,dni,full_name,status'])
            ->orderByDesc('id');

        return DataTables::of($guarantors)
            ->addIndexColumn()
            ->addColumn('member_code', fn (MemberGuarantor $link) => e($link->member?->code ?: '-'))
            ->addColumn('member_name', fn (MemberGuarantor $link) => e($link->member?->full_name ?: '-'))
            ->addColumn('member_dni', fn (MemberGuarantor $link) => e($link->member?->dni ?: '-'))
            ->addColumn('guarantor_name', fn (MemberGuarantor $link) => e(($link->guarantorMember ?: $link->guarantor?->member)?->full_name ?: '-'))
            ->addColumn('guarantor_dni', fn (MemberGuarantor $link) => e(($link->guarantorMember ?: $link->guarantor?->member)?->dni ?: '-'))
            ->addColumn('guarantor_contributions', function (MemberGuarantor $link) {
                $member = $link->guarantorMember ?: $link->guarantor?->member;
                return 'S/ ' . number_format((float) ($member?->shares()->where('status', 'registrado')->sum('share_capital_amount') ?? 0), 2);
            })
            ->editColumn('status', fn (MemberGuarantor $link) => $this->statusBadge($link->status))
            ->addColumn('acciones', fn () => '<span class="text-muted">Relacion interna</span>')
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function summary()
    {
        return response()->json([
            'total' => MemberGuarantor::count(),
            'externals' => Guarantor::where('type', 'externo')->count(),
            'members' => MemberGuarantor::whereNotNull('guarantor_member_id')->count(),
            'active' => MemberGuarantor::where('status', 'activo')->count(),
        ]);
    }

    public function nextCode()
    {
        return response()->json(['code' => Guarantor::nextCode()]);
    }

    public function store(Request $request)
    {
        return response()->json(['message' => 'Solo se permite seleccionar como garante a un socio registrado.'], 422);

        /* Registro externo conservado debajo solo como referencia historica; las nuevas altas estan bloqueadas. */
        $data = $this->validatedData($request);

        $duplicate = $this->duplicateResponse($data['dni'], $data['type']);
        if ($duplicate) {
            return $duplicate;
        }

        $guarantor = DB::transaction(function () use ($request, $data) {
            if ($data['type'] === 'socio' && $existingExternal = $this->externalByDni($data['dni'])) {
                $this->normalizeData($data);
                $data['updated_by'] = auth()->id();
                unset($data['code']);
                $existingExternal->update($data);

                return $existingExternal;
            }

            if ($request->hasFile('photo_path')) {
                $data['photo_path'] = $request->file('photo_path')->store('guarantors', 'public');
            }

            $this->normalizeData($data);
            $data['code'] = Guarantor::nextCode();
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();

            return Guarantor::create($data);
        });

        return response()->json([
            'message' => $guarantor->wasChanged('type')
                ? 'Aval vinculado correctamente. No se creo un registro duplicado.'
                : 'Aval registrado correctamente.',
            'guarantor' => $this->selectPayload($guarantor->load('member')),
        ]);
    }

    public function show(Guarantor $avale)
    {
        return response()->json($this->payload($avale));
    }

    public function edit(Guarantor $avale)
    {
        return response()->json($this->payload($avale));
    }

    public function update(Request $request, Guarantor $avale)
    {
        $data = $this->validatedData($request, $avale);

        $duplicate = $this->duplicateResponse($data['dni'], $data['type'], $avale->id);
        if ($duplicate) {
            return $duplicate;
        }

        DB::transaction(function () use ($request, $avale, $data) {
            if ($request->hasFile('photo_path')) {
                if ($avale->photo_path) {
                    Storage::disk('public')->delete($avale->photo_path);
                }

                $data['photo_path'] = $request->file('photo_path')->store('guarantors', 'public');
            }

            $this->normalizeData($data);
            $data['updated_by'] = auth()->id();
            unset($data['code']);

            $avale->update($data);
        });

        return response()->json(['message' => 'Aval actualizado correctamente.']);
    }

    public function annul(Guarantor $avale)
    {
        $avale->update([
            'status' => 'anulado',
            'updated_by' => auth()->id(),
        ]);

        MemberGuarantor::where('guarantor_id', $avale->id)->update(['status' => 'inactivo']);

        return response()->json(['message' => 'Aval anulado correctamente.']);
    }

    public function select2(Request $request)
    {
        abort_unless(auth()->user()?->can('admin.socios.index') || auth()->user()?->can('avales.index'), 403);

        $term = trim((string) $request->input('q'));
        $excludeMemberId = $request->integer('exclude_member_id') ?: null;
        $items = collect();

        Member::eligibleGuarantors()
            ->when($excludeMemberId, fn ($query) => $query->whereKeyNot($excludeMemberId))
            ->when($term, fn ($query) => $query->where(function ($subQuery) use ($term) {
                $subQuery->where('code', 'like', "%{$term}%")
                    ->orWhere('dni', 'like', "%{$term}%")
                    ->orWhere('full_name', 'like', "%{$term}%");
            }))
            ->limit(20)
            ->get(['id', 'code', 'dni', 'full_name'])
            ->each(fn (Member $member) => $items->push([
                'id' => 'member:' . $member->id,
                'text' => ($member->code ?: 'SOCIO') . ' - ' . $member->dni . ' - ' . $member->full_name,
                'type' => 'socio',
            ]));

        return response()->json(['results' => $items->values()]);
    }

    public function verifyDni(string $dni)
    {
        if (! preg_match('/^\d{8}$/', $dni)) {
            return response()->json(['status' => false, 'message' => 'El DNI debe tener 8 digitos.'], 422);
        }

        if ($member = Member::where('dni', $dni)->first()) {
            try {
                $this->eligibility->assertCanBeGuarantor($member);
            } catch (ValidationException $exception) {
                return response()->json([
                    'status' => false,
                    'message' => $exception->errors()['guarantor_member_id'][0],
                    'errors' => ['member_id' => [$exception->errors()['guarantor_member_id'][0]]],
                ], 422);
            }
            return response()->json([
                'status' => 'member',
                'message' => 'Este DNI ya pertenece a un socio registrado. Puede usarlo como aval interno.',
                'member' => [
                    'id' => $member->id,
                    'value' => 'member:' . $member->id,
                    'label' => ($member->code ?: 'SOCIO') . ' - ' . $member->dni . ' - ' . $member->full_name,
                    'dni' => $member->dni,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'phone' => $member->phone,
                    'address' => $member->address,
                ],
            ]);
        }

        if ($guarantor = Guarantor::where('type', 'externo')->where('dni', $dni)->where('status', '!=', 'anulado')->first()) {
            return response()->json([
                'status' => 'external',
                'message' => 'Este aval externo ya esta registrado. Se seleccionara el registro existente.',
                'guarantor' => $this->selectPayload($guarantor),
            ]);
        }

        return response()->json(['status' => 'available']);
    }

    private function validatedData(Request $request, ?Guarantor $guarantor = null): array
    {
        if (! $request->has('type')) {
            $request->merge(['type' => 'externo']);
        }

        if (! $request->has('status')) {
            $request->merge(['status' => 'activo']);
        }

        $this->normalizeNullableRequestFields($request);

        if ($request->input('type') === 'socio' && $request->filled('member_id')) {
            $member = Member::find($request->input('member_id'));
            if ($member) {
                $this->eligibility->assertCanBeGuarantor($member);
                $request->merge([
                    'dni' => $member->dni,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'phone' => $request->input('phone') ?: $member->phone,
                    'address' => $request->input('address') ?: $member->address,
                ]);
            }
        }

        return $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'type' => ['required', Rule::in(['socio', 'externo'])],
            'member_id' => ['nullable', 'required_if:type,socio', Rule::exists('members', 'id')->where(fn ($query) => $query->where('status', 'vigente')->whereNull('retirement_date'))],
            'dni' => ['required', 'digits:8'],
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'photo_path' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'observation' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['activo', 'inactivo', 'anulado'])],
        ], $this->messages());
    }

    private function duplicateResponse(string $dni, string $type, ?int $ignoreGuarantorId = null)
    {
        if ($type === 'socio') {
            $member = Member::where('dni', $dni)->first();
            if (! $member) {
                return null;
            }

            $guarantor = Guarantor::where('type', 'socio')
                ->where('member_id', $member->id)
                ->when($ignoreGuarantorId, fn ($query) => $query->where('id', '!=', $ignoreGuarantorId))
                ->first();

            if ($guarantor) {
                return response()->json([
                    'duplicate_type' => 'member_guarantor',
                    'message' => 'Este socio ya esta registrado como aval interno.',
                    'guarantor' => $this->selectPayload($guarantor),
                ], 409);
            }

            return null;
        }

        if ($member = Member::where('dni', $dni)->first()) {
            return response()->json([
                'duplicate_type' => 'member',
                'message' => 'Este DNI ya pertenece a un socio registrado. Puede usarlo como aval interno.',
                'question' => 'Desea usar este socio como aval?',
                'member' => [
                    'value' => 'member:' . $member->id,
                    'label' => ($member->code ?: 'SOCIO') . ' - ' . $member->dni . ' - ' . $member->full_name,
                ],
            ], 409);
        }

        if ($guarantor = Guarantor::where('type', 'externo')
            ->where('dni', $dni)
            ->where('status', '!=', 'anulado')
            ->when($ignoreGuarantorId, fn ($query) => $query->where('id', '!=', $ignoreGuarantorId))
            ->first()) {
            return response()->json([
                'duplicate_type' => 'external',
                'message' => 'Este aval externo ya esta registrado. Se seleccionara el registro existente.',
                'guarantor' => $this->selectPayload($guarantor),
            ], 409);
        }

        return null;
    }

    private function externalByDni(string $dni): ?Guarantor
    {
        return Guarantor::where('type', 'externo')
            ->where('dni', $dni)
            ->where('status', '!=', 'anulado')
            ->first();
    }

    private function normalizeData(array &$data): void
    {
        if ($data['type'] === 'socio' && ! blank($data['member_id'] ?? null)) {
            $member = Member::findOrFail($data['member_id']);
            $data['dni'] = $member->dni;
            $data['first_name'] = $member->first_name;
            $data['last_name'] = $member->last_name;
            $data['phone'] = $member->phone;
            $data['address'] = $member->address;
        } else {
            $data['member_id'] = null;
        }

        $data['full_name'] = trim($data['first_name'] . ' ' . $data['last_name']);
    }

    private function normalizeNullableRequestFields(Request $request): void
    {
        $fields = ['member_id', 'phone', 'address', 'occupation', 'relationship', 'observation'];
        $normalized = [];

        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $normalized[$field] = null;
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    private function payload(Guarantor $guarantor): array
    {
        $guarantor->load(['member:id,code,dni,first_name,last_name,full_name,phone,address,status', 'guaranteedMembers:id,code,dni,full_name,status', 'guaranteedMembers.guarantorLinks']);

        return array_merge($this->selectPayload($guarantor), [
            'member_id' => $guarantor->member_id,
            'first_name' => $guarantor->first_name,
            'last_name' => $guarantor->last_name,
            'occupation' => $guarantor->occupation,
            'relationship' => $guarantor->relationship,
            'observation' => $guarantor->observation,
            'photo_url' => $guarantor->photo_path ? Storage::url($guarantor->photo_path) : self::DEFAULT_AVATAR,
            'created_at' => optional($guarantor->created_at)->format('d/m/Y H:i'),
            'updated_at' => optional($guarantor->updated_at)->format('d/m/Y H:i'),
            'related_members' => $guarantor->guaranteedMembers->map(fn (Member $member) => [
                'code' => $member->code,
                'full_name' => $member->full_name,
                'dni' => $member->dni,
                'status' => ucfirst(str_replace('_', ' ', $member->status)),
            ])->values(),
        ]);
    }

    private function selectPayload(Guarantor $guarantor): array
    {
        $guarantor->loadMissing('member:id,code,dni,full_name,phone,address,status');

        return [
            'id' => $guarantor->id,
            'value' => 'guarantor:' . $guarantor->id,
            'label' => $this->displayCode($guarantor) . ' - ' . $this->displayDni($guarantor) . ' - ' . $this->displayName($guarantor),
            'code' => $this->displayCode($guarantor),
            'type' => $guarantor->type,
            'type_label' => $guarantor->type === 'socio' ? 'Socio interno' : 'Aval externo',
            'dni' => $this->displayDni($guarantor),
            'full_name' => $this->displayName($guarantor),
            'phone' => $this->displayPhone($guarantor) ?: '-',
            'address' => $this->displayAddress($guarantor) ?: '-',
            'status' => $guarantor->status,
            'status_label' => ucfirst((string) $guarantor->status),
        ];
    }

    private function displayCode(Guarantor $guarantor): ?string
    {
        return $guarantor->type === 'socio' ? ($guarantor->member?->code ?: $guarantor->code) : $guarantor->code;
    }

    private function displayDni(Guarantor $guarantor): ?string
    {
        return $guarantor->type === 'socio' ? ($guarantor->member?->dni ?: $guarantor->dni) : $guarantor->dni;
    }

    private function displayName(Guarantor $guarantor): ?string
    {
        return $guarantor->type === 'socio' ? ($guarantor->member?->full_name ?: $guarantor->full_name) : $guarantor->full_name;
    }

    private function displayPhone(Guarantor $guarantor): ?string
    {
        return $guarantor->type === 'socio' ? ($guarantor->member?->phone ?: $guarantor->phone) : $guarantor->phone;
    }

    private function displayAddress(Guarantor $guarantor): ?string
    {
        return $guarantor->type === 'socio' ? ($guarantor->member?->address ?: $guarantor->address) : $guarantor->address;
    }

    private function typeBadge(string $type): string
    {
        return '<span class="badge badge-' . ($type === 'socio' ? 'info' : 'primary') . '">' . ($type === 'socio' ? 'Socio interno' : 'Aval externo') . '</span>';
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'activo' => 'success',
            'inactivo' => 'secondary',
            'anulado' => 'danger',
            default => 'secondary',
        };

        return '<span class="badge badge-' . $class . '">' . e(ucfirst($status)) . '</span>';
    }

    private function messages(): array
    {
        return [
            'dni.required' => 'El DNI es obligatorio.',
            'dni.digits' => 'El DNI debe tener 8 digitos.',
            'first_name.required' => 'Los nombres son obligatorios.',
            'last_name.required' => 'Los apellidos son obligatorios.',
            'type.required' => 'Seleccione un tipo de aval valido.',
            'type.in' => 'Seleccione un tipo de aval valido.',
            'member_id.required_if' => 'Seleccione un socio valido.',
            'member_id.exists' => 'Este socio se encuentra retirado y no puede realizar nuevas operaciones.',
            'status.required' => 'Seleccione un estado valido.',
            'status.in' => 'Seleccione un estado valido.',
            'photo_path.image' => 'La foto debe ser una imagen valida.',
            'photo_path.mimes' => 'La foto no tiene un formato valido.',
            'photo_path.max' => 'La foto no debe superar los 2 MB.',
        ];
    }
}
