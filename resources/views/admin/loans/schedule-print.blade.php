<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cronograma {{ $loan->loan_number }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { margin: 20px; color: #172433; font-family: Arial, sans-serif; background: #fff; }
        .actions { margin-bottom: 14px; text-align: right; }
        button { padding: 8px 12px; border: 1px solid #1e3a5f; border-radius: 7px; color: #fff; background: #1e3a5f; cursor: pointer; font-weight: 700; }
        .header { display: flex; justify-content: space-between; gap: 20px; border-radius: 12px; padding: 16px 18px; color: #fff; background: linear-gradient(135deg, #173b5f, #2d7187); }
        h1 { margin: 0 0 5px; font-size: 24px; }
        h2 { margin: 18px 0 9px; padding-left: 8px; border-left: 4px solid #2d7187; font-size: 13px; color: #1e3a5f; text-transform: uppercase; letter-spacing: .04em; }
        .muted { color: #6b7280; font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
        .box { border: 1px solid #dce5eb; border-radius: 9px; padding: 9px 10px; background: #f8fafc; }
        .box span { display: block; color: #6b7280; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .box strong { display: block; margin-top: 4px; font-size: 12px; }
        .financial-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; }
        .financial-grid .box { border-top: 3px solid #5d9caf; }
        .financial-grid .box.real { background: #effaf7; border-top-color: #45a17b; }
        .financial-grid .box.exonerated { background: #fff9ed; border-top-color: #d6a849; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; font-size: 8.5px; border: 1px solid #d8e1e7; border-radius: 9px; overflow: hidden; }
        th, td { border: 1px solid #d1d5db; padding: 5px 4px; text-align: right; }
        th { background: #eaf1f5; color: #27495a; text-transform: uppercase; font-size: 7.5px; }
        tbody tr:nth-child(even) { background: #f9fbfc; }
        .advanced { background: #eefaf5 !important; }.liquidated { background: #f1f6fb !important; }
        .note { margin-top: 10px; padding: 10px 12px; border: 1px solid #d9e4ea; border-left: 4px solid #4a8da3; border-radius: 8px; background: #f7fbfc; font-size: 10px; line-height: 1.5; }
        th:first-child, td:first-child, th:nth-child(2), td:nth-child(2), th:last-child, td:last-child { text-align: center; }
        .footer { display: grid; grid-template-columns: repeat(2, 1fr); gap: 40px; margin-top: 52px; }
        .signature { border-top: 1px solid #111827; padding-top: 8px; text-align: center; font-size: 12px; }
        @media print { body { margin: 0; } .actions { display: none; } .header { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    <div class="header">
        <div>
            <h1>{{ config('adminlte.title', 'SysCaC') }}</h1>
            <div class="muted">Cronograma de pagos</div>
        </div>
        <div>
            <strong>{{ $loan->loan_number }}</strong><br>
            <span style="font-size:12px;opacity:.85">Estado: {{ ucfirst($loan->status) }} &nbsp;|&nbsp; Emision: {{ $generatedAt->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <h2>Datos del socio</h2>
    <div class="grid">
        <div class="box"><span>Codigo socio</span><strong>{{ $loan->member?->code ?? '-' }}</strong></div>
        <div class="box"><span>Nombre completo</span><strong>{{ $loan->member?->full_name ?? '-' }}</strong></div>
        <div class="box"><span>DNI</span><strong>{{ $loan->member?->dni ?? '-' }}</strong></div>
        <div class="box"><span>Telefono</span><strong>{{ $loan->member?->phone ?? '-' }}</strong></div>
        <div class="box" style="grid-column: span 4;"><span>Direccion</span><strong>{{ $loan->member?->address ?? '-' }}</strong></div>
    </div>

    <h2>Datos del prestamo</h2>
    <div class="grid">
        <div class="box"><span>Codigo</span><strong>{{ $loan->loan_number }}</strong></div>
        <div class="box"><span>Estado</span><strong>{{ ucfirst($loan->status) }}</strong></div>
        <div class="box"><span>Monto solicitado</span><strong>S/ {{ number_format((float) $loan->requested_amount, 2) }}</strong></div>
        <div class="box"><span>Monto aprobado</span><strong>S/ {{ number_format((float) $loan->approved_amount, 2) }}</strong></div>
        <div class="box"><span>Tasa mensual</span><strong>{{ number_format((float) $loan->interest_rate, 2) }}%</strong></div>
        <div class="box"><span>Plazo</span><strong>{{ $loan->term_months }} meses</strong></div>
        <div class="box"><span>Amortizacion</span><strong>Aleman</strong></div>
        <div class="box"><span>Fecha inicio</span><strong>{{ optional($loan->start_date)->format('d/m/Y') }}</strong></div>
        <div class="box"><span>Primera cuota</span><strong>{{ optional($loan->first_payment_date)->format('d/m/Y') }}</strong></div>
        <div class="box"><span>Aprobacion</span><strong>{{ optional($loan->approved_at)->format('d/m/Y H:i') ?? '-' }}</strong></div>
        <div class="box"><span>Desembolso</span><strong>{{ optional($loan->disbursed_at)->format('d/m/Y H:i') ?? '-' }}</strong></div>
        <div class="box"><span>Total interes proyectado</span><strong>S/ {{ number_format((float) $loan->total_interest, 2) }}</strong></div>
    </div>

    <h2>Resumen financiero</h2>
    <div class="financial-grid">
        <div class="box"><span>Total proyectado original</span><strong>{{ $financialSummary['projected_total_formatted'] }}</strong></div>
        <div class="box real"><span>Total pagado real</span><strong>{{ $financialSummary['total_paid_formatted'] }}</strong></div>
        <div class="box real"><span>Capital pagado</span><strong>{{ $financialSummary['capital_paid_formatted'] }}</strong></div>
        <div class="box"><span>Interes pagado</span><strong>{{ $financialSummary['interest_paid_formatted'] }}</strong></div>
        <div class="box exonerated"><span>Interes exonerado / no cobrado</span><strong>{{ $financialSummary['total_interest_not_collected_formatted'] }}</strong></div>
        <div class="box"><span>Saldo final</span><strong>{{ $financialSummary['final_balance_formatted'] }}</strong></div>
    </div>

    <h2>Tabla de cronograma</h2>
    <table>
        <thead>
            <tr>
                <th>Cuota</th>
                <th>Fecha vencimiento</th>
                <th>Saldo inicial</th>
                <th>Capital</th>
                <th>Interes</th>
                <th>Interes exonerado</th>
                <th>Monto cuota</th>
                <th>Monto pagado</th>
                <th>Saldo pendiente</th>
                <th>Saldo final</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($loan->installments as $installment)
                <tr class="{{ $installment->status === 'adelantado' ? 'advanced' : ($installment->status === 'liquidado' ? 'liquidated' : '') }}">
                    <td>{{ $installment->installment_number }}</td>
                    <td>{{ optional($installment->due_date)->format('d/m/Y') }}</td>
                    <td>S/ {{ number_format((float) $installment->opening_balance, 2) }}</td>
                    <td>S/ {{ number_format((float) $installment->principal_amount, 2) }}</td>
                    <td>S/ {{ number_format((float) $installment->interest_amount, 2) }}</td>
                    <td>S/ {{ number_format((float) $installment->interest_exonerated, 2) }}</td>
                    <td>S/ {{ number_format((float) $installment->installment_amount, 2) }}</td>
                    <td>S/ {{ number_format((float) $installment->paid_amount, 2) }}</td>
                    <td>S/ {{ number_format((float) $installment->remaining_amount, 2) }}</td>
                    <td>S/ {{ number_format((float) $installment->closing_balance, 2) }}</td>
                    <td>{{ ucfirst($installment->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="11">Sin cuotas registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="note"><strong>Notas de auditoria:</strong><br>
        @if ($loan->installments->contains(fn ($installment) => $installment->status === 'adelantado')) Las cuotas marcadas como Adelantado fueron pagadas solo por capital. El interes futuro fue exonerado.<br>@endif
        @if ($loan->installments->contains(fn ($installment) => $installment->status === 'liquidado')) Las cuotas marcadas como Liquidado fueron cerradas por liquidacion anticipada. No se cobraron intereses futuros.@endif
    </div>

    <p class="muted">Usuario que genero: {{ $generatedBy }}. Fecha y hora de generacion: {{ $generatedAt->format('d/m/Y H:i') }}.</p>

    <div class="footer">
        <div class="signature">Firma del socio</div>
        <div class="signature">Firma del representante de la asociacion</div>
    </div>
</body>
</html>
