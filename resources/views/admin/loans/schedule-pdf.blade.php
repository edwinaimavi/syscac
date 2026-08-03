<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cronograma {{ $loan->loan_number }}</title>
    <style>
        @page { margin: 30px 30px 38px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172433; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .header { width: 100%; padding: 14px 16px; color: #fff; background: #1d4f69; border-radius: 8px; }
        .header td { border: 0; vertical-align: middle; }
        .brand { margin: 0; font-size: 22px; font-weight: bold; }
        .subtitle { margin-top: 3px; color: #dcecf2; font-size: 9px; }
        .loan-code { font-size: 14px; font-weight: bold; text-align: right; }
        .meta { margin-top: 4px; color: #dcecf2; text-align: right; font-size: 8px; }
        h2 { margin: 13px 0 6px; padding: 4px 7px; border-left: 4px solid #2b8196; color: #1d4f69; background: #f3f8fa; font-size: 10px; text-transform: uppercase; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-left: -5px; }
        .cards td { padding: 7px 8px; border: 1px solid #dce6eb; border-radius: 6px; background: #f9fbfc; vertical-align: top; }
        .cards .real { border-top: 3px solid #4a9b7a; background: #f2fbf7; }
        .cards .exonerated { border-top: 3px solid #d0a242; background: #fffaf0; }
        .label { display: block; color: #687986; font-size: 6.8px; font-weight: bold; text-transform: uppercase; }
        .value { display: block; margin-top: 3px; color: #172433; font-size: 9px; font-weight: bold; }
        .schedule { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .schedule th { padding: 5px 2px; border: 1px solid #cbd9e0; color: #244c5e; background: #e9f1f4; font-size: 6.3px; text-transform: uppercase; text-align: center; }
        .schedule td { padding: 5px 3px; border: 1px solid #dce5e9; text-align: right; white-space: nowrap; font-size: 7.1px; }
        .schedule td.center { text-align: center; }
        .schedule tr:nth-child(even) td { background: #fafcfd; }
        .schedule tr.advanced td { background: #edf9f4; }
        .schedule tr.liquidated td { background: #f0f5fb; }
        .note { margin-top: 9px; padding: 8px 10px; border: 1px solid #d7e3e8; border-left: 4px solid #3a8499; border-radius: 6px; background: #f7fbfc; line-height: 1.45; }
        .signatures { width: 100%; margin-top: 28px; border-collapse: separate; border-spacing: 55px 0; }
        .signatures td { padding-top: 7px; border-top: 1px solid #45545d; text-align: center; font-size: 8px; }
        .generated { margin-top: 9px; color: #71808a; font-size: 7px; }
    </style>
</head>
<body>
    <table class="header"><tr><td><div class="brand">SysCaC</div><div class="subtitle">Sistema de Caja y Creditos - Cronograma de pagos</div></td><td><div class="loan-code">{{ $loan->loan_number }}</div><div class="meta">Estado: {{ ucfirst($loan->status) }} | Emision: {{ $generatedAt->format('d/m/Y H:i') }}</div></td></tr></table>

    <h2>Datos del socio y del prestamo</h2>
    <table class="cards"><tr>
        <td><span class="label">Socio</span><span class="value">{{ $loan->member?->full_name ?? '-' }}</span></td>
        <td><span class="label">Codigo / DNI</span><span class="value">{{ $loan->member?->code ?? '-' }} / {{ $loan->member?->dni ?? '-' }}</span></td>
        <td><span class="label">Monto aprobado</span><span class="value">S/ {{ number_format((float) $loan->approved_amount, 2) }}</span></td>
        <td><span class="label">Tasa / Plazo</span><span class="value">{{ number_format((float) $loan->interest_rate, 2) }}% mensual / {{ $loan->term_months }} meses</span></td>
        <td><span class="label">Amortizacion</span><span class="value">Aleman</span></td>
    </tr></table>

    <h2>Resumen financiero</h2>
    <table class="cards"><tr>
        <td><span class="label">Total proyectado original</span><span class="value">{{ $financialSummary['projected_total_formatted'] }}</span></td>
        <td class="real"><span class="label">Total pagado real</span><span class="value">{{ $financialSummary['total_paid_formatted'] }}</span></td>
        <td class="real"><span class="label">Capital pagado</span><span class="value">{{ $financialSummary['capital_paid_formatted'] }}</span></td>
        <td><span class="label">Interes pagado</span><span class="value">{{ $financialSummary['interest_paid_formatted'] }}</span></td>
        <td class="exonerated"><span class="label">Interes exonerado / no cobrado</span><span class="value">{{ $financialSummary['total_interest_not_collected_formatted'] }}</span></td>
        <td><span class="label">Saldo final</span><span class="value">{{ $financialSummary['final_balance_formatted'] }}</span></td>
    </tr></table>

    <h2>Tabla del cronograma</h2>
    <table class="schedule">
        <thead><tr><th>Cuota</th><th>Vencimiento</th><th>Saldo inicial</th><th>Capital</th><th>Interes</th><th>Interes exonerado</th><th>Monto cuota</th><th>Monto pagado</th><th>Saldo pendiente</th><th>Saldo final</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse ($loan->installments as $installment)
            <tr class="{{ $installment->status === 'adelantado' ? 'advanced' : ($installment->status === 'liquidado' ? 'liquidated' : '') }}">
                <td class="center">{{ $installment->installment_number }}</td><td class="center">{{ optional($installment->due_date)->format('d/m/Y') }}</td>
                <td>S/ {{ number_format((float) $installment->opening_balance, 2) }}</td><td>S/ {{ number_format((float) $installment->principal_amount, 2) }}</td><td>S/ {{ number_format((float) $installment->interest_amount, 2) }}</td><td>S/ {{ number_format((float) $installment->interest_exonerated, 2) }}</td><td>S/ {{ number_format((float) $installment->installment_amount, 2) }}</td><td>S/ {{ number_format((float) $installment->paid_amount, 2) }}</td><td>S/ {{ number_format((float) $installment->remaining_amount, 2) }}</td><td>S/ {{ number_format((float) $installment->closing_balance, 2) }}</td><td class="center">{{ ucfirst($installment->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="center">Sin cuotas registradas.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="note"><strong>Notas de auditoria:</strong><br>
        @if ($loan->installments->contains(fn ($row) => $row->status === 'adelantado')) Las cuotas marcadas como Adelantado fueron pagadas solo por capital. El interes futuro fue exonerado.<br>@endif
        @if ($loan->installments->contains(fn ($row) => $row->status === 'liquidado')) Las cuotas marcadas como Liquidado fueron cerradas por liquidacion anticipada. No se cobraron intereses futuros.@endif
    </div>
    <div class="generated">Generado por: {{ $generatedBy }} | {{ $generatedAt->format('d/m/Y H:i') }}</div>
    <table class="signatures"><tr><td>Firma del socio</td><td>Firma del representante de la asociacion</td></tr></table>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
            $pdf->page_text(700, 570, 'Pagina {PAGE_NUM} de {PAGE_COUNT}', $font, 7, array(0.35, 0.42, 0.46));
        }
    </script>
</body>
</html>
