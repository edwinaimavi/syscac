<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\ActivityMovement;
use App\Models\CashMovement;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRefinancing;
use App\Models\Member;
use App\Models\MemberAccountClosure;
use App\Models\MemberEnrollment;
use App\Models\MemberShare;
use App\Models\ProfitDistributionDetail;
use App\Models\Receipt;
use App\Models\SolidarityMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.recibos.index')->only(['index', 'list', 'summary']);
        $this->middleware('can:admin.recibos.show')->only(['show']);
        $this->middleware('can:admin.recibos.print')->only(['print']);
        $this->middleware('can:admin.recibos.pdf')->only(['pdf']);
        $this->middleware('can:admin.recibos.download')->only(['download']);
        $this->middleware('can:admin.recibos.voucher')->only(['voucher']);
        $this->middleware('can:admin.recibos.delete')->only(['destroy']);
    }

    public function index()
    {
        return view('admin.receipts.index', [
            'members' => Member::orderBy('full_name')->get(['id', 'code', 'dni', 'full_name']),
            'types' => $this->types(),
        ]);
    }

    public function list(Request $request)
    {
        $receipts = Receipt::with(['member', 'related'])
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->input('payment_method')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('receipt_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('receipt_date', '<=', $request->input('date_to')))
            ->orderByDesc('receipt_date')
            ->orderByDesc('id');

        return DataTables::of($receipts)
            ->addIndexColumn()
            ->editColumn('receipt_date', fn (Receipt $receipt) => optional($receipt->receipt_date)->format('d/m/Y') ?? '-')
            ->editColumn('type', fn (Receipt $receipt) => $this->typeLabel($receipt->type))
            ->addColumn('member_name', fn (Receipt $receipt) => $receipt->member?->full_name ?? '-')
            ->addColumn('concept_reference', fn (Receipt $receipt) => $this->conceptReference($receipt))
            ->editColumn('amount', fn (Receipt $receipt) => 'S/ ' . number_format((float) $receipt->amount, 2))
            ->editColumn('payment_method', fn (Receipt $receipt) => $this->paymentMethodLabel($receipt->payment_method))
            ->editColumn('status', fn (Receipt $receipt) => $this->statusBadge($receipt->status))
            ->addColumn('acciones', fn (Receipt $receipt) => view('admin.receipts.partials.acciones', compact('receipt'))->render())
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function summary()
    {
        return response()->json([
            'issued' => Receipt::count(),
            'total' => number_format((float) Receipt::where('status', 'registrado')->sum('amount'), 2),
            'month' => Receipt::where('status', 'registrado')->whereYear('receipt_date', now()->year)->whereMonth('receipt_date', now()->month)->count(),
            'annulled' => Receipt::where('status', 'anulado')->count(),
        ]);
    }

    public function show(Receipt $recibo)
    {
        $this->loadRelatedContext($recibo);

        return response()->json($this->receiptPayload($recibo));
    }

    public function print(Receipt $recibo)
    {
        $this->loadRelatedContext($recibo);

        return view('admin.receipts.print', [
            'receipt' => $recibo,
            'detailRows' => $this->detailRows($recibo),
            'typeLabel' => $this->typeLabel($recibo->type),
        ]);
    }

    public function pdf(Receipt $recibo)
    {
        $this->loadRelatedContext($recibo);

        return Pdf::loadView('admin.receipts.print', [
            'receipt' => $recibo,
            'detailRows' => $this->detailRows($recibo),
            'typeLabel' => $this->typeLabel($recibo->type),
            'pdfMode' => true,
        ])->setPaper('a4', 'portrait')->stream($recibo->receipt_number . '.pdf');
    }

    public function download(Receipt $recibo)
    {
        if ($recibo->file_path && Storage::disk('public')->exists($recibo->file_path)) {
            return Storage::disk('public')->download($recibo->file_path);
        }

        return $this->pdf($recibo);
    }

    public function voucher(Receipt $recibo)
    {
        abort_unless($recibo->voucher_path && Storage::disk('public')->exists($recibo->voucher_path), 404);

        return Storage::disk('public')->download($recibo->voucher_path);
    }

    public function destroy(Receipt $recibo)
    {
        if ($recibo->related_type) {
            return response()->json(['message' => 'No se puede anular directamente un recibo vinculado a otro modulo. Anule el registro de origen.'], 422);
        }

        $recibo->update(['status' => 'anulado', 'updated_by' => auth()->id()]);

        return response()->json(['message' => 'Recibo anulado correctamente.']);
    }

    private function receiptPayload(Receipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_date' => optional($receipt->receipt_date)->format('d/m/Y'),
            'type' => $receipt->type,
            'type_label' => $this->typeLabel($receipt->type),
            'member_name' => $receipt->member?->full_name,
            'member_dni' => $receipt->member?->dni,
            'member_code' => $receipt->member?->code,
            'amount_formatted' => 'S/ ' . number_format((float) $receipt->amount, 2),
            'payment_method_label' => $this->paymentMethodLabel($receipt->payment_method),
            'payment_reference' => $receipt->payment_method === 'efectivo' ? 'No aplica' : ($receipt->payment_reference ?: '-'),
            'concept_reference' => $this->conceptReference($receipt),
            'origin_label' => class_basename($receipt->related_type ?: ''),
            'origin_type' => $receipt->related_type,
            'origin_id' => $receipt->related_id,
            'origin_details' => $this->detailRows($receipt),
            'voucher_url' => $receipt->voucher_path ? route('admin.recibos.voucher', $receipt) : null,
            'print_url' => route('admin.recibos.print', $receipt),
            'pdf_url' => route('admin.recibos.pdf', $receipt),
            'observation' => $receipt->observation,
            'status' => $receipt->status,
            'status_label' => $receipt->status === 'anulado' ? 'Anulado' : 'Registrado',
            'created_by_name' => $receipt->creator?->name ?? $this->originCreatorName($receipt),
            'created_at' => optional($receipt->created_at)->format('d/m/Y H:i'),
        ];
    }

    private function loadRelatedContext(Receipt $receipt): void
    {
        $receipt->load(['member', 'creator', 'related']);

        $related = $receipt->related;

        if ($related instanceof MemberShare) {
            $related->loadMissing(['member', 'creator']);
        } elseif ($related instanceof LoanPayment) {
            $related->loadMissing(['loan', 'member', 'details.installment', 'creator']);
        } elseif ($related instanceof Loan) {
            $related->loadMissing(['member', 'disburser']);
        } elseif ($related instanceof LoanRefinancing) {
            $related->loadMissing(['member', 'originalLoan', 'newLoan', 'creator']);
        } elseif ($related instanceof CashMovement) {
            $related->loadMissing(['creator']);
        } elseif ($related instanceof SolidarityMovement) {
            $related->loadMissing(['member', 'creator']);
        } elseif ($related instanceof ActivityMovement) {
            $related->loadMissing(['activity', 'member', 'creator']);
        } elseif ($related instanceof ProfitDistributionDetail) {
            $related->loadMissing(['distribution', 'member', 'payer']);
        } elseif ($related instanceof MemberAccountClosure) {
            $related->loadMissing(['member', 'details', 'closer']);
        } elseif ($related instanceof MemberEnrollment) {
            $related->loadMissing(['member', 'creator']);
        }
    }

    private function detailRows(Receipt $receipt): array
    {
        $related = $receipt->related;

        if ($related instanceof MemberShare) {
            return [
                ['label' => 'Codigo de aporte', 'value' => $related->code],
                ['label' => 'Monto aportado', 'value' => $this->money($related->amount)],
                ['label' => 'Valor de accion', 'value' => $this->money($related->share_value)],
                ['label' => 'Cantidad de acciones', 'value' => rtrim(rtrim(number_format((float) $related->shares_quantity, 4, '.', ''), '0'), '.')],
                ['label' => 'Usuario que registro', 'value' => $related->creator?->name ?? '-'],
            ];
        }

        if ($related instanceof MemberEnrollment) {
            return [
                ['label' => 'Codigo de inscripcion', 'value' => $related->code],
                ['label' => 'Socio', 'value' => $related->member?->full_name ?? '-'],
                ['label' => 'DNI', 'value' => $related->member?->dni ?? '-'],
                ['label' => 'Codigo socio', 'value' => $related->member?->code ?? '-'],
                ['label' => 'Monto inscripcion', 'value' => $this->money($related->amount)],
                ['label' => 'Metodo de pago', 'value' => ucfirst($related->payment_method)],
                ['label' => 'Referencia', 'value' => $related->payment_method === 'efectivo' ? 'No aplica' : ($related->payment_reference ?: '-')],
                ['label' => 'Comprobante', 'value' => $related->voucher_path ? 'Registrado' : 'Sin comprobante'],
                ['label' => 'Usuario que registro', 'value' => $related->creator?->name ?? '-'],
                ['label' => 'Observacion', 'value' => $related->observation ?? '-'],
            ];
        }

        if ($related instanceof LoanPayment) {
            $details = $related->details->map(fn ($detail) => [
                'label' => 'Cuota ' . ($detail->installment?->installment_number ?? '-'),
                'value' => 'Capital ' . $this->money($detail->principal_paid) . ' / Interés ' . $this->money($detail->interest_paid) . ' / Mora pagada ' . $this->money($detail->late_fee_paid) . ' / Mora exonerada ' . $this->money($detail->late_fee_waived) . ' / Saldo ' . $this->money($detail->previous_balance) . ' -> ' . $this->money($detail->new_balance),
            ])->all();

            return array_merge([
                ['label' => 'Codigo de prestamo', 'value' => $related->loan?->loan_number ?? '-'],
                ['label' => 'Codigo de cobro', 'value' => $related->payment_number],
                ['label' => 'Tipo de pago', 'value' => $this->typeLabel($receipt->type)],
                ['label' => 'Capital pagado', 'value' => $this->money($related->capital_amount)],
                ['label' => 'Interés pagado', 'value' => $this->money($related->interest_amount)],
                ['label' => 'Mora pagada', 'value' => $this->money($related->late_fee_paid)],
                ['label' => 'Mora exonerada', 'value' => $this->money($related->late_fee_waived)],
                ['label' => 'Total pagado', 'value' => $this->money($related->amount)],
                ['label' => 'Saldo anterior del préstamo', 'value' => $this->money($related->previous_loan_balance)],
                ['label' => 'Saldo posterior del préstamo', 'value' => $this->money($related->new_loan_balance)],
                ['label' => 'Nota financiera', 'value' => 'El total incluye capital, interés y mora. El saldo del préstamo es la suma de capital e interés pendientes; la mora no forma parte del saldo futuro.'],
                ['label' => 'Motivo de exoneración', 'value' => $related->late_fee_reason ?: 'No aplica'],
                ['label' => 'Usuario que registro', 'value' => $related->creator?->name ?? '-'],
            ], $details);
        }

        if ($related instanceof Loan) {
            return [
                ['label' => 'Codigo de prestamo', 'value' => $related->loan_number],
                ['label' => 'Socio', 'value' => $related->member?->full_name ?? '-'],
                ['label' => 'DNI', 'value' => $related->member?->dni ?? '-'],
                ['label' => 'Monto desembolsado', 'value' => $this->money($related->disbursed_amount)],
                ['label' => 'Usuario que desembolso', 'value' => $related->disburser?->name ?? '-'],
            ];
        }

        if ($related instanceof LoanRefinancing) {
            return [
                ['label' => 'Prestamo original', 'value' => $related->originalLoan?->loan_number ?? '-'],
                ['label' => 'Nuevo prestamo', 'value' => $related->newLoan?->loan_number ?? '-'],
                ['label' => 'Saldo anterior', 'value' => $this->money($related->previous_balance)],
                ['label' => 'Nuevo monto refinanciado', 'value' => $this->money($related->new_amount)],
                ['label' => 'Tasa nueva', 'value' => number_format((float) $related->interest_rate, 2) . '% mensual'],
                ['label' => 'Plazo nuevo', 'value' => $related->term_months . ' meses'],
                ['label' => 'Total interes', 'value' => $this->money($related->total_interest)],
                ['label' => 'Total a pagar', 'value' => $this->money($related->total_amount)],
                ['label' => 'Motivo', 'value' => $related->reason ?? '-'],
                ['label' => 'Usuario que registro', 'value' => $related->creator?->name ?? '-'],
            ];
        }

        if ($related instanceof CashMovement) {
            return [
                ['label' => 'Codigo de movimiento', 'value' => $related->movement_number],
                ['label' => 'Tipo', 'value' => ucfirst($related->type)],
                ['label' => 'Categoria', 'value' => ucfirst(str_replace('_', ' ', $related->category))],
                ['label' => 'Concepto', 'value' => $related->concept],
                ['label' => 'Usuario que registro', 'value' => $related->creator?->name ?? '-'],
            ];
        }

        if ($related instanceof SolidarityMovement) {
            return [
                ['label' => 'Codigo de solidaridad', 'value' => $related->code],
                ['label' => 'Fecha', 'value' => optional($related->movement_date ?: $related->date)->format('d/m/Y')],
                ['label' => 'Tipo', 'value' => ucfirst($related->type)],
                ['label' => 'Socio', 'value' => $related->member?->full_name ?? '-'],
                ['label' => 'DNI', 'value' => $related->member?->dni ?? '-'],
                ['label' => 'Concepto', 'value' => $related->concept],
                ['label' => 'Metodo de pago', 'value' => ucfirst($related->payment_method ?? '-')],
                ['label' => 'Referencia', 'value' => $related->payment_reference ?? '-'],
                ['label' => 'Comprobante', 'value' => $related->voucher_path ? 'Registrado' : 'Sin comprobante'],
                ['label' => 'Observacion', 'value' => $related->observation ?? '-'],
                ['label' => 'Usuario que registro', 'value' => $related->creator?->name ?? '-'],
            ];
        }

        if ($related instanceof ActivityMovement) {
            return [
                ['label' => 'Codigo de actividad', 'value' => $related->activity?->code ?? '-'],
                ['label' => 'Actividad', 'value' => $related->activity?->name ?? '-'],
                ['label' => 'Codigo de movimiento', 'value' => $related->code ?? '-'],
                ['label' => 'Fecha', 'value' => optional($related->movement_date ?: $related->date)->format('d/m/Y')],
                ['label' => 'Socio', 'value' => $related->member?->full_name ?? '-'],
                ['label' => 'DNI', 'value' => $related->member?->dni ?? '-'],
                ['label' => 'Tipo', 'value' => ucfirst($related->type)],
                ['label' => 'Concepto', 'value' => $related->concept],
                ['label' => 'Metodo de pago', 'value' => ucfirst($related->payment_method ?? '-')],
                ['label' => 'Referencia', 'value' => $related->payment_reference ?? '-'],
                ['label' => 'Comprobante', 'value' => $related->voucher_path ? 'Registrado' : 'Sin comprobante'],
                ['label' => 'Observacion', 'value' => $related->observation ?? '-'],
                ['label' => 'Usuario que registro', 'value' => $related->creator?->name ?? '-'],
            ];
        }

        if ($related instanceof ProfitDistributionDetail) {
            return [
                ['label' => 'Periodo', 'value' => trim(($related->distribution?->period_month ?? '') . '/' . ($related->distribution?->period_year ?? ''), '/') ?: '-'],
                ['label' => 'Socio', 'value' => $related->member?->full_name ?? '-'],
                ['label' => 'DNI', 'value' => $related->member?->dni ?? '-'],
                ['label' => 'Codigo de socio', 'value' => $related->member?->code ?? '-'],
                ['label' => 'Cantidad de acciones', 'value' => number_format((float) $related->shares_quantity, 2)],
                ['label' => 'Porcentaje participacion', 'value' => number_format((float) $related->participation_percentage, 4) . '%'],
                ['label' => 'Monto utilidad pagada', 'value' => $this->money($related->paid_amount)],
                ['label' => 'Metodo de pago', 'value' => ucfirst($related->payment_method ?? '-')],
                ['label' => 'Referencia', 'value' => $related->payment_reference ?? '-'],
                ['label' => 'Comprobante', 'value' => $related->voucher_path ? 'Registrado' : 'Sin comprobante'],
                ['label' => 'Usuario que pago', 'value' => $related->payer?->name ?? '-'],
                ['label' => 'Observacion', 'value' => $related->observation ?? '-'],
            ];
        }

        if ($related instanceof MemberAccountClosure) {
            return [
                ['label' => 'Codigo de cierre', 'value' => $related->code],
                ['label' => 'Socio', 'value' => $related->member?->full_name ?? '-'],
                ['label' => 'DNI', 'value' => $related->member?->dni ?? '-'],
                ['label' => 'Codigo de socio', 'value' => $related->member?->code ?? '-'],
                ['label' => 'Fecha de ingreso', 'value' => optional($related->member?->admission_date)->format('d/m/Y') ?? '-'],
                ['label' => 'Fecha de retiro', 'value' => optional($related->retirement_date)->format('d/m/Y') ?? '-'],
                ['label' => 'Aportes realizados', 'value' => $this->money($related->total_contributions)],
                ['label' => 'Acciones acumuladas', 'value' => number_format((float) $related->total_shares, 4)],
                ['label' => 'Utilidades pendientes', 'value' => $this->money($related->pending_utilities_amount)],
                ['label' => 'Deudas pendientes', 'value' => $this->money($related->pending_loans_amount)],
                ['label' => 'Total a favor', 'value' => $this->money($related->total_in_favor)],
                ['label' => 'Total en contra', 'value' => $this->money($related->total_against)],
                ['label' => 'Saldo final', 'value' => $this->money($related->final_balance)],
                ['label' => 'Tipo resultado', 'value' => ucfirst(str_replace('_', ' ', $related->settlement_type))],
                ['label' => 'Metodo de pago', 'value' => ucfirst($related->payment_method ?? '-')],
                ['label' => 'Referencia', 'value' => $related->payment_reference ?? '-'],
                ['label' => 'Usuario que cerro', 'value' => $related->closer?->name ?? '-'],
                ['label' => 'Firma del socio', 'value' => '____________________________'],
                ['label' => 'Firma del responsable', 'value' => '____________________________'],
            ];
        }

        return [
            ['label' => 'Relacion', 'value' => $receipt->related_type ? class_basename($receipt->related_type) . ' #' . $receipt->related_id : 'Sin relacion'],
        ];
    }

    private function conceptReference(Receipt $receipt): string
    {
        $related = $receipt->related;

        if ($related instanceof MemberShare) {
            return $related->code ?? $receipt->payment_reference ?? '-';
        }

        if ($related instanceof MemberEnrollment) {
            return 'Inscripcion de socio ' . ($related->code ?? '-');
        }

        if ($related instanceof LoanPayment) {
            return trim(($related->payment_number ?? '') . ' ' . ($related->loan?->loan_number ?? '')) ?: ($receipt->payment_reference ?? '-');
        }

        if ($related instanceof Loan) {
            return $related->loan_number ?? $receipt->payment_reference ?? '-';
        }

        if ($related instanceof LoanRefinancing) {
            return trim(($related->code ?? '') . ' ' . ($related->originalLoan?->loan_number ?? '') . ' -> ' . ($related->newLoan?->loan_number ?? '')) ?: '-';
        }

        if ($related instanceof CashMovement) {
            return $related->concept ?? $related->movement_number ?? '-';
        }

        if ($related instanceof SolidarityMovement) {
            return trim(($related->code ?? '') . ' - ' . ($related->concept ?? '-'), ' -');
        }

        if ($related instanceof ActivityMovement) {
            return trim(($related->activity?->code ?? '') . ' - ' . ($related->code ?? '') . ' - ' . ($related->concept ?? '-'), ' -');
        }

        if ($related instanceof ProfitDistributionDetail) {
            return 'Utilidad ' . ($related->distribution?->period_month ?? '-') . '/' . ($related->distribution?->period_year ?? '-');
        }

        if ($related instanceof MemberAccountClosure) {
            return 'Cierre de cuenta ' . ($related->code ?? '-');
        }

        return $receipt->payment_reference ?? $receipt->observation ?? '-';
    }

    private function originCreatorName(Receipt $receipt): ?string
    {
        $related = $receipt->related;

        return $related?->creator?->name ?? null;
    }

    private function types(): array
    {
        return [
            'aporte_accion' => 'Aporte de accion',
            'inscripcion_socio' => 'Inscripcion de socio',
            'cobro_prestamo' => 'Cobro de prestamo',
            'pago_parcial' => 'Pago parcial',
            'abono_capital' => 'Abono a capital',
            'liquidacion_prestamo' => 'Liquidacion de prestamo',
            'desembolso_prestamo' => 'Desembolso de prestamo',
            'caja' => 'Movimiento de caja',
            'solidaridad' => 'Solidaridad',
            'actividad' => 'Actividad',
            'utilidad' => 'Utilidad',
            'refinanciamiento' => 'Refinanciamiento',
            'cierre_socio' => 'Cierre de cuenta de socio',
            'otro' => 'Otro',
        ];
    }

    private function typeLabel(?string $type): string
    {
        return $this->types()[$type] ?? '-';
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

    private function statusBadge(?string $status): string
    {
        $class = $status === 'anulado' ? 'danger' : 'success';

        return '<span class="badge badge-' . $class . '">' . e($status === 'anulado' ? 'Anulado' : 'Registrado') . '</span>';
    }

    private function money(mixed $amount): string
    {
        return 'S/ ' . number_format((float) $amount, 2);
    }
}
