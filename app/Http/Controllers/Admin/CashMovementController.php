<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityMovement;
use App\Models\CashMovement;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\MemberAccountClosure;
use App\Models\MemberShare;
use App\Models\MemberEnrollment;
use App\Models\ProfitDistributionDetail;
use App\Models\Receipt;
use App\Models\SolidarityMovement;
use App\Services\ShareCashMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class CashMovementController extends Controller
{
    public const INCOME_CATEGORIES = [
        'inscripcion_socio' => 'Inscripcion de socio',
        'accion_socio' => 'Acción de socio',
        'solidaridad_aporte' => 'Solidaridad',
        'gasto_administrativo_aporte' => 'Fondo administrativo',
        'fondo_administrativo' => 'Fondo administrativo',
        'cobro_prestamo' => 'Cobro de prestamo',
        'mora_atraso' => 'Mora de préstamo',
        'abono_capital' => 'Abono a capital',
        'liquidacion_prestamo' => 'Liquidacion de prestamo',
        'caja_chica' => 'Caja chica',
        'solidaridad' => 'Solidaridad',
        'actividad' => 'Actividad',
        'cierre_socio' => 'Cierre de cuenta de socio',
        'otro_ingreso' => 'Otro ingreso',
    ];

    public const EXPENSE_CATEGORIES = [
        'desembolso_prestamo' => 'Desembolso de prestamo',
        'gasto_administrativo' => 'Gasto administrativo',
        'fondo_administrativo' => 'Fondo administrativo',
        'devolucion_socio' => 'Retiro de socio / Cierre de cuenta',
        'solidaridad' => 'Solidaridad',
        'actividad' => 'Actividad',
        'utilidad' => 'Utilidad',
        'caja_chica' => 'Caja chica',
        'otro_egreso' => 'Otro egreso',
    ];

    public function __construct()
    {
        $this->middleware('can:admin.caja.index')->only(['index', 'list', 'summary', 'nextCode']);
        $this->middleware('can:admin.caja.create')->only(['store']);
        $this->middleware('can:admin.caja.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.caja.show')->only(['show', 'voucher']);
        $this->middleware('can:admin.caja.anular')->only(['annul']);
        $this->middleware('can:admin.caja.delete')->only(['destroy']);
    }

    public function index()
    {
        return view('admin.cash-movements.index', [
            'nextCode' => $this->generateNextCode(),
            'incomeCategories' => self::INCOME_CATEGORIES,
            'expenseCategories' => self::EXPENSE_CATEGORIES,
        ]);
    }

    public function list(Request $request)
    {
        $movements = CashMovement::query()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->input('category')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->input('date_to')))
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        return DataTables::of($movements)
            ->addIndexColumn()
            ->editColumn('movement_date', fn (CashMovement $movement) => optional($movement->movement_date)->format('d/m/Y') ?? '-')
            ->editColumn('type', fn (CashMovement $movement) => $this->typeBadge($movement->type))
            ->editColumn('category', fn (CashMovement $movement) => $this->categoryLabel($movement->category))
            ->editColumn('payment_method', fn (CashMovement $movement) => $this->paymentMethodLabel($movement->payment_method))
            ->editColumn('amount', fn (CashMovement $movement) => 'S/ ' . number_format((float) $movement->amount, 2))
            ->editColumn('balance_after', fn (CashMovement $movement) => $movement->balance_after !== null ? 'S/ ' . number_format((float) $movement->balance_after, 2) : '-')
            ->editColumn('status', fn (CashMovement $movement) => $this->statusBadge($movement->status))
            ->addColumn('acciones', fn (CashMovement $movement) => view('admin.cash-movements.partials.acciones', compact('movement'))->render())
            ->rawColumns(['type', 'status', 'acciones'])
            ->make(true);
    }

    public function summary(Request $request)
    {
        $query = CashMovement::query()
            ->where('status', 'registrado')
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->input('date_to')));

        $income = (clone $query)->where('type', 'ingreso')->sum('amount');
        $expense = (clone $query)->where('type', 'egreso')->sum('amount');
        $monthMovements = CashMovement::query()
            ->where('status', 'registrado')
            ->whereYear('movement_date', now()->year)
            ->whereMonth('movement_date', now()->month)
            ->count();

        return response()->json([
            'balance' => number_format((float) ($income - $expense), 2),
            'income' => number_format((float) $income, 2),
            'expense' => number_format((float) $expense, 2),
            'month_movements' => $monthMovements,
        ]);
    }

    public function nextCode()
    {
        return response()->json(['code' => $this->generateNextCode()]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $movement = DB::transaction(function () use ($request, $data) {
            $data['movement_number'] = $this->generateNextCode();
            $this->normalizeMovementData($data);
            $this->ensureEnoughBalance($data);
            $this->storeVoucher($request, $data);
            $this->applyBalance($data);
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();

            $movement = CashMovement::create($data);
            $this->createOrUpdateReceipt($movement);

            return $movement;
        });

        return response()->json([
            'message' => 'Movimiento de caja registrado correctamente.',
            'id' => $movement->id,
        ]);
    }

    public function show(CashMovement $cashMovement)
    {
        return response()->json($this->movementPayload($cashMovement));
    }

    public function edit(CashMovement $cashMovement)
    {
        if ($this->isRelatedMovement($cashMovement)) {
            return response()->json([
                'message' => 'Este movimiento fue generado desde otro modulo y no puede editarse directamente desde Caja.',
            ], 422);
        }

        return response()->json($this->movementPayload($cashMovement));
    }

    public function update(Request $request, CashMovement $cashMovement)
    {
        if ($this->isRelatedMovement($cashMovement)) {
            return response()->json([
                'message' => 'Este movimiento fue generado desde otro modulo y no puede editarse directamente desde Caja.',
            ], 422);
        }

        $data = $this->validatedData($request, $cashMovement);

        DB::transaction(function () use ($request, $cashMovement, $data) {
            $this->normalizeMovementData($data);
            $this->ensureEnoughBalance($data, $cashMovement->id);
            $this->storeVoucher($request, $data, $cashMovement);
            $this->applyBalance($data, $cashMovement->id);
            unset($data['movement_number']);
            $data['updated_by'] = auth()->id();

            $cashMovement->update($data);
            $this->createOrUpdateReceipt($cashMovement->fresh());
            app(ShareCashMovementService::class)->recalculateBalances();
        });

        return response()->json(['message' => 'Movimiento de caja actualizado correctamente.']);
    }

    public function destroy(CashMovement $cashMovement)
    {
        return $this->annul($cashMovement);
    }

    public function annul(CashMovement $cashMovement)
    {
        if ($this->isRelatedMovement($cashMovement)) {
            return response()->json([
                'message' => 'Este movimiento esta relacionado con otro modulo. Debe anularse desde el modulo origen para mantener la trazabilidad.',
            ], 422);
        }

        if ($cashMovement->status === 'anulado') {
            return response()->json(['message' => 'El movimiento ya se encuentra anulado.'], 422);
        }

        $cashMovement->update([
            'status' => 'anulado',
            'balance_before' => null,
            'balance_after' => null,
            'updated_by' => auth()->id(),
            'annulled_by' => auth()->id(),
            'annulled_at' => now(),
        ]);

        Receipt::where('related_type', CashMovement::class)
            ->where('related_id', $cashMovement->id)
            ->update([
                'status' => 'anulado',
                'updated_by' => auth()->id(),
            ]);

        app(ShareCashMovementService::class)->recalculateBalances();

        return response()->json(['message' => 'Movimiento de caja anulado correctamente.']);
    }

    public function voucher(CashMovement $cashMovement)
    {
        if (! $cashMovement->voucher_path || ! Storage::disk('public')->exists($cashMovement->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return Storage::disk('public')->download($cashMovement->voucher_path);
    }

    private function validatedData(Request $request, ?CashMovement $movement = null): array
    {
        $this->normalizeNullableRequestFields($request);

        $data = $request->validate([
            'movement_date' => ['required', 'date'],
            'type' => ['required', Rule::in(['ingreso', 'egreso'])],
            'category' => ['required', 'string', 'max:100'],
            'concept' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['efectivo', 'yape', 'plin', 'transferencia', 'otro'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'voucher_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'observation' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['registrado', 'anulado'])],
        ], $this->messages());

        $allowed = $data['type'] === 'ingreso' ? array_keys(self::INCOME_CATEGORIES) : array_keys(self::EXPENSE_CATEGORIES);

        if (! in_array($data['category'], $allowed, true)) {
            throw ValidationException::withMessages([
                'category' => ['Seleccione una categoria valida.'],
            ]);
        }

        return $data;
    }

    private function normalizeNullableRequestFields(Request $request): void
    {
        $fields = ['reference', 'observation'];
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

    private function normalizeMovementData(array &$data): void
    {
        $data['amount'] = (float) $data['amount'];
        $data['status'] = $data['status'] ?? 'registrado';
    }

    private function ensureEnoughBalance(array $data, ?int $excludeId = null): void
    {
        if (($data['status'] ?? 'registrado') !== 'registrado' || $data['type'] !== 'egreso') {
            return;
        }

        if ($this->currentBalance($excludeId) < (float) $data['amount']) {
            throw ValidationException::withMessages([
                'amount' => ['No hay saldo suficiente en caja para registrar este egreso.'],
            ]);
        }
    }

    private function applyBalance(array &$data, ?int $excludeId = null): void
    {
        $balanceBefore = $this->currentBalance($excludeId);
        $amount = (float) $data['amount'];
        $balanceAfter = $data['status'] === 'registrado'
            ? ($data['type'] === 'ingreso' ? $balanceBefore + $amount : $balanceBefore - $amount)
            : $balanceBefore;

        $data['balance_before'] = $balanceBefore;
        $data['balance_after'] = $balanceAfter;
    }

    private function currentBalance(?int $excludeId = null): float
    {
        $query = CashMovement::query()->where('status', 'registrado');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $income = (clone $query)->where('type', 'ingreso')->sum('amount');
        $expense = (clone $query)->where('type', 'egreso')->sum('amount');

        return (float) $income - (float) $expense;
    }

    private function storeVoucher(Request $request, array &$data, ?CashMovement $movement = null): void
    {
        if (! $request->hasFile('voucher_path')) {
            unset($data['voucher_path']);
            return;
        }

        if ($movement?->voucher_path) {
            Storage::disk('public')->delete($movement->voucher_path);
        }

        $data['voucher_path'] = $request->file('voucher_path')->store('cash-movements', 'public');
    }

    private function generateNextCode(): string
    {
        return CashMovement::nextCode();
    }

    private function generateNextReceiptNumber(): string
    {
        $lastCode = Receipt::withTrashed()
            ->whereNotNull('receipt_number')
            ->where('receipt_number', 'like', 'REC-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('receipt_number');

        $lastNumber = 0;

        if ($lastCode && preg_match('/REC-(\d+)/', $lastCode, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        return 'REC-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function createOrUpdateReceipt(CashMovement $movement): Receipt
    {
        $receipt = Receipt::firstOrNew([
            'related_type' => CashMovement::class,
            'related_id' => $movement->id,
        ]);

        if (! $receipt->exists) {
            $receipt->receipt_number = $this->generateNextReceiptNumber();
            $receipt->created_by = $movement->created_by ?: auth()->id();
        }

        $receipt->fill([
            'receipt_date' => $movement->movement_date,
            'member_id' => null,
            'type' => 'caja',
            'amount' => $movement->amount,
            'payment_method' => $movement->payment_method,
            'payment_reference' => $movement->reference,
            'voucher_path' => $movement->voucher_path,
            'observation' => $movement->concept,
            'status' => $movement->status,
            'updated_by' => auth()->id(),
        ]);

        $receipt->save();

        return $receipt;
    }

    private function movementPayload(CashMovement $movement): array
    {
        $movement->load(['creator', 'updater', 'annuller']);
        $voucher = $this->voucherPayload($movement);

        return [
            'id' => $movement->id,
            'movement_number' => $movement->movement_number,
            'movement_date' => optional($movement->movement_date)->format('Y-m-d'),
            'movement_date_formatted' => optional($movement->movement_date)->format('d/m/Y'),
            'type' => $movement->type,
            'type_label' => $this->typeLabel($movement->type),
            'category' => $movement->category,
            'category_label' => $this->categoryLabel($movement->category),
            'concept' => $movement->concept,
            'amount' => number_format((float) $movement->amount, 2, '.', ''),
            'amount_formatted' => 'S/ ' . number_format((float) $movement->amount, 2),
            'payment_method' => $movement->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($movement->payment_method),
            'reference' => $movement->reference,
            'reference_display' => $movement->payment_method === 'efectivo' ? 'No aplica' : ($movement->reference ?: '-'),
            'voucher_path' => $movement->voucher_path,
            'voucher_url' => $voucher['url'],
            'voucher_status' => $voucher['status'],
            'voucher_message' => $voucher['message'],
            'balance_before' => $movement->balance_before !== null ? number_format((float) $movement->balance_before, 2, '.', '') : null,
            'balance_before_formatted' => $movement->balance_before !== null ? 'S/ ' . number_format((float) $movement->balance_before, 2) : '-',
            'balance_after' => $movement->balance_after !== null ? number_format((float) $movement->balance_after, 2, '.', '') : null,
            'balance_after_formatted' => $movement->balance_after !== null ? 'S/ ' . number_format((float) $movement->balance_after, 2) : '-',
            'observation' => $movement->observation,
            'status' => $movement->status,
            'status_label' => $this->statusLabel($movement->status),
            'created_at' => optional($movement->created_at)->format('d/m/Y H:i'),
            'created_by_name' => $movement->creator?->name,
            'updated_at' => optional($movement->updated_at)->format('d/m/Y H:i'),
            'updated_by_name' => $movement->updater?->name,
            'annulled_at' => optional($movement->annulled_at)->format('d/m/Y H:i'),
            'annulled_by_name' => $movement->annuller?->name,
            'is_related' => $this->isRelatedMovement($movement),
            'origin' => $this->originPayload($movement),
        ];
    }

    public function isRelatedForView(CashMovement $movement): bool
    {
        return $this->isRelatedMovement($movement);
    }

    public function voucherStateForView(CashMovement $movement): array
    {
        return $this->voucherPayload($movement);
    }

    private function isRelatedMovement(CashMovement $movement): bool
    {
        return filled($movement->related_type) && filled($movement->related_id);
    }

    private function voucherPayload(CashMovement $movement): array
    {
        if (blank($movement->voucher_path)) {
            return [
                'status' => 'missing',
                'message' => 'Sin comprobante registrado',
                'url' => null,
            ];
        }

        if (! Storage::disk('public')->exists($movement->voucher_path)) {
            return [
                'status' => 'not_found',
                'message' => 'Comprobante no encontrado',
                'url' => null,
            ];
        }

        return [
            'status' => 'available',
            'message' => 'Ver comprobante',
            'url' => route('admin.caja.voucher', $movement),
        ];
    }

    private function originPayload(CashMovement $movement): array
    {
        if (! $this->isRelatedMovement($movement)) {
            return [
                'type' => 'Movimiento manual de caja',
                'module' => 'Caja',
                'code' => 'No aplica',
                'member' => '-',
                'loan' => '-',
                'url' => null,
                'technical_relation' => '-',
                'found' => true,
            ];
        }

        $related = $movement->related;

        if (! $related) {
            return [
                'type' => 'Registro relacionado no encontrado',
                'module' => '-',
                'code' => 'No disponible',
                'member' => '-',
                'loan' => '-',
                'url' => null,
                'technical_relation' => $this->technicalRelation($movement),
                'found' => false,
            ];
        }

        return match (true) {
            $related instanceof MemberShare => $this->originFromMemberShare($movement, $related),
            $related instanceof MemberEnrollment => $this->originFromEnrollment($movement, $related),
            $related instanceof Loan => $this->originFromLoan($movement, $related),
            $related instanceof LoanPayment => $this->originFromLoanPayment($movement, $related),
            $related instanceof SolidarityMovement => $this->originFromSolidarity($movement, $related),
            $related instanceof ActivityMovement => $this->originFromActivity($movement, $related),
            $related instanceof ProfitDistributionDetail => $this->originFromProfit($movement, $related),
            $related instanceof MemberAccountClosure => $this->originFromClosure($movement, $related),
            default => [
                'type' => class_basename($movement->related_type),
                'module' => 'Modulo relacionado',
                'code' => $this->relatedCode($related),
                'member' => $this->relatedMemberName($related),
                'loan' => '-',
                'url' => null,
                'technical_relation' => $this->technicalRelation($movement),
                'found' => true,
            ],
        };
    }

    private function originFromMemberShare(CashMovement $movement, MemberShare $share): array
    {
        $share->loadMissing('member');

        return [
            'type' => 'Aporte de acciones',
            'module' => 'Acciones / Aportes',
            'code' => $share->code ?: '-',
            'member' => $share->member?->full_name ?: '-',
            'loan' => '-',
            'url' => route('admin.acciones.show', $share),
            'technical_relation' => $this->technicalRelation($movement),
            'found' => true,
        ];
    }

    private function originFromEnrollment(CashMovement $movement, MemberEnrollment $enrollment): array
    {
        $enrollment->loadMissing('member');
        return [
            'type' => 'Inscripcion de socio', 'module' => 'Socios / Inscripciones', 'code' => $enrollment->code,
            'member' => $enrollment->member?->full_name ?? '-', 'loan' => '-', 'url' => null,
            'technical_relation' => $this->technicalRelation($movement), 'found' => true,
        ];
    }

    private function originFromLoan(CashMovement $movement, Loan $loan): array
    {
        $loan->loadMissing('member');

        return [
            'type' => 'Desembolso de prestamo',
            'module' => 'Prestamos',
            'code' => $loan->loan_number ?: '-',
            'member' => $loan->member?->full_name ?: '-',
            'loan' => $loan->loan_number ?: '-',
            'url' => route('admin.prestamos.show', $loan),
            'technical_relation' => $this->technicalRelation($movement),
            'found' => true,
        ];
    }

    private function originFromLoanPayment(CashMovement $movement, LoanPayment $payment): array
    {
        $payment->loadMissing(['member', 'loan']);

        return [
            'type' => 'Cobro de prestamo',
            'module' => 'Cobros',
            'code' => $payment->payment_number ?: '-',
            'member' => $payment->member?->full_name ?: '-',
            'loan' => $payment->loan?->loan_number ?: '-',
            'financial_breakdown' => [
                'capital' => 'S/ ' . number_format((float) $payment->capital_amount, 2),
                'interest' => 'S/ ' . number_format((float) $payment->interest_amount, 2),
                'late_fee' => 'S/ ' . number_format((float) $payment->late_fee_paid, 2),
                'late_fee_waived' => 'S/ ' . number_format((float) $payment->late_fee_waived, 2),
                'total' => 'S/ ' . number_format((float) $payment->amount, 2),
            ],
            'url' => route('admin.cobros.show', $payment),
            'technical_relation' => $this->technicalRelation($movement),
            'found' => true,
        ];
    }

    private function originFromSolidarity(CashMovement $movement, SolidarityMovement $solidarity): array
    {
        $solidarity->loadMissing('member');

        return [
            'type' => 'Solidaridad',
            'module' => 'Solidaridad',
            'code' => $solidarity->code ?: '-',
            'member' => $solidarity->member?->full_name ?: '-',
            'loan' => '-',
            'url' => route('admin.solidaridad.show', $solidarity),
            'technical_relation' => $this->technicalRelation($movement),
            'found' => true,
        ];
    }

    private function originFromActivity(CashMovement $movement, ActivityMovement $activityMovement): array
    {
        $activityMovement->loadMissing(['activity', 'member']);

        return [
            'type' => 'Actividad',
            'module' => 'Actividades',
            'code' => $activityMovement->code ?: '-',
            'member' => $activityMovement->member?->full_name ?: '-',
            'loan' => '-',
            'url' => route('admin.actividades.movimientos.show', $activityMovement),
            'technical_relation' => $this->technicalRelation($movement),
            'found' => true,
        ];
    }

    private function originFromProfit(CashMovement $movement, ProfitDistributionDetail $detail): array
    {
        $detail->loadMissing(['member', 'distribution']);

        return [
            'type' => 'Pago de utilidad',
            'module' => 'Utilidades',
            'code' => $detail->distribution?->code ?: 'Detalle #' . $detail->id,
            'member' => $detail->member?->full_name ?: '-',
            'loan' => '-',
            'url' => $detail->distribution ? route('admin.utilidades.show', $detail->distribution) : null,
            'technical_relation' => $this->technicalRelation($movement),
            'found' => true,
        ];
    }

    private function originFromClosure(CashMovement $movement, MemberAccountClosure $closure): array
    {
        $closure->loadMissing('member');

        return [
            'type' => 'Retiro / cierre de socio',
            'module' => 'Retiro de socios',
            'code' => $closure->code ?: '-',
            'member' => $closure->member?->full_name ?: '-',
            'loan' => '-',
            'url' => route('admin.retiros-socios.show', $closure),
            'technical_relation' => $this->technicalRelation($movement),
            'found' => true,
        ];
    }

    private function relatedCode(object $related): string
    {
        return $related->code
            ?? $related->loan_number
            ?? $related->payment_number
            ?? $related->movement_number
            ?? ('#' . ($related->id ?? '-'));
    }

    private function relatedMemberName(object $related): string
    {
        if (method_exists($related, 'loadMissing')) {
            $related->loadMissing('member');
        }

        return $related->member?->full_name ?? '-';
    }

    private function technicalRelation(CashMovement $movement): string
    {
        return ($movement->related_type ?: '-') . ' #' . ($movement->related_id ?: '-');
    }

    private function typeBadge(?string $type): string
    {
        $class = $type === 'egreso' ? 'danger' : 'success';

        return '<span class="badge badge-' . $class . '">' . e($this->typeLabel($type)) . '</span>';
    }

    private function statusBadge(?string $status): string
    {
        $class = $status === 'anulado' ? 'danger' : 'success';

        return '<span class="badge badge-' . $class . '">' . e($this->statusLabel($status)) . '</span>';
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'ingreso' => 'Ingreso',
            'egreso' => 'Egreso',
            default => '-',
        };
    }

    private function categoryLabel(?string $category): string
    {
        return self::INCOME_CATEGORIES[$category]
            ?? self::EXPENSE_CATEGORIES[$category]
            ?? '-';
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

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'registrado' => 'Registrado',
            'anulado' => 'Anulado',
            default => '-',
        };
    }

    private function messages(): array
    {
        return [
            'movement_date.required' => 'La fecha del movimiento es obligatoria.',
            'movement_date.date' => 'La fecha del movimiento debe ser valida.',
            'type.required' => 'Seleccione un tipo de movimiento valido.',
            'type.in' => 'Seleccione un tipo de movimiento valido.',
            'category.required' => 'Seleccione una categoria valida.',
            'concept.required' => 'El concepto del movimiento es obligatorio.',
            'concept.max' => 'El concepto no debe superar 255 caracteres.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un numero valido.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'payment_method.required' => 'Seleccione un metodo de pago valido.',
            'payment_method.in' => 'Seleccione un metodo de pago valido.',
            'reference.max' => 'La referencia no debe superar 100 caracteres.',
            'voucher_path.file' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.mimes' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.max' => 'El comprobante no debe superar los 4 MB.',
            'status.required' => 'Seleccione un estado valido.',
            'status.in' => 'Seleccione un estado valido.',
        ];
    }
}
