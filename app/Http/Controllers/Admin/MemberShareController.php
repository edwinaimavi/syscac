<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Member;
use App\Models\MemberShare;
use App\Models\Receipt;
use App\Services\ShareCashMovementService;
use App\Services\ShareContributionCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class MemberShareController extends Controller
{
    private const DEFAULT_SHARE_VALUE = 20.00;

    public function __construct()
    {
        $this->middleware('can:admin.acciones.index')->only(['index', 'list', 'nextCode', 'historyByMember', 'summary']);
        $this->middleware('can:admin.acciones.create')->only(['store']);
        $this->middleware('can:admin.acciones.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.acciones.show')->only(['show', 'voucher', 'voucherView']);
        $this->middleware('can:admin.acciones.anular')->only(['annul']);
        $this->middleware('can:admin.acciones.delete')->only(['destroy']);
        $this->middleware('can:admin.acciones.receipt')->only(['receipt']);
    }

    public function index()
    {
        $members = Member::query()
            ->where('status', 'vigente')
            ->orderBy('full_name')
            ->get(['id', 'code', 'dni', 'full_name']);

        return view('admin.member-shares.index', [
            'members' => $members,
            'nextCode' => $this->generateNextCode(),
            'defaultShareValue' => number_format(self::DEFAULT_SHARE_VALUE, 2, '.', ''),
        ]);
    }

    public function list(Request $request)
    {
        $shares = MemberShare::with(['member'])
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date', '<=', $request->input('date_to')))
            ->orderByDesc('id');

        return DataTables::of($shares)
            ->addIndexColumn()
            ->editColumn('date', fn (MemberShare $share) => optional($share->date)->format('d/m/Y') ?? '-')
            ->addColumn('member_name', fn (MemberShare $share) => $share->member?->full_name ?? '-')
            ->addColumn('member_dni', fn (MemberShare $share) => $share->member?->dni ?? '-')
            ->addColumn('total_paid', fn (MemberShare $share) => $this->money($share->total_paid ?? $share->amount))
            ->addColumn('share_capital_amount', fn (MemberShare $share) => $this->money($share->share_capital_amount ?? $share->amount))
            ->addColumn('solidarity_amount', fn (MemberShare $share) => $this->money($share->solidarity_amount))
            ->addColumn('administrative_fee_amount', fn (MemberShare $share) => $this->money($share->administrative_fee_amount))
            ->editColumn('share_value', fn (MemberShare $share) => 'S/ ' . number_format((float) $share->share_value, 2))
            ->editColumn('shares_quantity', fn (MemberShare $share) => $this->formatQuantity((float) $share->shares_quantity))
            ->editColumn('payment_method', fn (MemberShare $share) => $this->paymentMethodLabel($share->payment_method))
            ->editColumn('status', fn (MemberShare $share) => $this->statusBadge($share->status))
            ->addColumn('acciones', fn (MemberShare $share) => view('admin.member-shares.partials.acciones', compact('share'))->render())
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function nextCode()
    {
        return response()->json(['code' => $this->generateNextCode()]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $share = DB::transaction(function () use ($request, $data) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $this->ensureActiveMember($member);

            $data['code'] = $this->generateNextCode();
            $this->normalizeShareData($data);
            $this->storeVoucher($request, $data);
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();

            $share = MemberShare::create($data);
            $receipt = $this->createOrUpdateReceipt($share);

            $share->forceFill(['receipt_number' => $receipt->receipt_number])->save();
            app(ShareCashMovementService::class)->sync($share->fresh(['member']));

            return $share;
        });

        return response()->json([
            'message' => 'Aporte registrado correctamente.',
            'id' => $share->id,
        ]);
    }

    public function show(MemberShare $memberShare)
    {
        return response()->json($this->sharePayload($memberShare));
    }

    public function edit(MemberShare $memberShare)
    {
        if ($memberShare->status === 'anulado') {
            return response()->json(['message' => 'No se puede editar un aporte anulado.'], 422);
        }

        return response()->json($this->sharePayload($memberShare));
    }

    public function update(Request $request, MemberShare $memberShare)
    {
        if ($memberShare->status === 'anulado') {
            return response()->json(['message' => 'No se puede editar un aporte anulado.'], 422);
        }

        $data = $this->validatedData($request, $memberShare);

        DB::transaction(function () use ($request, $memberShare, $data) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $this->ensureActiveMember($member);

            $this->normalizeShareData($data);
            $this->storeVoucher($request, $data, $memberShare);

            unset($data['code'], $data['receipt_number']);
            $data['updated_by'] = auth()->id();

            $memberShare->update($data);
            $freshShare = $memberShare->fresh(['member']);
            $this->createOrUpdateReceipt($freshShare);
            app(ShareCashMovementService::class)->sync($freshShare);
        });

        return response()->json(['message' => 'Aporte actualizado correctamente.']);
    }

    public function destroy(MemberShare $memberShare)
    {
        return $this->annul($memberShare);
    }

    public function annul(MemberShare $memberShare)
    {
        if ($memberShare->status === 'anulado') {
            return response()->json(['message' => 'El aporte ya se encuentra anulado.'], 422);
        }

        DB::transaction(function () use ($memberShare) {
            $memberShare->update([
                'status' => 'anulado',
                'updated_by' => auth()->id(),
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
            ]);

            $memberShare->receipt()->update([
                'status' => 'anulado',
                'updated_by' => auth()->id(),
            ]);

            app(ShareCashMovementService::class)->annul($memberShare);
        });

        return response()->json(['message' => 'Aporte anulado correctamente.']);
    }

    public function receipt(MemberShare $memberShare)
    {
        $memberShare->load(['member', 'creator', 'receipt']);

        if (! $memberShare->receipt) {
            $this->createOrUpdateReceipt($memberShare);
            $memberShare->load('receipt');
        }

        return view('admin.member-shares.receipt', [
            'share' => $memberShare,
            'receipt' => $memberShare->receipt,
        ]);
    }

    public function receiptPdf(MemberShare $memberShare)
    {
        $memberShare->load('receipt');
        if (! $memberShare->receipt) {
            $this->createOrUpdateReceipt($memberShare);
            $memberShare->load('receipt');
        }

        return redirect()->route('admin.recibos.pdf', $memberShare->receipt);
    }

    public function voucher(MemberShare $memberShare)
    {
        if (! $memberShare->voucher_path || ! Storage::disk('public')->exists($memberShare->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return Storage::disk('public')->download($memberShare->voucher_path);
    }

    public function voucherView(MemberShare $memberShare)
    {
        if (! $memberShare->voucher_path || ! Storage::disk('public')->exists($memberShare->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return response()->file(Storage::disk('public')->path($memberShare->voucher_path), [
            'Content-Disposition' => 'inline; filename="' . basename($memberShare->voucher_path) . '"',
        ]);
    }

    public function voucherStateForView(MemberShare $share): array
    {
        return $this->voucherPayload($share);
    }

    public function historyByMember(Member $member)
    {
        $shares = $member->shares()
            ->where('status', 'registrado')
            ->orderByDesc('date')
            ->get()
            ->map(fn (MemberShare $share) => [
                'code' => $share->code,
                'date' => optional($share->date)->format('d/m/Y'),
                'amount' => number_format((float) $share->amount, 2),
                'share_value' => number_format((float) $share->share_value, 2),
                'shares_quantity' => $this->formatQuantity((float) $share->shares_quantity),
                'receipt_number' => $share->receipt_number,
            ]);

        return response()->json($shares);
    }

    public function summary(Request $request)
    {
        $query = MemberShare::query()
            ->where('status', 'registrado')
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date', '<=', $request->input('date_to')));

        return response()->json([
            'total_received' => number_format((float) (clone $query)->sum('total_paid'), 2),
            'total_amount' => number_format((float) (clone $query)->sum('share_capital_amount'), 2),
            'total_solidarity' => number_format((float) (clone $query)->sum('solidarity_amount'), 2),
            'total_administrative_fees' => number_format((float) (clone $query)->sum('administrative_fee_amount'), 2),
            'total_shares' => $this->formatQuantity((float) $query->sum('shares_quantity')),
            'total_records' => (clone $query)->count(),
        ]);
    }

    private function validatedData(Request $request, ?MemberShare $share = null): array
    {
        $this->normalizeNullableRequestFields($request);
        $requestedMember = Member::find($request->integer('member_id'));
        if ($requestedMember && ($requestedMember->status !== 'vigente' || $requestedMember->retirement_date)) {
            throw ValidationException::withMessages(['member_id' => ['Este socio se encuentra retirado y no puede realizar nuevas operaciones.']]);
        }

        $data = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'date' => ['required', 'date'],
            'total_paid' => ['required', 'numeric', 'gt:0'],
            'solidarity_amount' => ['nullable', 'numeric', 'min:0'],
            'administrative_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'share_value' => ['nullable', 'numeric'],
            'shares_quantity' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in(['efectivo', 'yape', 'plin', 'transferencia', 'otro'])],
            'payment_reference' => [
                Rule::requiredIf(fn () => in_array($request->input('payment_method'), ['yape', 'plin', 'transferencia'], true)),
                'nullable',
                'string',
                'max:100',
            ],
            'voucher_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'observation' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['registrado', 'anulado'])],
        ], $this->messages());

        $member = Member::find($data['member_id']);
        if (! $member || $member->status !== 'vigente' || $member->retirement_date) {
            throw ValidationException::withMessages(['member_id' => ['Este socio se encuentra retirado y no puede realizar nuevas operaciones.']]);
        }

        return $data;
    }

    private function normalizeNullableRequestFields(Request $request): void
    {
        $fields = ['payment_reference', 'observation'];
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

    private function normalizeShareData(array &$data): void
    {
        if (($data['payment_method'] ?? null) === 'efectivo') {
            $data['payment_reference'] = null;
        }

        $breakdown = app(ShareContributionCalculator::class)->calculate(
            (float) $data['total_paid'],
            (float) ($data['solidarity_amount'] ?? 0),
            (float) ($data['administrative_fee_amount'] ?? 0)
        );
        $data = array_replace($data, $breakdown);
        $data['amount'] = $breakdown['share_capital_amount']; // Compatibilidad: amount siempre representa capital reembolsable.
        $data['status'] = $data['status'] ?? 'registrado';
    }

    private function storeVoucher(Request $request, array &$data, ?MemberShare $share = null): void
    {
        if (! $request->hasFile('voucher_path')) {
            unset($data['voucher_path']);
            return;
        }

        if ($share?->voucher_path) {
            Storage::disk('public')->delete($share->voucher_path);
        }

        $data['voucher_path'] = $request->file('voucher_path')->store('member-shares', 'public');
    }

    private function ensureActiveMember(Member $member): void
    {
        if ($member->status !== 'vigente') {
            throw ValidationException::withMessages([
                'member_id' => ['El socio seleccionado no esta vigente.'],
            ]);
        }
    }

    private function generateNextCode(): string
    {
        return MemberShare::nextCode();
    }

    private function generateNextReceiptNumber(): string
    {
        $lastNumber = Receipt::withTrashed()
            ->whereNotNull('receipt_number')
            ->where('receipt_number', 'like', 'REC-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('receipt_number');

        $number = 0;

        if ($lastNumber && preg_match('/REC-(\d+)/', $lastNumber, $matches)) {
            $number = (int) $matches[1];
        }

        return 'REC-' . str_pad((string) ($number + 1), 6, '0', STR_PAD_LEFT);
    }

    private function createOrUpdateReceipt(MemberShare $share): Receipt
    {
        $share->loadMissing('member');

        $receipt = $share->receipt ?: new Receipt([
            'receipt_number' => $share->receipt_number ?: $this->generateNextReceiptNumber(),
        ]);

        $receipt->fill([
            'receipt_date' => $share->date,
            'member_id' => $share->member_id,
            'type' => 'aporte_accion',
            'amount' => $share->total_paid ?? $share->amount,
            'payment_method' => $share->payment_method,
            'payment_reference' => $share->payment_reference,
            'voucher_path' => $share->voucher_path,
            'related_type' => MemberShare::class,
            'related_id' => $share->id,
            'observation' => $share->observation,
            'status' => $share->status,
            'created_by' => $share->created_by ?: auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $receipt->save();

        return $receipt;
    }

    private function sharePayload(MemberShare $share): array
    {
        $share->load(['member', 'creator', 'updater', 'annuller', 'receipt']);
        $voucher = $this->voucherPayload($share);
        $cashMovements = $this->cashMovementPayload($share);

        return [
            'id' => $share->id,
            'code' => $share->code,
            'date' => optional($share->date)->format('Y-m-d'),
            'date_formatted' => optional($share->date)->format('d/m/Y'),
            'member_id' => $share->member_id,
            'member_code' => $share->member?->code,
            'member_dni' => $share->member?->dni,
            'member_name' => $share->member?->full_name,
            'amount' => number_format((float) $share->amount, 2, '.', ''),
            'amount_formatted' => 'S/ ' . number_format((float) $share->amount, 2),
            'total_paid' => number_format((float) ($share->total_paid ?? $share->amount), 2, '.', ''),
            'share_capital_amount' => number_format((float) ($share->share_capital_amount ?? $share->amount), 2, '.', ''),
            'solidarity_amount' => number_format((float) $share->solidarity_amount, 2, '.', ''),
            'administrative_fee_amount' => number_format((float) $share->administrative_fee_amount, 2, '.', ''),
            'total_paid_formatted' => $this->money($share->total_paid ?? $share->amount),
            'share_capital_amount_formatted' => $this->money($share->share_capital_amount ?? $share->amount),
            'solidarity_amount_formatted' => $this->money($share->solidarity_amount),
            'administrative_fee_amount_formatted' => $this->money($share->administrative_fee_amount),
            'share_value' => number_format((float) $share->share_value, 2, '.', ''),
            'share_value_formatted' => 'S/ ' . number_format((float) $share->share_value, 2),
            'shares_quantity' => $this->formatQuantity((float) $share->shares_quantity),
            'payment_method' => $share->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($share->payment_method),
            'payment_reference' => $share->payment_reference,
            'payment_reference_display' => $share->payment_method === 'efectivo' ? 'No aplica' : ($share->payment_reference ?: '-'),
            'voucher_path' => $share->voucher_path,
            'voucher_url' => $voucher['url'],
            'voucher_download_url' => $voucher['download_url'],
            'voucher_preview_url' => $voucher['preview_url'],
            'voucher_status' => $voucher['status'],
            'voucher_type' => $voucher['type'],
            'voucher_message' => $voucher['message'],
            'voucher_name' => $voucher['name'],
            'receipt_number' => $share->receipt_number ?: $share->receipt?->receipt_number,
            'receipt_url' => route('admin.acciones.receipt', $share),
            'cash_movements' => $cashMovements,
            'cash_movement' => $cashMovements->first(),
            'observation' => $share->observation,
            'status' => $share->status,
            'status_label' => $this->statusLabel($share->status),
            'created_at' => optional($share->created_at)->format('d/m/Y H:i'),
            'created_by_name' => $share->creator?->name,
            'updated_at' => optional($share->updated_at)->format('d/m/Y H:i'),
            'updated_by_name' => $share->updater?->name,
            'annulled_at' => optional($share->annulled_at)->format('d/m/Y H:i'),
            'annulled_by_name' => $share->annuller?->name,
        ];
    }

    private function voucherPayload(MemberShare $share): array
    {
        if (blank($share->voucher_path)) {
            return [
                'status' => 'missing',
                'type' => 'none',
                'message' => 'Sin comprobante registrado',
                'url' => null,
                'download_url' => null,
                'preview_url' => null,
                'name' => null,
            ];
        }

        $extension = strtolower(pathinfo($share->voucher_path, PATHINFO_EXTENSION));
        $type = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            ? 'image'
            : ($extension === 'pdf' ? 'pdf' : 'unknown');

        if (! Storage::disk('public')->exists($share->voucher_path)) {
            return [
                'status' => 'not_found',
                'type' => $type,
                'message' => 'Comprobante no encontrado',
                'url' => null,
                'download_url' => null,
                'preview_url' => null,
                'name' => basename($share->voucher_path),
            ];
        }

        return [
            'status' => 'available',
            'type' => $type,
            'message' => $type === 'pdf' ? 'Comprobante PDF' : 'Ver comprobante',
            'url' => route('admin.acciones.voucher.view', $share),
            'download_url' => route('admin.acciones.voucher', $share),
            'preview_url' => $type === 'image' ? route('admin.acciones.voucher.view', $share) : null,
            'name' => basename($share->voucher_path),
        ];
    }

    private function cashMovementPayload(MemberShare $share): \Illuminate\Support\Collection
    {
        return CashMovement::query()
            ->where('related_type', MemberShare::class)
            ->where('related_id', $share->id)
            ->orderBy('id')->get()->map(fn ($movement) => [
                'movement_number' => $movement->movement_number, 'category' => $movement->category,
                'category_label' => match ($movement->category) { 'accion_socio' => 'Capital de acciones', 'solidaridad_aporte' => 'Solidaridad', 'gasto_administrativo_aporte' => 'Gasto administrativo', default => $movement->category },
                'amount' => $this->money($movement->amount), 'status' => $movement->status, 'status_label' => ucfirst($movement->status),
                'balance_after' => $movement->balance_after !== null ? $this->money($movement->balance_after) : '-',
            ]);
    }

    private function statusBadge(?string $status): string
    {
        $class = $status === 'anulado' ? 'danger' : 'success';

        return '<span class="badge badge-' . $class . '">' . e($this->statusLabel($status)) . '</span>';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'registrado' => 'Registrado',
            'anulado' => 'Anulado',
            default => 'No definido',
        };
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'efectivo' => 'Efectivo',
            'yape' => 'Yape',
            'plin' => 'Plin',
            'transferencia' => 'Transferencia',
            'otro' => 'Otro',
            default => '-',
        };
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function money(mixed $amount): string { return 'S/ ' . number_format((float) $amount, 2); }

    private function messages(): array
    {
        return [
            'member_id.required' => 'Seleccione un socio valido.',
            'member_id.exists' => 'Seleccione un socio valido.',
            'date.required' => 'La fecha del aporte es obligatoria.',
            'date.date' => 'La fecha del aporte debe ser valida.',
            'total_paid.required' => 'El monto total pagado es obligatorio.',
            'total_paid.gt' => 'El monto total pagado debe ser mayor a cero.',
            'amount.numeric' => 'El monto aportado debe ser un numero valido.',
            'amount.min' => 'El monto aportado debe ser mayor a cero.',
            'share_value.required' => 'El valor de la accion es obligatorio.',
            'share_value.numeric' => 'El valor de la accion debe ser un numero valido.',
            'share_value.min' => 'El valor de la accion debe ser mayor a cero.',
            'shares_quantity.numeric' => 'La cantidad de acciones debe ser un numero valido.',
            'shares_quantity.min' => 'La cantidad de acciones debe ser mayor a cero.',
            'payment_method.required' => 'Seleccione un metodo de pago valido.',
            'payment_method.in' => 'Seleccione un metodo de pago valido.',
            'payment_reference.required' => 'La referencia de pago es obligatoria para este método.',
            'payment_reference.max' => 'La referencia de pago no debe superar 100 caracteres.',
            'voucher_path.file' => 'El comprobante debe ser una imagen o PDF válido.',
            'voucher_path.mimes' => 'El comprobante debe ser una imagen o PDF válido.',
            'voucher_path.max' => 'El comprobante no debe superar los 4 MB.',
            'status.required' => 'Seleccione un estado valido.',
            'status.in' => 'Seleccione un estado valido.',
        ];
    }
}
