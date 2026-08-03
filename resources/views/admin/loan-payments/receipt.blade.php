<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $receipt->receipt_number }}</title>
    <style>
        body { margin: 0; padding: 28px; color: #111827; background: #f4f6f9; font-family: Arial, sans-serif; }
        .doc { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .header { display: flex; justify-content: space-between; gap: 20px; padding: 26px 30px; color: #fff; background: #1e3a5f; }
        h1 { margin: 0 0 5px; font-size: 24px; }
        .muted, .field span { color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .header .muted { color: rgba(255,255,255,.75); }
        .body { padding: 26px 30px; }
        .amount { padding: 16px; margin-bottom: 18px; border-left: 5px solid #b08968; border-radius: 10px; background: #f8fafc; }
        .amount strong { display: block; color: #1e3a5f; font-size: 28px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .field { padding: 11px; border: 1px solid #eef2f7; border-radius: 8px; background: #f8fafc; }
        .field strong { display: block; margin-top: 4px; }
        .field.full { grid-column: span 3; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 7px; text-align: right; }
        th { background: #eef2f7; font-size: 10px; text-transform: uppercase; }
        th:first-child, td:first-child { text-align: center; }
        .signatures { display: grid; grid-template-columns: repeat(2, 1fr); gap: 50px; margin-top: 55px; }
        .signature { border-top: 1px solid #111827; padding-top: 8px; text-align: center; font-size: 12px; }
        .actions { max-width: 900px; margin: 14px auto 0; text-align: right; }
        button { padding: 9px 14px; border: 1px solid #1e3a5f; border-radius: 8px; color: #fff; background: #1e3a5f; cursor: pointer; font-weight: 700; }
        @media print { body { padding: 0; background: #fff; } .doc { border: 0; border-radius: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="doc">
        <div class="header">
            <div><h1>{{ config('adminlte.title', 'SysCaC') }}</h1><div class="muted">Recibo de cobro</div></div>
            <div><span class="muted">Numero de recibo</span><br><strong>{{ $receipt->receipt_number }}</strong></div>
        </div>
        <div class="body">
            <div class="amount"><span class="muted">Total pagado (capital + interés + mora)</span><strong>S/ {{ number_format((float) $payment->amount, 2) }}</strong></div>
            <div class="grid" style="margin-bottom:18px"><div class="field"><span>Capital pagado</span><strong>S/ {{number_format((float)$payment->capital_amount,2)}}</strong></div><div class="field"><span>Interés pagado</span><strong>S/ {{number_format((float)$payment->interest_amount,2)}}</strong></div><div class="field"><span>Mora pagada</span><strong>S/ {{number_format((float)$payment->late_fee_paid,2)}}</strong></div><div class="field"><span>Saldo anterior préstamo</span><strong>S/ {{number_format((float)$payment->previous_loan_balance,2)}}</strong></div><div class="field"><span>Saldo posterior préstamo</span><strong>S/ {{number_format((float)$payment->new_loan_balance,2)}}</strong></div><div class="field"><span>Mora exonerada</span><strong>S/ {{number_format((float)$payment->late_fee_waived,2)}}</strong></div>@if((float)$payment->late_fee_waived>0)<div class="field full"><span>Motivo de exoneración</span><strong>{{$payment->late_fee_reason}}</strong></div>@endif</div>
            <div class="field full" style="margin-bottom:18px"><span>Importante</span><strong>El total pagado incluye capital, interés y mora. El saldo del préstamo es la suma de capital e interés pendientes; la mora no forma parte del saldo futuro.</strong></div>
            <div class="grid">
                <div class="field"><span>Fecha</span><strong>{{ optional($payment->payment_date)->format('d/m/Y') }}</strong></div>
                <div class="field"><span>Cobro</span><strong>{{ $payment->payment_number }}</strong></div>
                <div class="field"><span>Prestamo</span><strong>{{ $payment->loan?->loan_number ?? '-' }}</strong></div>
                <div class="field full"><span>Socio</span><strong>{{ $payment->member?->full_name ?? '-' }}</strong></div>
                <div class="field"><span>DNI</span><strong>{{ $payment->member?->dni ?? '-' }}</strong></div>
                <div class="field"><span>Codigo socio</span><strong>{{ $payment->member?->code ?? '-' }}</strong></div>
                <div class="field"><span>Tipo pago</span><strong>{{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}</strong></div>
                <div class="field"><span>Metodo pago</span><strong>{{ ucfirst($payment->payment_method ?? '-') }}</strong></div>
                <div class="field"><span>Referencia</span><strong>{{ $payment->payment_reference ?? '-' }}</strong></div>
                <div class="field"><span>Comprobante</span><strong>{{ $payment->voucher_path ? 'Adjunto' : 'Sin comprobante' }}</strong></div>
                <div class="field"><span>Usuario</span><strong>{{ $payment->creator?->name ?? '-' }}</strong></div>
                <div class="field full"><span>Observacion</span><strong>{{ $payment->observation ?? '-' }}</strong></div>
            </div>
            <table>
                <thead><tr><th>Cuota</th><th>Capital</th><th>Interés</th><th>Mora</th><th>Exonerada</th><th>Monto</th><th>Saldo anterior</th><th>Nuevo saldo</th></tr></thead>
                <tbody>
                    @forelse($payment->details as $detail)
                        <tr><td>{{ $detail->installment?->installment_number ?? '-' }}</td><td>S/ {{ number_format((float) $detail->principal_paid, 2) }}</td><td>S/ {{ number_format((float) $detail->interest_paid, 2) }}</td><td>S/ {{number_format((float)$detail->late_fee_paid,2)}}</td><td>S/ {{number_format((float)$detail->late_fee_waived,2)}}</td><td>S/ {{ number_format((float) $detail->amount_paid + (float)$detail->late_fee_paid, 2) }}</td><td>S/ {{ number_format((float) $detail->previous_balance, 2) }}</td><td>S/ {{ number_format((float) $detail->new_balance, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="8">Sin detalle de cuotas.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="signatures"><div class="signature">Firma del socio</div><div class="signature">Firma del responsable</div></div>
        </div>
    </div>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>
</body>
</html>
