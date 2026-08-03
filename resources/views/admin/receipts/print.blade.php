<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $receipt->receipt_number }}</title>
    <style>
        body { margin: 0; padding: 30px; color: #111827; background: #f4f6f9; font-family: Arial, sans-serif; }
        .doc { max-width: 820px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .header { display: flex; justify-content: space-between; padding: 26px 30px; color: #fff; background: #1e3a5f; }
        h1 { margin: 0 0 5px; font-size: 24px; }
        .muted, .field span { color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .header .muted { color: rgba(255,255,255,.75); }
        .body { padding: 26px 30px; }
        .amount { padding: 16px; margin-bottom: 18px; border-left: 5px solid #b08968; border-radius: 10px; background: #f8fafc; }
        .amount strong { display: block; color: #1e3a5f; font-size: 28px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .field { padding: 12px; border: 1px solid #eef2f7; border-radius: 8px; background: #f8fafc; }
        .field strong { display: block; margin-top: 4px; }
        .field.full { grid-column: span 2; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #eef2f7; font-size: 10px; text-transform: uppercase; }
        .signatures { display: grid; grid-template-columns: repeat(2, 1fr); gap: 50px; margin-top: 55px; }
        .signature { border-top: 1px solid #111827; padding-top: 8px; text-align: center; font-size: 12px; }
        .actions { max-width: 820px; margin: 14px auto 0; text-align: right; }
        button { padding: 9px 14px; border: 1px solid #1e3a5f; border-radius: 8px; color: #fff; background: #1e3a5f; cursor: pointer; font-weight: 700; }
        @media print { body { padding: 0; background: #fff; } .doc { border: 0; border-radius: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="doc">
        <div class="header"><div><h1>{{ config('adminlte.title', 'SysCaC') }}</h1><div class="muted">Recibo</div></div><div><span class="muted">Numero</span><br><strong>{{ $receipt->receipt_number }}</strong></div></div>
        <div class="body">
            <div class="amount"><span class="muted">Monto</span><strong>S/ {{ number_format((float) $receipt->amount, 2) }}</strong></div>
            <div class="grid">
                <div class="field"><span>Fecha</span><strong>{{ optional($receipt->receipt_date)->format('d/m/Y') }}</strong></div>
                <div class="field"><span>Tipo</span><strong>{{ $typeLabel }}</strong></div>
                <div class="field full"><span>Socio</span><strong>{{ $receipt->member?->full_name ?? '-' }}</strong></div>
                <div class="field"><span>DNI</span><strong>{{ $receipt->member?->dni ?? '-' }}</strong></div>
                <div class="field"><span>Codigo socio</span><strong>{{ $receipt->member?->code ?? '-' }}</strong></div>
                <div class="field"><span>Metodo pago</span><strong>{{ ucfirst($receipt->payment_method ?? '-') }}</strong></div>
                <div class="field"><span>Referencia</span><strong>{{ $receipt->payment_method === 'efectivo' ? 'No aplica' : ($receipt->payment_reference ?: '-') }}</strong></div>
                <div class="field"><span>Comprobante</span><strong>{{ $receipt->voucher_path ? 'Adjunto' : 'Sin comprobante' }}</strong></div>
                <div class="field"><span>Estado</span><strong>{{ ucfirst($receipt->status ?? '-') }}</strong></div>
                <div class="field full"><span>Observacion</span><strong>{{ $receipt->observation ?? '-' }}</strong></div>
            </div>
            <table>
                <thead><tr><th>Dato del movimiento relacionado</th><th>Valor</th></tr></thead>
                <tbody>
                    @forelse($detailRows as $row)
                        <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                    @empty
                        <tr><td colspan="2">Sin datos relacionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="signatures"><div class="signature">Firma del socio</div><div class="signature">Firma del responsable</div></div>
        </div>
    </div>
    @empty($pdfMode)
        <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>
    @endempty
</body>
</html>
