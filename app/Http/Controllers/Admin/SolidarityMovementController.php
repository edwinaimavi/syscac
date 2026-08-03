<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Member;
use App\Models\Receipt;
use App\Models\SolidarityMovement;
use App\Services\ShareCashMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class SolidarityMovementController extends Controller
{
    private const PAYMENT_METHODS = ['efectivo', 'yape', 'plin', 'transferencia', 'otro'];

    public function __construct()
    {
        $this->middleware('can:admin.solidaridad.index')->only(['index', 'list', 'summary', 'nextCode']);
        $this->middleware('can:admin.solidaridad.create')->only(['store']);
        $this->middleware('can:admin.solidaridad.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.solidaridad.show')->only(['show']);
        $this->middleware('can:admin.solidaridad.anular')->only(['annul', 'destroy']);
        $this->middleware('can:admin.solidaridad.receipt')->only(['receipt']);
        $this->middleware('can:admin.solidaridad.receipt_pdf')->only(['receiptPdf']);
        $this->middleware('can:admin.solidaridad.voucher')->only(['voucher']);
    }

    public function index()
    {
        return view('admin.solidarity-movements.index', [
            'members' => Member::where('status', 'vigente')->whereNull('retirement_date')->orderBy('full_name')->get(['id', 'code', 'dni', 'full_name']),
            'nextCode' => SolidarityMovement::nextCode(),
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function list(Request $request)
    {
        $movements = SolidarityMovement::with(['member'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->input('payment_method')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->input('date_to')))
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        return DataTables::of($movements)
            ->addIndexColumn()
            ->editColumn('movement_date', fn (SolidarityMovement $movement) => optional($this->movementDate($movement))->format('d/m/Y') ?? '-')
            ->editColumn('type', fn (SolidarityMovement $movement) => $this->typeBadge($movement->type))
            ->addColumn('member_name', fn (SolidarityMovement $movement) => $movement->member?->full_name ?? '-')
            ->editColumn('payment_method', fn (SolidarityMovement $movement) => $this->paymentMethodLabel($movement->payment_method))
            ->editColumn('amount', fn (SolidarityMovement $movement) => $this->money($movement->amount))
            ->editColumn('status', fn (SolidarityMovement $movement) => $this->statusBadge($movement->status))
            ->addColumn('acciones', fn (SolidarityMovement $movement) => view('admin.solidarity-movements.partials.acciones', compact('movement'))->render())
            ->rawColumns(['type', 'status', 'acciones'])
            ->make(true);
    }

    public function summary(Request $request)
    {
        $query = SolidarityMovement::query()
            ->where('status', 'registrado')
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->input('date_to')));

        $income = (clone $query)->where('type', 'ingreso')->sum('amount');
        $expense = (clone $query)->where('type', 'egreso')->sum('amount');
        $monthMovements = SolidarityMovement::query()
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
        return response()->json(['code' => SolidarityMovement::nextCode()]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $movement = DB::transaction(function () use ($request, $data) {
            $this->normalizeMovementData($data);
            $this->ensureBalances($data);
            $this->storeVoucher($request, $data);

            $movement = SolidarityMovement::create($data + [
                'code' => SolidarityMovement::nextCode(),
                'date' => $data['movement_date'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncCashMovement($movement);
            $receipt = $this->createOrUpdateReceipt($movement);
            $movement->update(['receipt_id' => $receipt->id]);

            return $movement;
        });

        return response()->json([
            'message' => 'Movimiento de solidaridad registrado correctamente.',
            'id' => $movement->id,
        ]);
    }

    public function show(SolidarityMovement $solidaridad)
    {
        return response()->json($this->movementPayload($solidaridad));
    }

    public function edit(SolidarityMovement $solidaridad)
    {
        if ($solidaridad->source_type) {
            return response()->json(['message' => 'Este movimiento se administra desde el aporte de origen.'], 422);
        }
        return response()->json($this->movementPayload($solidaridad));
    }

    public function update(Request $request, SolidarityMovement $solidaridad)
    {
        if ($solidaridad->status === 'anulado') {
            return response()->json(['message' => 'No se puede editar un movimiento anulado.'], 422);
        }
        if ($solidaridad->source_type) {
            return response()->json(['message' => 'Este movimiento se administra desde el aporte de origen.'], 422);
        }

        $data = $this->validatedData($request, $solidaridad);

        DB::transaction(function () use ($request, $solidaridad, $data) {
            $this->normalizeMovementData($data);
            $this->ensureBalances($data, $solidaridad);
            $this->storeVoucher($request, $data, $solidaridad);

            $solidaridad->update($data + [
                'date' => $data['movement_date'],
                'updated_by' => auth()->id(),
            ]);

            $this->syncCashMovement($solidaridad->fresh(['member']));
            $receipt = $this->createOrUpdateReceipt($solidaridad->fresh(['member']));
            $solidaridad->update(['receipt_id' => $receipt->id]);
        });

        return response()->json(['message' => 'Movimiento de solidaridad actualizado correctamente.']);
    }

    public function destroy(SolidarityMovement $solidaridad)
    {
        return $this->annul($solidaridad);
    }

    public function annul(SolidarityMovement $solidaridad)
    {
        if ($solidaridad->status === 'anulado') {
            return response()->json(['message' => 'El movimiento ya se encuentra anulado.'], 422);
        }
        if ($solidaridad->source_type) {
            return response()->json(['message' => 'Anule el aporte de origen para mantener la trazabilidad de Caja.'], 422);
        }

        DB::transaction(function () use ($solidaridad) {
            $solidaridad->update([
                'status' => 'anulado',
                'updated_by' => auth()->id(),
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
            ]);

            CashMovement::where('related_type', SolidarityMovement::class)
                ->where('related_id', $solidaridad->id)
                ->update([
                    'status' => 'anulado',
                    'balance_before' => null,
                    'balance_after' => null,
                    'updated_by' => auth()->id(),
                    'annulled_by' => auth()->id(),
                    'annulled_at' => now(),
                ]);

            Receipt::where('related_type', SolidarityMovement::class)
                ->where('related_id', $solidaridad->id)
                ->update([
                    'status' => 'anulado',
                    'updated_by' => auth()->id(),
                ]);

            app(ShareCashMovementService::class)->recalculateBalances();
        });

        return response()->json(['message' => 'Movimiento de solidaridad anulado correctamente.']);
    }

    public function receipt(SolidarityMovement $solidaridad)
    {
        $receipt = $solidaridad->receipt ?: Receipt::where('related_type', SolidarityMovement::class)->where('related_id', $solidaridad->id)->firstOrFail();

        return redirect()->route('admin.recibos.print', $receipt);
    }

    public function receiptPdf(SolidarityMovement $solidaridad)
    {
        $receipt = $solidaridad->receipt ?: Receipt::where('related_type', SolidarityMovement::class)->where('related_id', $solidaridad->id)->firstOrFail();

        return redirect()->route('admin.recibos.pdf', $receipt);
    }

    public function voucher(SolidarityMovement $solidaridad)
    {
        if (! $solidaridad->voucher_path || ! Storage::disk('public')->exists($solidaridad->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return Storage::disk('public')->download($solidaridad->voucher_path);
    }

    private function validatedData(Request $request, ?SolidarityMovement $movement = null): array
    {
        $this->normalizeNullableRequestFields($request);

        $data = $request->validate([
            'movement_date' => ['required', 'date'],
            'type' => ['required', Rule::in(['ingreso', 'egreso'])],
            'member_id' => ['nullable', Rule::exists('members', 'id')->where(function ($query) use ($request, $movement) {
                if ($movement && (int) $request->input('member_id') === (int) $movement->member_id) return;
                $query->where('status', 'vigente')->whereNull('retirement_date');
            })],
            'concept' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'voucher_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'observation' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['registrado', 'anulado'])],
        ], $this->messages());

        if (in_array($data['payment_method'], ['yape', 'plin', 'transferencia'], true) && blank($data['payment_reference'] ?? null)) {
            throw ValidationException::withMessages([
                'payment_reference' => ['La referencia de pago es obligatoria para este metodo de pago.'],
            ]);
        }

        return $data;
    }

    private function normalizeNullableRequestFields(Request $request): void
    {
        $fields = ['member_id', 'payment_reference', 'observation'];
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
        $data['amount'] = round((float) $data['amount'], 2);
        $data['status'] = $data['status'] ?? 'registrado';
    }

    private function ensureBalances(array $data, ?SolidarityMovement $movement = null): void
    {
        if (($data['status'] ?? 'registrado') !== 'registrado' || $data['type'] !== 'egreso') {
            return;
        }

        $cashMovementId = $movement
            ? CashMovement::where('related_type', SolidarityMovement::class)->where('related_id', $movement->id)->value('id')
            : null;

        if ($this->currentCashBalance($cashMovementId) < (float) $data['amount']) {
            throw ValidationException::withMessages([
                'amount' => ['No hay saldo suficiente en caja para registrar este egreso.'],
            ]);
        }

        if ($this->currentSolidarityBalance($movement?->id) < (float) $data['amount']) {
            throw ValidationException::withMessages([
                'amount' => ['No hay saldo suficiente en el fondo solidario.'],
            ]);
        }
    }

    private function currentCashBalance(?int $excludeId = null): float
    {
        $query = CashMovement::where('status', 'registrado');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $income = (clone $query)->where('type', 'ingreso')->sum('amount');
        $expense = (clone $query)->where('type', 'egreso')->sum('amount');

        return (float) $income - (float) $expense;
    }

    private function currentSolidarityBalance(?int $excludeId = null): float
    {
        $query = SolidarityMovement::where('status', 'registrado');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $income = (clone $query)->where('type', 'ingreso')->sum('amount');
        $expense = (clone $query)->where('type', 'egreso')->sum('amount');

        return (float) $income - (float) $expense;
    }

    private function storeVoucher(Request $request, array &$data, ?SolidarityMovement $movement = null): void
    {
        if (! $request->hasFile('voucher_path')) {
            unset($data['voucher_path']);
            return;
        }

        if ($movement?->voucher_path) {
            Storage::disk('public')->delete($movement->voucher_path);
        }

        $data['voucher_path'] = $request->file('voucher_path')->store('solidarity', 'public');
    }

    private function syncCashMovement(SolidarityMovement $movement): CashMovement
    {
        $cashMovement = CashMovement::where('related_type', SolidarityMovement::class)
            ->where('related_id', $movement->id)
            ->lockForUpdate()
            ->first();

        $cashMovement ??= new CashMovement([
            'movement_number' => CashMovement::nextCode(),
            'created_by' => $movement->created_by ?: auth()->id(),
        ]);

        $cashMovement->fill([
            'movement_date' => $this->movementDate($movement),
            'type' => $movement->type,
            'category' => 'solidaridad',
            'concept' => 'Movimiento de solidaridad: ' . $movement->concept,
            'amount' => $movement->amount,
            'payment_method' => $movement->payment_method,
            'reference' => $movement->payment_reference,
            'voucher_path' => $movement->voucher_path,
            'related_type' => SolidarityMovement::class,
            'related_id' => $movement->id,
            'observation' => $movement->observation,
            'status' => $movement->status,
            'updated_by' => auth()->id(),
            'annulled_by' => $movement->annulled_by,
            'annulled_at' => $movement->annulled_at,
        ]);

        $cashMovement->save();
        if ((int) $movement->cash_movement_id !== (int) $cashMovement->id) {
            $movement->forceFill(['cash_movement_id' => $cashMovement->id])->save();
        }
        app(ShareCashMovementService::class)->recalculateBalances();

        return $cashMovement->fresh();
    }

    private function createOrUpdateReceipt(SolidarityMovement $movement): Receipt
    {
        $receipt = Receipt::firstOrNew([
            'related_type' => SolidarityMovement::class,
            'related_id' => $movement->id,
        ]);

        if (! $receipt->exists) {
            $receipt->receipt_number = $this->generateNextReceiptNumber();
            $receipt->created_by = $movement->created_by ?: auth()->id();
        }

        $receipt->fill([
            'receipt_date' => $this->movementDate($movement),
            'member_id' => $movement->member_id,
            'type' => 'solidaridad',
            'amount' => $movement->amount,
            'payment_method' => $movement->payment_method,
            'payment_reference' => $movement->payment_reference,
            'voucher_path' => $movement->voucher_path,
            'observation' => $movement->concept,
            'status' => $movement->status,
            'updated_by' => auth()->id(),
        ]);

        $receipt->save();

        return $receipt;
    }

    private function generateNextReceiptNumber(): string
    {
        $lastCode = Receipt::withTrashed()
            ->whereNotNull('receipt_number')
            ->where('receipt_number', 'like', 'REC-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('receipt_number');

        $lastNumber = $lastCode && preg_match('/REC-(\d+)/', $lastCode, $matches) ? (int) $matches[1] : 0;

        return 'REC-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function movementPayload(SolidarityMovement $movement): array
    {
        $movement->load(['member', 'receipt', 'creator', 'updater', 'annuller', 'cashMovement']);

        return [
            'id' => $movement->id,
            'code' => $movement->code,
            'movement_date' => optional($this->movementDate($movement))->format('Y-m-d'),
            'movement_date_formatted' => optional($this->movementDate($movement))->format('d/m/Y'),
            'type' => $movement->type,
            'type_label' => $this->typeLabel($movement->type),
            'member_id' => $movement->member_id,
            'member_name' => $movement->member?->full_name ?? '-',
            'member_dni' => $movement->member?->dni ?? '-',
            'concept' => $movement->concept,
            'amount' => number_format((float) $movement->amount, 2, '.', ''),
            'amount_formatted' => $this->money($movement->amount),
            'impact_label' => $movement->type === 'egreso' ? 'Disminuye el fondo solidario' : 'Aumenta el fondo solidario',
            'payment_method' => $movement->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($movement->payment_method),
            'payment_reference' => $movement->payment_reference,
            'voucher_path' => $movement->voucher_path,
            'voucher_url' => $movement->voucher_path ? Storage::url($movement->voucher_path) : null,
            'voucher_download_url' => $movement->voucher_path ? route('admin.solidaridad.voucher', $movement) : null,
            'receipt_number' => $movement->receipt?->receipt_number,
            'receipt_url' => $movement->receipt ? route('admin.solidaridad.receipt', $movement) : null,
            'receipt_pdf_url' => $movement->receipt ? route('admin.solidaridad.receipt.pdf', $movement) : null,
            'cash_movement_number' => $movement->cashMovement?->movement_number,
            'observation' => $movement->observation,
            'status' => $movement->status,
            'status_label' => $this->statusLabel($movement->status),
            'created_at' => optional($movement->created_at)->format('d/m/Y H:i'),
            'created_by_name' => $movement->creator?->name,
            'updated_at' => optional($movement->updated_at)->format('d/m/Y H:i'),
            'updated_by_name' => $movement->updater?->name,
            'annulled_at' => optional($movement->annulled_at)->format('d/m/Y H:i'),
            'annulled_by_name' => $movement->annuller?->name,
        ];
    }

    private function movementDate(SolidarityMovement $movement)
    {
        return $movement->movement_date ?: $movement->date;
    }

    private function typeBadge(?string $type): string
    {
        return '<span class="badge badge-' . ($type === 'egreso' ? 'danger' : 'success') . '">' . e($this->typeLabel($type)) . '</span>';
    }

    private function statusBadge(?string $status): string
    {
        return '<span class="badge badge-' . ($status === 'anulado' ? 'danger' : 'success') . '">' . e($this->statusLabel($status)) . '</span>';
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'ingreso' => 'Ingreso',
            'egreso' => 'Egreso',
            default => '-',
        };
    }

    private function paymentMethods(): array
    {
        return [
            'efectivo' => 'Efectivo',
            'yape' => 'Yape',
            'plin' => 'Plin',
            'transferencia' => 'Transferencia',
            'otro' => 'Otro',
        ];
    }

    private function paymentMethodLabel(?string $method): string
    {
        return $this->paymentMethods()[$method] ?? '-';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'registrado' => 'Registrado',
            'anulado' => 'Anulado',
            default => '-',
        };
    }

    private function money(mixed $amount): string
    {
        return 'S/ ' . number_format((float) $amount, 2);
    }

    private function messages(): array
    {
        return [
            'movement_date.required' => 'La fecha del movimiento es obligatoria.',
            'movement_date.date' => 'La fecha del movimiento debe ser valida.',
            'type.required' => 'Seleccione un tipo de movimiento valido.',
            'type.in' => 'Seleccione un tipo de movimiento valido.',
            'member_id.exists' => 'Este socio se encuentra retirado y no puede realizar nuevas operaciones.',
            'concept.required' => 'El concepto es obligatorio.',
            'concept.max' => 'El concepto no debe superar 255 caracteres.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un numero valido.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'payment_method.required' => 'Seleccione un metodo de pago valido.',
            'payment_method.in' => 'Seleccione un metodo de pago valido.',
            'payment_reference.max' => 'La referencia no debe superar 100 caracteres.',
            'voucher_path.file' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.mimes' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.max' => 'El comprobante no debe superar los 4 MB.',
            'status.required' => 'Seleccione un estado valido.',
            'status.in' => 'Seleccione un estado valido.',
        ];
    }
}
