<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia {{ $refinancing->code }}</title>
    <style>
        body { margin: 24px; color: #111827; font-family: Arial, sans-serif; }
        .actions { text-align: right; margin-bottom: 14px; }
        button { padding: 8px 12px; border: 1px solid #1e3a5f; border-radius: 7px; color: #fff; background: #1e3a5f; font-weight: 700; }
        .header { display: flex; justify-content: space-between; border-bottom: 3px solid #1e3a5f; padding-bottom: 14px; }
        h1 { margin: 0 0 5px; font-size: 24px; }
        h2 { margin: 20px 0 10px; font-size: 15px; color: #1e3a5f; text-transform: uppercase; }
        .muted { color: #6b7280; font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 9px; background: #f8fafc; }
        .box span { display: block; color: #6b7280; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .box strong { display: block; margin-top: 4px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: right; }
        th { background: #eef2f7; text-transform: uppercase; font-size: 9px; }
        th:first-child, td:first-child, th:nth-child(2), td:nth-child(2), th:last-child, td:last-child { text-align: center; }
        .footer { display: grid; grid-template-columns: repeat(2, 1fr); gap: 40px; margin-top: 52px; }
        .signature { border-top: 1px solid #111827; padding-top: 8px; text-align: center; font-size: 12px; }
        @media print { .actions { display: none; } body { margin: 14px; } .box { background: #fff; } }
    </style>
</head>
<body>
    @empty($pdfMode)<div class="actions"><button onclick="window.print()">Imprimir</button></div>@endempty
    <div class="header">
        <div><h1>{{ config('adminlte.title', 'SysCaC') }}</h1><div class="muted">Constancia de refinanciamiento</div></div>
        <div><strong>{{ $refinancing->code }}</strong><br><span class="muted">{{ optional($refinancing->refinancing_date)->format('d/m/Y') }}</span></div>
    </div>
    <h2>Datos generales</h2>
    <div class="grid">
        <div class="box"><span>Socio</span><strong>{{ $refinancing->member?->full_name ?? '-' }}</strong></div>
        <div class="box"><span>DNI</span><strong>{{ $refinancing->member?->dni ?? '-' }}</strong></div>
        <div class="box"><span>Prestamo original</span><strong>{{ $refinancing->originalLoan?->loan_number ?? '-' }}</strong></div>
        <div class="box"><span>Nuevo prestamo</span><strong>{{ $refinancing->newLoan?->loan_number ?? '-' }}</strong></div>
        <div class="box"><span>Saldo anterior</span><strong>S/ {{ number_format((float) $refinancing->previous_balance, 2) }}</strong></div>
        <div class="box"><span>Monto adicional</span><strong>S/ {{ number_format((float) $refinancing->additional_amount, 2) }}</strong></div>
        <div class="box"><span>Nuevo monto</span><strong>S/ {{ number_format((float) $refinancing->new_amount, 2) }}</strong></div>
        <div class="box"><span>Tasa</span><strong>{{ number_format((float) $refinancing->interest_rate, 2) }}%</strong></div>
        <div class="box"><span>Plazo</span><strong>{{ $refinancing->term_months }} meses</strong></div>
        <div class="box"><span>Total interes</span><strong>S/ {{ number_format((float) $refinancing->total_interest, 2) }}</strong></div>
        <div class="box"><span>Total a pagar</span><strong>S/ {{ number_format((float) $refinancing->total_amount, 2) }}</strong></div>
        <div class="box"><span>Usuario</span><strong>{{ $refinancing->creator?->name ?? '-' }}</strong></div>
        <div class="box" style="grid-column: span 4;"><span>Motivo</span><strong>{{ $refinancing->reason ?? '-' }}</strong></div>
    </div>
    <h2>Nuevo cronograma</h2>
    <table>
        <thead><tr><th>Cuota</th><th>Fecha</th><th>Saldo inicial</th><th>Capital</th><th>Interes</th><th>Monto cuota</th><th>Saldo final</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse($refinancing->newLoan?->installments ?? [] as $installment)
                <tr><td>{{ $installment->installment_number }}</td><td>{{ optional($installment->due_date)->format('d/m/Y') }}</td><td>S/ {{ number_format((float) $installment->opening_balance, 2) }}</td><td>S/ {{ number_format((float) $installment->principal_amount, 2) }}</td><td>S/ {{ number_format((float) $installment->interest_amount, 2) }}</td><td>S/ {{ number_format((float) $installment->installment_amount, 2) }}</td><td>S/ {{ number_format((float) $installment->closing_balance, 2) }}</td><td>{{ ucfirst($installment->status) }}</td></tr>
            @empty
                <tr><td colspan="8">Sin cuotas generadas.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer"><div class="signature">Firma del socio</div><div class="signature">Firma del representante</div></div>
</body>
</html>
