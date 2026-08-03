<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guarantor;
use App\Models\MemberGuarantor;
use App\Models\Member;
use App\Models\MemberEnrollment;
use App\Services\LoanEligibilityService;
use App\Services\MemberEnrollmentService;
use App\Services\CreditHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class MemberController extends Controller
{
    private const DEFAULT_AVATAR = 'https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg';

    public function __construct(private readonly LoanEligibilityService $eligibility, private readonly MemberEnrollmentService $enrollmentService, private readonly CreditHistoryService $creditHistory)
    {
        $this->middleware('can:admin.socios.index')->only(['index', 'list']);
        $this->middleware('can:admin.socios.create')->only(['store']);
        $this->middleware('can:admin.socios.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.socios.show')->only(['show']);
        $this->middleware('can:admin.socios.delete')->only(['destroy']);
        $this->middleware('can:admin.socios.index')->only(['consultarDni', 'verifyDni', 'nextCode']);
    }

    public function index()
    {
        return view('admin.members.index', [
            'guarantorOptions' => $this->guarantorOptions(),
        ]);
    }

    public function list()
    {
        $members = Member::with(['relatives', 'accountClosures'])->orderBy('id', 'desc')->get();
        $members->each->syncCalculatedMemberType();

        return DataTables::of($members)
            ->addIndexColumn()
            ->addColumn('photo', function (Member $member) {
                return '<img src="' . e($this->photoUrl($member)) . '" class="rounded-circle" style="width:42px;height:42px;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.12);" alt="Foto">';
            })
            ->editColumn('admission_date', fn (Member $member) => optional($member->admission_date)->format('d/m/Y') ?? '-')
            ->editColumn('full_name', fn (Member $member) => e($member->full_name) . ($member->isMinor() ? ' <span class="badge badge-warning ml-1">Menor de edad</span>' : ''))
            ->addColumn('member_type', fn (Member $member) => $this->memberTypeBadge($member->calculatedMemberType()))
            ->editColumn('civil_status', fn (Member $member) => $member->civil_status ? ucfirst(str_replace('_', ' ', $member->civil_status)) : '-')
            ->editColumn('status', fn (Member $member) => $this->statusBadge($member->status) . ($member->hasPendingWithdrawalProcess() ? '<span class="badge badge-warning ml-1">En retiro</span>' : ''))
            ->addColumn('acciones', function (Member $member) {
                $closure = $member->accountClosures()->where('status', 'cerrado')->latest('closed_at')->first();
                return view('admin.members.partials.acciones', [
                    'member' => $member,
                    'hasMovements' => $this->hasMovements($member),
                    'closure' => $closure,
                ])->render();
            })
            ->rawColumns(['photo', 'full_name', 'member_type', 'status', 'acciones'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $previousMember = $this->validatedReentryMember($request);
        $linkedExternalGuarantor = false;

        DB::transaction(function () use ($request, $data, $previousMember, &$linkedExternalGuarantor) {
            if ($request->hasFile('photo_path')) {
                $data['photo_path'] = $request->file('photo_path')->store('members', 'public');
            }

            $this->normalizeMemberData($data);
            $data['reentry_from_member_id'] = $previousMember?->id;
            if ($previousMember && ! $request->hasFile('photo_path')) {
                $data['photo_path'] = $previousMember->photo_path;
            }
            $data['status'] = 'vigente';
            $data['retirement_date'] = null;
            $data['code'] = $this->generateNextCode();
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();

            $member = Member::create($data);
            $this->syncMemberEnrollment($request, $member);
            $linkedExternalGuarantor = $this->convertExternalGuarantorToMember($member);
            $this->syncRelatives($member, $request->input('relatives', []));
            $this->syncBeneficiaries($member, $request->input('beneficiaries', []));
            $this->syncGuarantor($member, $request->input('guarantor_option'));
        });

        return response()->json([
            'message' => $linkedExternalGuarantor
                ? 'Socio registrado correctamente. Esta persona estaba registrada como aval externo. Ahora fue vinculada como socio interno.'
                : ($previousMember ? 'Socio registrado correctamente como reingreso, con un nuevo código de socio.' : 'Socio registrado correctamente.'),
            'linked_external_guarantor' => $linkedExternalGuarantor,
        ]);
    }

    public function show(Member $member)
    {
        return response()->json($this->memberPayload($member));
    }

    public function edit(Member $member)
    {
        $this->ensureMemberIsActive($member);
        return response()->json($this->memberPayload($member));
    }

    public function update(Request $request, Member $member)
    {
        $this->ensureMemberIsActive($member);
        $data = $this->validatedData($request, $member);
        $linkedExternalGuarantor = false;

        DB::transaction(function () use ($request, $member, $data, &$linkedExternalGuarantor) {
            if ($request->hasFile('photo_path')) {
                if ($member->photo_path) {
                    Storage::disk('public')->delete($member->photo_path);
                }

                $data['photo_path'] = $request->file('photo_path')->store('members', 'public');
            }

            $this->normalizeMemberData($data);
            unset($data['code'], $data['status'], $data['retirement_date']);
            $data['updated_by'] = auth()->id();

            $member->update($data);
            $this->syncMemberEnrollment($request, $member);
            $linkedExternalGuarantor = $this->convertExternalGuarantorToMember($member);
            $this->syncRelatives($member, $request->input('relatives', []));
            $this->syncBeneficiaries($member, $request->input('beneficiaries', []));
            $this->syncGuarantor($member, $request->input('guarantor_option'));
        });

        return response()->json([
            'message' => $linkedExternalGuarantor
                ? 'Socio actualizado correctamente. Esta persona estaba registrada como aval externo. Ahora fue vinculada como socio interno.'
                : 'Socio actualizado correctamente.',
            'linked_external_guarantor' => $linkedExternalGuarantor,
        ]);
    }

    public function destroy(Member $member)
    {
        if (in_array($member->status, ['retirado', 'no_vigente'], true)) {
            return response()->json([
                'message' => 'No se puede eliminar porque el socio tiene historial y cierre de cuenta confirmado.',
            ], 422);
        }

        if ($this->hasMovements($member)) {
            return response()->json([
                'message' => 'No se puede eliminar porque tiene movimientos registrados.',
            ], 422);
        }

        $member->delete();

        return response()->json(['message' => 'Socio eliminado correctamente.']);
    }

    public function consultarDni(string $dni)
    {
        if (! preg_match('/^\d+$/', $dni)) {
            return response()->json([
                'status' => false,
                'message' => 'El numero de documento debe contener solo digitos.',
            ], 422);
        }

        if (strlen($dni) !== 8) {
            return response()->json([
                'status' => false,
                'message' => 'El DNI debe tener 8 digitos.',
            ], 422);
        }

        $token = config('services.apis_net_pe.token');

        if (blank($token)) {
            return response()->json([
                'status' => false,
                'message' => 'No se encontro el token para consultar DNI.',
            ], 500);
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Referer' => 'https://apis.net.pe/consulta-dni-api'])
                ->withOptions(['verify' => false])
                ->timeout(15)
                ->get('https://api.apis.net.pe/v2/reniec/dni', [
                    'numero' => $dni,
                ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Error al conectar con el servicio de DNI.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        if ($response->status() === 404) {
            return response()->json([
                'status' => false,
                'message' => 'DNI no encontrado.',
                'data' => $response->json(),
            ], 404);
        }

        if ($response->failed()) {
            return response()->json([
                'status' => false,
                'message' => 'No se pudo consultar el DNI.',
                'data' => $response->json(),
            ], 500);
        }

        $persona = $response->json();

        if (blank($persona) || isset($persona['error'])) {
            return response()->json([
                'status' => false,
                'message' => 'DNI no encontrado.',
                'data' => $persona,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'type' => 'DNI',
            'data' => $persona,
        ]);
    }

    public function nextCode()
    {
        $code = DB::transaction(fn () => $this->generateNextCode());

        if (! preg_match('/^SOC-\d{6}$/', $code)) {
            return response()->json([
                'message' => 'El codigo del socio no pudo generarse correctamente.',
            ], 500);
        }

        return response()->json(['code' => $code]);
    }

    public function verifyDni(string $dni)
    {
        if (! preg_match('/^\d{8}$/', $dni)) {
            return response()->json([
                'status' => false,
                'message' => 'El DNI debe tener 8 digitos.',
            ], 422);
        }

        if ($member = Member::where('dni', $dni)->where('status', '!=', 'retirado')->latest('id')->first()) {
            return response()->json([
                'status' => 'member',
                'message' => 'El DNI ya esta registrado en otro socio.',
                'member' => [
                    'id' => $member->id,
                    'code' => $member->code,
                    'full_name' => $member->full_name,
                ],
            ]);
        }

        if ($member = Member::where('dni', $dni)->where('status', 'retirado')->latest('id')->first()) {
            $closure = $member->accountClosures()->where('status', 'cerrado')->latest('closed_at')->first();
            return response()->json([
                'status' => 'reentry',
                'message' => 'Este DNI pertenece a un socio retirado anteriormente: ' . $member->code . ' - ' . ($closure?->code ?: 'sin cierre') . '. Puede registrarse nuevamente como reingreso. Se creará un nuevo código de socio y deberá pagar inscripción si corresponde.',
                'member' => [
                    'id' => $member->id, 'code' => $member->code, 'closure_code' => $closure?->code,
                    'dni' => $member->dni, 'first_name' => $member->first_name, 'last_name' => $member->last_name,
                    'birth_date' => optional($member->birth_date)->format('Y-m-d'), 'phone' => $member->phone,
                    'address' => $member->address, 'photo_url' => $this->photoUrl($member),
                ],
            ]);
        }

        if ($guarantor = Guarantor::where('type', 'externo')->where('dni', $dni)->where('status', '!=', 'anulado')->first()) {
            return response()->json([
                'status' => 'external',
                'message' => 'Esta persona ya esta registrada como aval externo. Desea usar sus datos para crear el socio?',
                'guarantor' => [
                    'id' => $guarantor->id,
                    'dni' => $guarantor->dni,
                    'first_name' => $guarantor->first_name,
                    'last_name' => $guarantor->last_name,
                    'phone' => $guarantor->phone,
                    'address' => $guarantor->address,
                    'observation' => $guarantor->observation,
                    'photo_url' => $guarantor->photo_path ? Storage::url($guarantor->photo_path) : null,
                ],
            ]);
        }

        return response()->json(['status' => 'available']);
    }

    private function validatedReentryMember(Request $request): ?Member
    {
        $retired = Member::where('dni', $request->input('dni'))->where('status', 'retirado')->latest('id')->first();
        if (! $retired) {
            return null;
        }

        if (! $request->boolean('reentry_confirmed') || (int) $request->input('reentry_from_member_id') !== $retired->id) {
            throw ValidationException::withMessages([
                'dni' => ['El socio ya estuvo registrado y fue retirado. Puede registrarlo nuevamente como reingreso; confirme la advertencia para continuar.'],
            ]);
        }

        return $retired;
    }

    private function validatedData(Request $request, ?Member $member = null): array
    {
        $this->normalizeNullableRequestFields($request);
        if (Member::calculateMemberTypeByAdmissionDate($request->input('admission_date')) === 'antiguo') {
            $request->request->remove('enrollment_amount');
            $request->request->remove('enrollment_date');
            $request->request->remove('enrollment_payment_method');
            $request->request->remove('enrollment_payment_reference');
            $request->request->remove('enrollment_observation');
            $request->files->remove('enrollment_voucher');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('members', 'code')->ignore($member?->id)],
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:150'],
            'dni' => ['required', 'digits:8', Rule::unique('members', 'dni')->where(fn ($query) => $query->where('status', '!=', 'retirado'))->ignore($member?->id)],
            'reentry_from_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'reentry_confirmed' => ['nullable', 'boolean'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'admission_date' => ['required', 'date'],
            'member_type_selected' => ['required', Rule::in(['nuevo', 'antiguo'])],
            'retirement_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'current_job' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'civil_status' => ['required', Rule::in(['soltero', 'casado', 'conviviente', 'divorciado', 'viudo'])],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'reference_name' => ['nullable', 'string', 'max:255'],
            'reference_dni' => ['nullable', 'digits:8'],
            'reference_phone' => ['nullable', 'string', 'max:20'],
            'guarantor_option' => ['nullable', 'string', 'max:80'],
            'photo_path' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['vigente', 'retirado', 'no_vigente'])],
            'observation' => ['nullable', 'string'],
            'relatives' => ['nullable', 'array'],
            'relatives.*.name' => ['nullable', 'string', 'max:255'],
            'relatives.*.birth_date' => ['nullable', 'date'],
            'relatives.*.observation' => ['nullable', 'string'],
            'beneficiaries' => ['nullable', 'array'],
            'beneficiaries.*.id' => ['nullable', 'integer'],
            'beneficiaries.*.full_name' => ['required_with:beneficiaries', 'string', 'max:255'],
            'beneficiaries.*.dni' => ['nullable', 'digits:8', 'distinct'],
            'beneficiaries.*.relationship' => ['required_with:beneficiaries', Rule::in(['conyuge', 'hijo', 'padre', 'madre', 'hermano', 'otro'])],
            'beneficiaries.*.phone' => ['nullable', 'string', 'max:20'],
            'beneficiaries.*.address' => ['nullable', 'string', 'max:255'],
            'beneficiaries.*.percentage' => ['required_with:beneficiaries', 'numeric', 'gt:0', 'max:100'],
            'beneficiaries.*.birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'beneficiaries.*.observation' => ['nullable', 'string'],
            'enrollment_amount' => [Rule::requiredIf(fn () => $this->calculatedMemberType($request->input('admission_date')) === 'nuevo'), 'nullable', 'numeric', 'in:50,50.00'],
            'enrollment_date' => [Rule::requiredIf(fn () => $this->calculatedMemberType($request->input('admission_date')) === 'nuevo'), 'nullable', 'date'],
            'enrollment_payment_method' => [Rule::requiredIf(fn () => $this->calculatedMemberType($request->input('admission_date')) === 'nuevo'), 'nullable', Rule::in(['efectivo', 'yape', 'plin', 'transferencia', 'otro'])],
            'enrollment_payment_reference' => ['nullable', Rule::requiredIf(fn () => $this->calculatedMemberType($request->input('admission_date')) === 'nuevo' && in_array($request->input('enrollment_payment_method'), ['yape', 'plin', 'transferencia'], true)), 'string', 'max:255'],
            'enrollment_voucher' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'enrollment_observation' => ['nullable', 'string'],
        ], $this->messages());

        $beneficiaries = collect($data['beneficiaries'] ?? []);
        if ($beneficiaries->isNotEmpty()) {
            $total = round((float) $beneficiaries->sum('percentage'), 2);
            if ($total < 100) {
                throw ValidationException::withMessages(['beneficiaries' => ['La suma de beneficiarios debe completar el 100%. Falta asignar ' . number_format(100 - $total, 2) . '%.']]);
            }
            if ($total > 100) {
                throw ValidationException::withMessages(['beneficiaries' => ['La suma de beneficiarios no puede superar el 100%. Excede ' . number_format($total - 100, 2) . '%.']]);
            }
        }

        return $data;
    }

    private function syncRelatives(Member $member, array $relatives): void
    {
        $member->relatives()->delete();

        foreach ($relatives as $relative) {
            if (blank($relative['name'] ?? null)) {
                continue;
            }

            $member->relatives()->create([
                'name' => $relative['name'],
                'relationship' => $relative['relationship'] ?? 'hijo',
                'birth_date' => $relative['birth_date'] ?? null,
                'observation' => $relative['observation'] ?? null,
            ]);
        }
    }

    private function syncBeneficiaries(Member $member, array $beneficiaries): void
    {
        $keptIds = [];
        foreach ($beneficiaries as $beneficiary) {
            $birthDate = $beneficiary['birth_date'] ?? null;
            $values = [
                'full_name' => trim($beneficiary['full_name']),
                'dni' => ($beneficiary['dni'] ?? null) ?: null,
                'relationship' => $beneficiary['relationship'],
                'phone' => ($beneficiary['phone'] ?? null) ?: null,
                'address' => ($beneficiary['address'] ?? null) ?: null,
                'percentage' => $beneficiary['percentage'],
                'birth_date' => $birthDate,
                'is_minor' => $birthDate ? \Carbon\Carbon::parse($birthDate)->gt(today()->subYears(18)) : false,
                'observation' => ($beneficiary['observation'] ?? null) ?: null,
                'updated_by' => auth()->id(),
            ];
            $record = isset($beneficiary['id'])
                ? $member->beneficiaries()->whereKey($beneficiary['id'])->first()
                : null;
            if ($record) {
                $record->update($values);
            } else {
                $record = $member->beneficiaries()->create($values + ['created_by' => auth()->id()]);
            }
            $keptIds[] = $record->id;
        }
        $member->beneficiaries()->when($keptIds, fn ($query) => $query->whereNotIn('id', $keptIds))->delete();
    }

    private function syncMemberEnrollment(Request $request, Member $member): void
    {
        if ($member->calculatedMemberType() === 'antiguo') {
            return;
        }

        $enrollment = $member->enrollments()->where('status', 'registrado')->lockForUpdate()->first();
        if (! $enrollment) {
            $enrollment = new MemberEnrollment([
                'code' => MemberEnrollment::nextCode(), 'member_id' => $member->id,
                'status' => 'registrado', 'created_by' => auth()->id(),
            ]);
        }

        $enrollment->fill([
            'enrollment_date' => $request->input('enrollment_date'), 'amount' => 50,
            'payment_method' => $request->input('enrollment_payment_method'),
            'payment_reference' => $request->input('enrollment_payment_reference'),
            'observation' => $request->input('enrollment_observation'), 'updated_by' => auth()->id(),
        ]);
        if ($request->hasFile('enrollment_voucher')) {
            $enrollment->voucher_path = $request->file('enrollment_voucher')->store('member-enrollments', 'public');
        }
        $enrollment->save();
        $this->enrollmentService->sync($enrollment);
    }

    private function normalizeMemberData(array &$data): void
    {
        $data['full_name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        $data['member_type'] = $this->calculatedMemberType($data['admission_date']);

        if (! blank($data['retirement_date'] ?? null)) {
            $data['status'] = 'retirado';
        }

        unset($data['relatives'], $data['beneficiaries'], $data['guarantor_option'], $data['enrollment_amount'], $data['enrollment_date'], $data['enrollment_payment_method'], $data['enrollment_payment_reference'], $data['enrollment_voucher'], $data['enrollment_observation']);
    }

    private function normalizeNullableRequestFields(Request $request): void
    {
        $fields = [
            'code',
            'retirement_date',
            'current_job',
            'address',
            'spouse_name',
            'phone',
            'reference_name',
            'reference_dni',
            'reference_phone',
            'guarantor_option',
            'observation',
        ];

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

    private function generateNextCode(): string
    {
        $lastNumber = Member::withTrashed()
            ->whereNotNull('code')
            ->where('code', 'like', 'SOC-%')
            ->lockForUpdate()
            ->pluck('code')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^SOC-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'SOC-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function memberPayload(Member $member): array
    {
        $member->load([
            'previousMember',
            'subsequentReentries',
            'relatives' => fn ($query) => $query->orderBy('id'),
            'beneficiaries' => fn ($query) => $query->orderBy('id'),
            'creator',
            'updater',
            'enrollments' => fn ($query) => $query->where('status', 'registrado')->latest('id'),
            'enrollments.receipt',
            'enrollments.cashMovement',
            'guarantorLinks' => fn ($query) => $query->where('status', 'activo')->latest('id'),
            'guarantorLinks.guarantor.member',
            'accountClosures' => fn ($query) => $query->where('status', 'cerrado')->latest('closed_at')->with('receipt'),
        ]);

        $guarantor = $member->guarantorLinks->first()?->guarantor;
        $enrollment = $member->enrollments->first();
        $closure = $member->accountClosures->first();

        return [
            'id' => $member->id,
            'code' => $member->code,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'full_name' => $member->full_name,
            'dni' => $member->dni,
            'birth_date' => optional($member->birth_date)->format('Y-m-d'),
            'birth_date_formatted' => optional($member->birth_date)->format('d/m/Y'),
            'age' => $member->birth_date ? $member->birth_date->age : null,
            'is_minor' => $member->isMinor(),
            'admission_date' => optional($member->admission_date)->format('Y-m-d'),
            'member_type' => $member->member_type ?: $this->eligibility->memberType($member),
            'member_type_selected' => $member->member_type_selected,
            'member_type_calculated' => $this->eligibility->memberType($member),
            'membership_time' => $member->admission_date ? $member->admission_date->diffForHumans(now(), true) : '-',
            'enrollment_pending' => $this->eligibility->memberType($member) === 'nuevo' && ! $member->enrollments()->where('status', 'registrado')->exists(),
            'admission_date_formatted' => optional($member->admission_date)->format('d/m/Y'),
            'retirement_date' => optional($member->retirement_date)->format('Y-m-d'),
            'retirement_date_formatted' => optional($member->retirement_date)->format('d/m/Y'),
            'current_job' => $member->current_job,
            'address' => $member->address,
            'civil_status' => $member->civil_status,
            'spouse_name' => $member->spouse_name,
            'phone' => $member->phone,
            'reference_name' => $member->reference_name,
            'reference_dni' => $member->reference_dni,
            'reference_phone' => $member->reference_phone,
            'guarantor_option' => $guarantor
                ? ($guarantor->type === 'socio' && $guarantor->member_id ? 'member:' . $guarantor->member_id : 'guarantor:' . $guarantor->id)
                : null,
            'guarantor' => $guarantor ? $this->guarantorPayload($guarantor) : null,
            'enrollment' => $enrollment ? $this->enrollmentPayload($enrollment) : null,
            'financial_summary' => $this->financialSummary($member),
            'credit_history' => $this->creditHistory->summary($member),
            'credit_history_url' => auth()->user()?->can('credit-history.show') ? route('admin.historial-crediticio.show', $member) : null,
            'credit_history_recalculate_url' => auth()->user()?->can('credit-history.recalculate') ? route('admin.historial-crediticio.recalculate', $member) : null,
            'photo_url' => $this->photoUrl($member),
            'status' => $member->status,
            'status_label' => $this->statusLabel($member->status),
            'withdrawal_pending' => $member->hasPendingWithdrawalProcess(),
            'reentry_from' => $member->previousMember ? ['id' => $member->previousMember->id, 'code' => $member->previousMember->code] : null,
            'subsequent_reentries' => $member->subsequentReentries->map(fn (Member $reentry) => ['id' => $reentry->id, 'code' => $reentry->code])->values(),
            'observation' => $member->observation,
            'account_closure' => $closure ? [
                'code' => $closure->code,
                'retirement_date' => optional($closure->retirement_date)->format('d/m/Y'),
                'final_balance_formatted' => $this->money($closure->final_balance),
                'payment_method_label' => ucfirst((string) ($closure->payment_method ?: '-')),
                'constancy_url' => route('admin.retiros-socios.pdf', $closure),
                'receipt_url' => $closure->receipt ? route('admin.retiros-socios.receipt.pdf', $closure) : null,
            ] : null,
            'created_at' => optional($member->created_at)->format('d/m/Y H:i'),
            'created_by_name' => $member->creator?->name,
            'updated_at' => optional($member->updated_at)->format('d/m/Y H:i'),
            'updated_by_name' => $member->updater?->name,
            'relatives' => $member->relatives->map(fn ($relative) => [
                'id' => $relative->id,
                'name' => $relative->name,
                'relationship' => $relative->relationship,
                'birth_date' => optional($relative->birth_date)->format('Y-m-d'),
                'birth_date_formatted' => optional($relative->birth_date)->format('d/m/Y'),
                'observation' => $relative->observation,
            ])->values(),
            'beneficiaries' => $member->beneficiaries->map(fn ($beneficiary) => [
                'id' => $beneficiary->id,
                'full_name' => $beneficiary->full_name,
                'dni' => $beneficiary->dni,
                'relationship' => $beneficiary->relationship,
                'relationship_label' => match ($beneficiary->relationship) {
                    'conyuge' => 'Esposa / Cónyuge', 'hijo' => 'Hijo(a)', 'padre' => 'Padre',
                    'madre' => 'Madre', 'hermano' => 'Hermano(a)', default => 'Otro',
                },
                'phone' => $beneficiary->phone,
                'address' => $beneficiary->address,
                'percentage' => number_format((float) $beneficiary->percentage, 2, '.', ''),
                'birth_date' => optional($beneficiary->birth_date)->format('Y-m-d'),
                'is_minor' => $beneficiary->is_minor,
                'observation' => $beneficiary->observation,
            ])->values(),
        ];
    }

    private function photoUrl(Member $member): string
    {
        return $member->photo_path ? Storage::url($member->photo_path) : self::DEFAULT_AVATAR;
    }

    private function statusBadge(string $status): string
    {
        $classes = [
            'vigente' => 'success',
            'retirado' => 'danger',
            'no_vigente' => 'secondary',
        ];

        return '<span class="badge badge-' . ($classes[$status] ?? 'secondary') . '">' . e($this->statusLabel($status)) . '</span>';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'vigente' => 'Vigente',
            'retirado' => 'Retirado',
            'no_vigente' => 'No vigente',
            default => 'No definido',
        };
    }

    private function messages(): array
    {
        return [
            'first_name.required' => 'El nombre del socio es obligatorio.',
            'first_name.string' => 'El nombre del socio debe ser texto.',
            'first_name.max' => 'El nombre del socio no debe superar 150 caracteres.',
            'last_name.required' => 'Los apellidos del socio son obligatorios.',
            'last_name.string' => 'Los apellidos del socio deben ser texto.',
            'last_name.max' => 'Los apellidos del socio no deben superar 150 caracteres.',
            'dni.required' => 'El DNI es obligatorio.',
            'dni.digits' => 'El DNI debe tener 8 digitos.',
            'dni.unique' => 'El DNI ya esta registrado en otro socio.',
            'birth_date.required' => 'La fecha de nacimiento es obligatoria.',
            'birth_date.date' => 'La fecha de nacimiento debe ser valida.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser mayor a la fecha actual.',
            'admission_date.required' => 'La fecha de ingreso es obligatoria.',
            'admission_date.date' => 'La fecha de ingreso debe ser valida.',
            'retirement_date.date' => 'La fecha de retiro debe ser valida.',
            'retirement_date.after_or_equal' => 'La fecha de retiro no puede ser menor a la fecha de ingreso.',
            'civil_status.required' => 'Seleccione un estado civil valido.',
            'civil_status.in' => 'Seleccione un estado civil valido.',
            'reference_dni.digits' => 'El DNI de referencia debe tener 8 digitos.',
            'photo_path.image' => 'La foto debe ser una imagen valida.',
            'photo_path.mimes' => 'La foto no tiene un formato valido.',
            'photo_path.max' => 'La foto no debe superar los 2 MB.',
            'status.required' => 'Seleccione un estado valido.',
            'status.in' => 'Seleccione un estado valido.',
            'relatives.*.name.max' => 'El nombre del hijo no debe superar 255 caracteres.',
            'relatives.*.birth_date.date' => 'La fecha de nacimiento del hijo debe ser valida.',
            'guarantor_option.max' => 'Seleccione un aval o garante valido.',
            'enrollment_amount.required' => 'La inscripcion del socio nuevo es obligatoria.',
            'enrollment_amount.in' => 'El monto de inscripcion debe ser S/ 50.00.',
            'enrollment_date.required' => 'La inscripcion del socio nuevo es obligatoria.',
            'enrollment_payment_method.required' => 'Seleccione un metodo de pago valido.',
            'enrollment_payment_method.in' => 'Seleccione un metodo de pago valido.',
            'enrollment_payment_reference.required' => 'La referencia es obligatoria para este metodo de pago.',
            'enrollment_voucher.mimes' => 'El comprobante debe ser una imagen o PDF valido.',
            'enrollment_voucher.max' => 'El comprobante no debe superar los 4 MB.',
        ];
    }

    private function syncGuarantor(Member $member, ?string $selection): void
    {
        $member->guarantorLinks()->update(['status' => 'inactivo']);

        if (blank($selection)) {
            $member->forceFill([
                'reference_name' => null,
                'reference_dni' => null,
                'reference_phone' => null,
            ])->save();

            return;
        }

        $guarantor = $this->resolveGuarantorSelection($selection, $member);
        $payload = $this->guarantorPayload($guarantor);

        MemberGuarantor::updateOrCreate(
            [
                'member_id' => $member->id,
                'guarantor_id' => $guarantor->id,
                'relationship_type' => 'aval',
            ],
            [
                'guarantor_member_id' => $guarantor->member_id,
                'is_main' => true,
                'status' => 'activo',
            ]
        );

        $member->forceFill([
            'reference_name' => $payload['full_name'],
            'reference_dni' => $payload['dni'],
            'reference_phone' => $payload['phone'] !== '-' ? $payload['phone'] : null,
        ])->save();
    }

    private function resolveGuarantorSelection(string $selection, Member $member): Guarantor
    {
        [$type, $id] = array_pad(explode(':', $selection, 2), 2, null);

        if ($type !== 'member' || ! ctype_digit((string) $id)) {
            throw ValidationException::withMessages([
                'guarantor_option' => 'Solo se permite seleccionar como garante a un socio registrado.',
            ]);
        }

        if ((int) $id === $member->id) {
            throw ValidationException::withMessages([
                'guarantor_option' => 'Un socio no puede ser aval de si mismo.',
            ]);
        }

        $guarantorMember = Member::find((int) $id);
        if (! $guarantorMember) {
            throw ValidationException::withMessages(['guarantor_option' => [LoanEligibilityService::WITHDRAWAL_GUARANTOR_MESSAGE]]);
        }
        try {
            $this->eligibility->assertCanBeGuarantor($guarantorMember);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['guarantor_option' => [$exception->errors()['guarantor_member_id'][0]]]);
        }
        $guarantor = Guarantor::firstOrNew([
            'type' => 'socio',
            'member_id' => $guarantorMember->id,
        ]);

        if (! $guarantor->exists) {
            $guarantor->code = Guarantor::nextCode();
            $guarantor->created_by = auth()->id();
        }

        $guarantor->fill([
            'dni' => $guarantorMember->dni,
            'first_name' => $guarantorMember->first_name,
            'last_name' => $guarantorMember->last_name,
            'full_name' => $guarantorMember->full_name,
            'phone' => $guarantorMember->phone,
            'address' => $guarantorMember->address,
            'status' => 'activo',
            'updated_by' => auth()->id(),
        ])->save();

        return $guarantor->load('member');
    }

    private function convertExternalGuarantorToMember(Member $member): bool
    {
        $guarantor = Guarantor::where('type', 'externo')
            ->where('dni', $member->dni)
            ->where('status', '!=', 'anulado')
            ->first();

        if (! $guarantor) {
            return false;
        }

        $guarantor->forceFill([
            'type' => 'socio',
            'member_id' => $member->id,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'address' => $member->address,
            'status' => 'activo',
            'updated_by' => auth()->id(),
        ])->save();

        return true;
    }

    private function guarantorOptions(): array
    {
        $members = Member::query()
            ->eligibleGuarantors()
            ->orderBy('full_name')
            ->get(['id', 'code', 'dni', 'full_name'])
            ->map(fn (Member $member) => [
                'value' => 'member:' . $member->id,
                'label' => ($member->code ?: 'SOCIO') . ' - ' . $member->dni . ' - ' . $member->full_name,
                'type' => 'socio',
                'type_label' => 'Socio interno',
            ]);

        return $members->values()->all();
    }

    private function calculatedMemberType(?string $admissionDate): string
    {
        return Member::calculateMemberTypeByAdmissionDate($admissionDate);
    }

    private function memberTypeBadge(string $type): string
    {
        return '<span class="badge badge-' . ($type === 'nuevo' ? 'info' : 'success') . '">' . ($type === 'nuevo' ? 'Nuevo' : 'Antiguo') . '</span>';
    }

    private function guarantorPayload(Guarantor $guarantor): array
    {
        $guarantor->loadMissing('member');
        $member = $guarantor->member;
        $isMember = $guarantor->type === 'socio';

        return [
            'id' => $guarantor->id,
            'value' => 'guarantor:' . $guarantor->id,
            'code' => $isMember ? ($member?->code ?: $guarantor->code) : $guarantor->code,
            'type' => $guarantor->type,
            'type_label' => $isMember ? 'Socio interno' : 'Aval externo',
            'dni' => $isMember ? ($member?->dni ?: $guarantor->dni) : $guarantor->dni,
            'full_name' => $isMember ? ($member?->full_name ?: $guarantor->full_name) : $guarantor->full_name,
            'phone' => $isMember ? ($member?->phone ?: '-') : ($guarantor->phone ?: '-'),
            'address' => $isMember ? ($member?->address ?: '-') : ($guarantor->address ?: '-'),
            'status' => $guarantor->status,
            'status_label' => ucfirst((string) $guarantor->status),
            'total_contributions' => (float) ($member?->shares()->where('status', 'registrado')->sum('share_capital_amount') ?? 0),
            'total_contributions_formatted' => 'S/ ' . number_format((float) ($member?->shares()->where('status', 'registrado')->sum('share_capital_amount') ?? 0), 2),
        ];
    }

    private function enrollmentPayload(MemberEnrollment $enrollment): array
    {
        $cash = $enrollment->cashMovement;
        $voucherExists = $enrollment->voucher_path && Storage::disk('public')->exists($enrollment->voucher_path);
        $extension = strtolower(pathinfo((string) $enrollment->voucher_path, PATHINFO_EXTENSION));
        $mimeType = $voucherExists ? Storage::disk('public')->mimeType($enrollment->voucher_path) : null;
        $publicUrl = $voucherExists ? Storage::disk('public')->url($enrollment->voucher_path) : null;
        if ($publicUrl && parse_url($publicUrl, PHP_URL_HOST)) {
            $publicUrl = parse_url($publicUrl, PHP_URL_PATH);
        }
        return [
            'id' => $enrollment->id, 'code' => $enrollment->code,
            'enrollment_date' => optional($enrollment->enrollment_date)->format('Y-m-d'),
            'enrollment_date_formatted' => optional($enrollment->enrollment_date)->format('d/m/Y'),
            'amount' => number_format((float) $enrollment->amount, 2, '.', ''),
            'amount_formatted' => 'S/ ' . number_format((float) $enrollment->amount, 2),
            'payment_method' => $enrollment->payment_method,
            'payment_method_label' => ucfirst((string) $enrollment->payment_method),
            'payment_reference' => $enrollment->payment_reference,
            'observation' => $enrollment->observation,
            'status' => $enrollment->status,
            'status_label' => $enrollment->status === 'registrado' ? 'Registrado' : 'Anulado',
            'voucher_exists' => (bool) $voucherExists,
            'voucher_missing' => (bool) $enrollment->voucher_path && ! $voucherExists,
            'voucher_is_pdf' => $extension === 'pdf' || $mimeType === 'application/pdf',
            'voucher_is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) || str_starts_with((string) $mimeType, 'image/'),
            'voucher_mime_type' => $mimeType,
            'voucher_extension' => $extension,
            'voucher_file_name' => basename((string) $enrollment->voucher_path),
            'voucher_public_url' => $publicUrl,
            'voucher_url' => $voucherExists ? route('admin.inscripciones.voucher', $enrollment) : null,
            'voucher_view_url' => $voucherExists ? route('admin.inscripciones.voucher.view', $enrollment) : null,
            'receipt_number' => $enrollment->receipt?->receipt_number,
            'receipt_url' => $enrollment->receipt ? route('admin.recibos.print', $enrollment->receipt) : null,
            'cash_code' => $cash?->movement_number,
            'cash_status' => $cash?->status,
            'cash_balance_after' => $cash?->balance_after !== null ? 'S/ ' . number_format((float) $cash->balance_after, 2) : '-',
        ];
    }

    private function financialSummary(Member $member): array
    {
        $totalAmount = (float) $member->shares()
            ->where('status', 'registrado')
            ->sum('amount');
        $totalShares = (float) $member->shares()
            ->where('status', 'registrado')
            ->sum('shares_quantity');
        $activeLoans = $member->loans()
            ->whereIn('status', ['desembolsado', 'refinanciado'])
            ->where('current_balance', '>', 0);
        $pendingDebt = (float) (clone $activeLoans)->sum('current_balance');
        $pendingUtilities = (float) $member->profitDistributionDetails()
            ->whereNotIn('status', ['pagado', 'anulado'])
            ->selectRaw('COALESCE(SUM(profit_amount - paid_amount), 0) as pending')
            ->value('pending');

        return [
            'total_amount' => $this->money($totalAmount),
            'contribution_count' => $member->shares()->where('status', 'registrado')->count(),
            'total_shares' => number_format($totalShares, 4),
            'active_loans' => (clone $activeLoans)->count(),
            'pending_debt' => $this->money($pendingDebt),
            'pending_utilities' => $this->money($pendingUtilities),
        ];
    }

    private function hasMovements(Member $member): bool
    {
        return $member->shares()->exists()
            || $member->enrollments()->exists()
            || $member->loans()->exists()
            || $member->loanPayments()->exists()
            || $member->profitDistributionDetails()->exists()
            || $member->accountClosures()->exists()
            || $member->solidarityMovements()->exists()
            || $member->activityMovements()->exists()
            || $member->receipts()->exists();
    }

    private function ensureMemberIsActive(Member $member): void
    {
        if ($member->status !== 'vigente' || $member->retirement_date) {
            throw ValidationException::withMessages([
                'member' => ['Este socio se encuentra retirado y no puede realizar nuevas operaciones.'],
            ]);
        }
    }

    private function hasPendingDebt(Member $member): bool
    {
        return $member->loans()
            ->whereIn('status', ['desembolsado', 'refinanciado'])
            ->where('current_balance', '>', 0)
            ->exists();
    }

    private function money(float $amount): string
    {
        return 'S/ ' . number_format($amount, 2);
    }
}
