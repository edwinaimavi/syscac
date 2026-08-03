<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityMovement;
use App\Models\CashMovement;
use App\Models\Receipt;
use App\Services\ShareCashMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivityMovementController extends Controller
{
    private const PAYMENT_METHODS = ['efectivo', 'yape', 'plin', 'transferencia', 'otro'];

    public function __construct()
    {
        $this->middleware('can:admin.actividades.movements')->only(['listByActivity', 'nextCode']);
        $this->middleware('can:admin.actividades.movement_create')->only(['store']);
        $this->middleware('can:admin.actividades.movement_edit')->only(['edit', 'update']);
        $this->middleware('can:admin.actividades.movement_show')->only(['show']);
        $this->middleware('can:admin.actividades.movement_anular')->only(['annul']);
        $this->middleware('can:admin.actividades.receipt')->only(['receipt']);
        $this->middleware('can:admin.actividades.receipt_pdf')->only(['receiptPdf']);
        $this->middleware('can:admin.actividades.voucher')->only(['voucher']);
    }

    public function listByActivity(Activity $activity)
    {
        $activity->load(['movements.member']);

        return response()->json($activity->movements->sortByDesc('movement_date')->map(fn (ActivityMovement $movement) => $this->movementPayload($movement))->values());
    }

    public function nextCode(Activity $activity)
    {
        return response()->json(['code' => ActivityMovement::nextCode()]);
    }

    public function store(Request $request, Activity $activity)
    {
        $this->ensureActivityOpen($activity);
        $data = $this->validatedData($request);

        $movement = DB::transaction(function () use ($request, $activity, $data) {
            $this->normalizeMovementData($data);
            $this->ensureCashBalance($data);
            $this->storeVoucher($request, $data);

            $movement = ActivityMovement::create($data + [
                'code' => ActivityMovement::nextCode(),
                'activity_id' => $activity->id,
                'date' => $data['movement_date'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncCashMovement($movement);
            $receipt = $this->createOrUpdateReceipt($movement);
            $movement->update(['receipt_id' => $receipt->id]);
            $this->recalculateActivityTotals($activity);

            return $movement;
        });

        return response()->json(['message' => 'Movimiento de actividad registrado correctamente.', 'id' => $movement->id]);
    }

    public function show(ActivityMovement $activityMovement)
    {
        return response()->json($this->movementPayload($activityMovement));
    }

    public function edit(ActivityMovement $activityMovement)
    {
        return response()->json($this->movementPayload($activityMovement));
    }

    public function update(Request $request, ActivityMovement $activityMovement)
    {
        if ($activityMovement->status === 'anulado') {
            return response()->json(['message' => 'No se puede editar un movimiento anulado.'], 422);
        }

        $activityMovement->load('activity');
        $this->ensureActivityOpen($activityMovement->activity);
        $data = $this->validatedData($request, $activityMovement);

        DB::transaction(function () use ($request, $activityMovement, $data) {
            $this->normalizeMovementData($data);
            $this->ensureCashBalance($data, $activityMovement);
            $this->storeVoucher($request, $data, $activityMovement);

            $activityMovement->update($data + [
                'date' => $data['movement_date'],
                'updated_by' => auth()->id(),
            ]);

            $this->syncCashMovement($activityMovement->fresh(['activity']));
            $receipt = $this->createOrUpdateReceipt($activityMovement->fresh(['activity']));
            $activityMovement->update(['receipt_id' => $receipt->id]);
            $this->recalculateActivityTotals($activityMovement->activity);
        });

        return response()->json(['message' => 'Movimiento de actividad actualizado correctamente.']);
    }

    public function annul(ActivityMovement $activityMovement)
    {
        if ($activityMovement->status === 'anulado') {
            return response()->json(['message' => 'El movimiento ya se encuentra anulado.'], 422);
        }

        DB::transaction(function () use ($activityMovement) {
            $activityMovement->update([
                'status' => 'anulado',
                'updated_by' => auth()->id(),
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
            ]);

            CashMovement::where('related_type', ActivityMovement::class)->where('related_id', $activityMovement->id)->update([
                'status' => 'anulado',
                'balance_before' => null,
                'balance_after' => null,
                'updated_by' => auth()->id(),
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
            ]);

            Receipt::where('related_type', ActivityMovement::class)->where('related_id', $activityMovement->id)->update([
                'status' => 'anulado',
                'updated_by' => auth()->id(),
            ]);

            $this->recalculateActivityTotals($activityMovement->activity);
            app(ShareCashMovementService::class)->recalculateBalances();
        });

        return response()->json(['message' => 'Movimiento de actividad anulado correctamente.']);
    }

    public function receipt(ActivityMovement $activityMovement)
    {
        $receipt = $activityMovement->receipt ?: Receipt::where('related_type', ActivityMovement::class)->where('related_id', $activityMovement->id)->firstOrFail();

        return redirect()->route('admin.recibos.print', $receipt);
    }

    public function receiptPdf(ActivityMovement $activityMovement)
    {
        $receipt = $activityMovement->receipt ?: Receipt::where('related_type', ActivityMovement::class)->where('related_id', $activityMovement->id)->firstOrFail();

        return redirect()->route('admin.recibos.pdf', $receipt);
    }

    public function voucher(ActivityMovement $activityMovement)
    {
        if (! $activityMovement->voucher_path || ! Storage::disk('public')->exists($activityMovement->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return Storage::disk('public')->download($activityMovement->voucher_path);
    }

    private function validatedData(Request $request, ?ActivityMovement $movement = null): array
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

    private function ensureActivityOpen(Activity $activity): void
    {
        if ($activity->status === 'cerrada') {
            throw ValidationException::withMessages(['activity_id' => ['No se pueden registrar movimientos en una actividad cerrada.']]);
        }

        if ($activity->status === 'anulada') {
            throw ValidationException::withMessages(['activity_id' => ['No se pueden registrar movimientos en una actividad anulada.']]);
        }
    }

    private function ensureCashBalance(array $data, ?ActivityMovement $movement = null): void
    {
        if (($data['status'] ?? 'registrado') !== 'registrado' || $data['type'] !== 'egreso') {
            return;
        }

        $cashMovementId = $movement ? CashMovement::where('related_type', ActivityMovement::class)->where('related_id', $movement->id)->value('id') : null;

        if ($this->currentCashBalance($cashMovementId) < (float) $data['amount']) {
            throw ValidationException::withMessages(['amount' => ['No hay saldo suficiente en caja para registrar este egreso.']]);
        }
    }

    private function currentCashBalance(?int $excludeId = null): float
    {
        $query = CashMovement::where('status', 'registrado');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return (float) (clone $query)->where('type', 'ingreso')->sum('amount') - (float) (clone $query)->where('type', 'egreso')->sum('amount');
    }

    private function storeVoucher(Request $request, array &$data, ?ActivityMovement $movement = null): void
    {
        if (! $request->hasFile('voucher_path')) {
            unset($data['voucher_path']);
            return;
        }

        if ($movement?->voucher_path) {
            Storage::disk('public')->delete($movement->voucher_path);
        }

        $data['voucher_path'] = $request->file('voucher_path')->store('activities', 'public');
    }

    private function syncCashMovement(ActivityMovement $movement): CashMovement
    {
        $movement->loadMissing('activity');

        $cashMovement = CashMovement::where('related_type', ActivityMovement::class)
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
            'category' => 'actividad',
            'concept' => 'Actividad ' . ($movement->activity?->name ?? '-') . ': ' . $movement->concept,
            'amount' => $movement->amount,
            'payment_method' => $movement->payment_method,
            'reference' => $movement->payment_reference,
            'voucher_path' => $movement->voucher_path,
            'related_type' => ActivityMovement::class,
            'related_id' => $movement->id,
            'observation' => $movement->observation,
            'status' => $movement->status,
            'updated_by' => auth()->id(),
            'annulled_by' => $movement->annulled_by,
            'annulled_at' => $movement->annulled_at,
        ]);

        $cashMovement->save();
        app(ShareCashMovementService::class)->recalculateBalances();

        return $cashMovement->fresh();
    }

    private function createOrUpdateReceipt(ActivityMovement $movement): Receipt
    {
        $movement->loadMissing('activity');

        $receipt = Receipt::firstOrNew([
            'related_type' => ActivityMovement::class,
            'related_id' => $movement->id,
        ]);

        if (! $receipt->exists) {
            $receipt->receipt_number = $this->generateNextReceiptNumber();
            $receipt->created_by = $movement->created_by ?: auth()->id();
        }

        $receipt->fill([
            'receipt_date' => $this->movementDate($movement),
            'member_id' => $movement->member_id,
            'type' => 'actividad',
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
        $lastCode = Receipt::withTrashed()->whereNotNull('receipt_number')->where('receipt_number', 'like', 'REC-%')->orderByDesc('id')->lockForUpdate()->value('receipt_number');
        $lastNumber = $lastCode && preg_match('/REC-(\d+)/', $lastCode, $matches) ? (int) $matches[1] : 0;

        return 'REC-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function recalculateActivityTotals(Activity $activity): void
    {
        $query = $activity->movements()->where('status', 'registrado');
        $income = (clone $query)->where('type', 'ingreso')->sum('amount');
        $expense = (clone $query)->where('type', 'egreso')->sum('amount');

        $activity->update([
            'total_income' => $income,
            'total_expense' => $expense,
            'profit' => (float) $income - (float) $expense,
            'updated_by' => auth()->id(),
        ]);
    }

    private function movementPayload(ActivityMovement $movement): array
    {
        $movement->load(['activity', 'member', 'receipt', 'cashMovement', 'creator']);

        return [
            'id' => $movement->id,
            'code' => $movement->code,
            'activity_id' => $movement->activity_id,
            'activity_code' => $movement->activity?->code,
            'activity_name' => $movement->activity?->name,
            'movement_date' => optional($this->movementDate($movement))->format('Y-m-d'),
            'movement_date_formatted' => optional($this->movementDate($movement))->format('d/m/Y'),
            'type' => $movement->type,
            'type_label' => ucfirst($movement->type),
            'member_id' => $movement->member_id,
            'member_name' => $movement->member?->full_name ?? '-',
            'member_dni' => $movement->member?->dni ?? '-',
            'concept' => $movement->concept,
            'amount' => number_format((float) $movement->amount, 2, '.', ''),
            'amount_formatted' => $this->money($movement->amount),
            'payment_method' => $movement->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($movement->payment_method),
            'payment_reference' => $movement->payment_reference,
            'voucher_url' => $movement->voucher_path ? Storage::url($movement->voucher_path) : null,
            'voucher_download_url' => $movement->voucher_path ? route('admin.actividades.movimientos.voucher', $movement) : null,
            'receipt_number' => $movement->receipt?->receipt_number,
            'receipt_url' => $movement->receipt ? route('admin.actividades.movimientos.receipt', $movement) : null,
            'receipt_pdf_url' => $movement->receipt ? route('admin.actividades.movimientos.receipt.pdf', $movement) : null,
            'cash_movement_number' => $movement->cashMovement?->movement_number,
            'observation' => $movement->observation,
            'status' => $movement->status,
            'status_label' => ucfirst($movement->status),
            'created_at' => optional($movement->created_at)->format('d/m/Y H:i'),
            'created_by_name' => $movement->creator?->name,
        ];
    }

    private function movementDate(ActivityMovement $movement)
    {
        return $movement->movement_date ?: $movement->date;
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

    private function money(mixed $amount): string
    {
        return 'S/ ' . number_format((float) $amount, 2);
    }

    private function messages(): array
    {
        return [
            'member_id.exists' => 'Este socio se encuentra retirado y no puede realizar nuevas operaciones.',
            'movement_date.required' => 'La fecha del movimiento es obligatoria.',
            'movement_date.date' => 'La fecha del movimiento debe ser valida.',
            'type.required' => 'Seleccione un tipo de movimiento valido.',
            'type.in' => 'Seleccione un tipo de movimiento valido.',
            'concept.required' => 'El concepto es obligatorio.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un numero valido.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'payment_method.required' => 'Seleccione un metodo de pago valido.',
            'payment_method.in' => 'Seleccione un metodo de pago valido.',
            'payment_reference.max' => 'La referencia no debe superar 100 caracteres.',
            'voucher_path.file' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.mimes' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.max' => 'El comprobante no debe superar los 4 MB.',
        ];
    }
}
