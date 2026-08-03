<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Simulacion de prestamo {{ $simulation->code }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: "Segoe UI", Arial, sans-serif; font-size: 12px; background: #ffffff; }
        .print-actions { margin-bottom: 12px; text-align: right; }
        .print-actions button { border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; padding: 7px 12px; font-weight: 700; cursor: pointer; }
        .sheet { max-width: 1120px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; gap: 18px; padding: 18px 20px; border: 1px solid #dbe3ef; border-radius: 12px; background: #f8fafc; }
        .header h1 { margin: 0; font-size: 24px; letter-spacing: .01em; }
        .header p { margin: 7px 0 0; color: #64748b; line-height: 1.45; }
        .code-box { min-width: 190px; text-align: right; }
        .code-box span, .box span, .section-title small { display: block; color: #64748b; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
        .code-box strong { display: block; margin-top: 5px; font-size: 18px; }
        .badge { display: inline-block; margin-top: 8px; padding: 5px 10px; border-radius: 999px; color: #ffffff; font-size: 11px; font-weight: 800; }
        .badge.simulada { background: #16a34a; }
        .badge.convertida { background: #0284c7; }
        .badge.anulada { background: #dc2626; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
        .box { min-height: 64px; padding: 11px 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; }
        .box strong { display: block; margin-top: 5px; color: #0f172a; font-size: 13px; overflow-wrap: anywhere; }
        .box.highlight { background: #eff6ff; border-color: #bfdbfe; }
        .section-title { display: flex; justify-content: space-between; align-items: end; margin: 20px 0 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; }
        .section-title h2 { margin: 0; font-size: 15px; }
        .note { padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; color: #334155; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { padding: 8px 7px; background: #0f172a; color: #ffffff; border: 1px solid #0f172a; text-transform: uppercase; font-size: 9px; letter-spacing: .04em; }
        td { padding: 7px; border: 1px solid #e2e8f0; text-align: right; }
        td:first-child, td:nth-child(2), th:first-child, th:nth-child(2) { text-align: center; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .footer { display: flex; justify-content: space-between; gap: 16px; margin-top: 18px; padding-top: 10px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 10px; }
        @media print {
            .print-actions { display: none; }
            .sheet { max-width: none; }
            .header, .box, .note { break-inside: avoid; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
        }
    </style>
</head>
@php
    $status = $simulation->status ?? 'simulada';
    $statusLabel = match ($status) {
        'convertida' => 'Convertida',
        'anulada' => 'Anulada',
        default => 'Simulada',
    };
@endphp
<body>
    <div class="print-actions">
        <button onclick="window.print()">Imprimir</button>
    </div>

    <main class="sheet">
        <header class="header">
            <div>
                <h1>Simulacion de prestamo</h1>
                <p>Cronograma referencial por metodo aleman. No genera deuda real ni movimiento en Caja.</p>
            </div>
            <div class="code-box">
                <span>Codigo de simulacion</span>
                <strong>{{ $simulation->code }}</strong>
                <div class="badge {{ $status }}">{{ $statusLabel }}</div>
            </div>
        </header>

        <section>
            <div class="section-title">
                <h2>Datos del socio y simulacion</h2>
                <small>{{ optional($simulation->simulation_date)->format('d/m/Y') }}</small>
            </div>
            <div class="grid">
                <div class="box"><span>Socio</span><strong>{{ $simulation->member?->full_name ?? '-' }}</strong></div>
                <div class="box"><span>DNI</span><strong>{{ $simulation->member?->dni ?? '-' }}</strong></div>
                <div class="box"><span>Monto</span><strong>S/ {{ number_format((float) $simulation->amount, 2) }}</strong></div>
                <div class="box"><span>Tasa mensual</span><strong>{{ number_format((float) $simulation->interest_rate, 2) }}%</strong></div>
                <div class="box"><span>Plazo</span><strong>{{ $simulation->term_months }} meses</strong></div>
                <div class="box"><span>Fecha inicio</span><strong>{{ optional($simulation->start_date)->format('d/m/Y') }}</strong></div>
                <div class="box"><span>Primera cuota</span><strong>{{ optional($simulation->first_payment_date)->format('d/m/Y') }}</strong></div>
                <div class="box"><span>Metodo</span><strong>Aleman</strong></div>
            </div>
        </section>

        <section>
            <div class="section-title"><h2>Resumen financiero</h2></div>
            <div class="grid">
                <div class="box highlight"><span>Capital fijo</span><strong>S/ {{ number_format((float) $simulation->fixed_principal, 2) }}</strong></div>
                <div class="box highlight"><span>Total interes</span><strong>S/ {{ number_format((float) $simulation->total_interest, 2) }}</strong></div>
                <div class="box highlight"><span>Total a pagar</span><strong>S/ {{ number_format((float) $simulation->total_payment, 2) }}</strong></div>
                <div class="box"><span>Prestamo generado</span><strong>{{ $simulation->convertedLoan?->loan_number ?? '-' }}</strong></div>
            </div>
        </section>

        <section>
            <div class="section-title"><h2>Observacion</h2></div>
            <div class="note">{{ $simulation->observation ?: 'Sin observaciones' }}</div>
        </section>

        <section>
            <div class="section-title">
                <h2>Cronograma</h2>
                <small>{{ $simulation->installments->count() }} cuotas</small>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Cuota</th>
                        <th>Fecha</th>
                        <th>Saldo inicial</th>
                        <th>Capital</th>
                        <th>Interes</th>
                        <th>Monto cuota</th>
                        <th>Saldo final</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($simulation->installments as $installment)
                        <tr>
                            <td>{{ $installment->installment_number }}</td>
                            <td>{{ optional($installment->due_date)->format('d/m/Y') }}</td>
                            <td>S/ {{ number_format((float) $installment->opening_balance, 2) }}</td>
                            <td>S/ {{ number_format((float) $installment->principal_amount, 2) }}</td>
                            <td>S/ {{ number_format((float) $installment->interest_amount, 2) }}</td>
                            <td>S/ {{ number_format((float) $installment->installment_amount, 2) }}</td>
                            <td>S/ {{ number_format((float) $installment->closing_balance, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <footer class="footer">
            <div>Fecha de impresion: {{ $generatedAt->format('d/m/Y H:i') }}</div>
            <div>Usuario: {{ $generatedBy }}</div>
            <div>Pagina <span class="page-number"></span></div>
        </footer>
    </main>
</body>
</html>
