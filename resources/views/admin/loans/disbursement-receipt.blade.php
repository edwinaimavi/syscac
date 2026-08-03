<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $receipt->receipt_number }}</title>
    <style>
        body { margin: 0; padding: 32px; color: #111827; background: #f4f6f9; font-family: Arial, sans-serif; }
        .receipt { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .header { display: flex; justify-content: space-between; gap: 24px; padding: 28px 32px; color: #fff; background: #1e3a5f; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        .muted, .field span, .number span { color: #6b7280; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .header .muted, .number span { color: rgba(255, 255, 255, .72); }
        .number { text-align: right; }
        .number strong { display: block; margin-top: 4px; font-size: 22px; }
        .body { padding: 28px 32px; }
        .amount { margin-bottom: 22px; padding: 18px; border-left: 5px solid #b08968; border-radius: 12px; background: #f8fafc; }
        .amount strong { display: block; margin-top: 4px; color: #1e3a5f; font-size: 30px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .field { padding: 14px; border: 1px solid #eef2f7; border-radius: 10px; background: #f8fafc; }
        .field strong { display: block; margin-top: 5px; font-size: 15px; }
        .field.full { grid-column: span 2; }
        .actions { max-width: 860px; margin: 16px auto 0; text-align: right; }
        .actions button { padding: 9px 14px; border: 1px solid #1e3a5f; border-radius: 8px; color: #fff; background: #1e3a5f; cursor: pointer; font-weight: 700; }
        @media print { body { padding: 0; background: #fff; } .receipt { border: 0; border-radius: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div>
                <h1>{{ config('adminlte.title', 'SysCaC') }}</h1>
                <div class="muted">Recibo de desembolso</div>
            </div>
            <div class="number">
                <span>Numero de recibo</span>
                <strong>{{ $receipt->receipt_number }}</strong>
            </div>
        </div>

        <div class="body">
            <div class="amount">
                <span class="muted">Monto desembolsado</span>
                <strong>S/ {{ number_format((float) $loan->disbursed_amount, 2) }}</strong>
            </div>

            <div class="grid">
                <div class="field"><span>Codigo de prestamo</span><strong>{{ $loan->loan_number }}</strong></div>
                <div class="field"><span>Fecha desembolso</span><strong>{{ optional($loan->disbursed_at)->format('d/m/Y H:i') }}</strong></div>
                <div class="field full"><span>Socio</span><strong>{{ $loan->member?->full_name ?? '-' }}</strong></div>
                <div class="field"><span>DNI</span><strong>{{ $loan->member?->dni ?? '-' }}</strong></div>
                <div class="field"><span>Codigo socio</span><strong>{{ $loan->member?->code ?? '-' }}</strong></div>
                <div class="field"><span>Metodo pago</span><strong>{{ ucfirst($loan->disbursement_payment_method ?? '-') }}</strong></div>
                <div class="field"><span>Referencia</span><strong>{{ $loan->disbursement_reference ?? '-' }}</strong></div>
                <div class="field"><span>Comprobante</span><strong>{{ $loan->disbursement_voucher_path ? 'Adjunto' : 'Sin comprobante' }}</strong></div>
                <div class="field"><span>Usuario que desembolso</span><strong>{{ $loan->disburser?->name ?? '-' }}</strong></div>
                <div class="field"><span>Estado</span><strong>{{ ucfirst($receipt->status ?? '-') }}</strong></div>
                <div class="field full"><span>Observacion</span><strong>{{ $loan->observation ?? '-' }}</strong></div>
            </div>
        </div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>
