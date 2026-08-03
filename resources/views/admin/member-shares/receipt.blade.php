<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $receipt->receipt_number }}</title>
    <style>
        body {
            margin: 0;
            padding: 32px;
            color: #111827;
            background: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        .receipt {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .receipt-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding: 28px 32px;
            color: #ffffff;
            background: #1e3a5f;
        }

        .receipt-header h1 {
            margin: 0 0 6px;
            font-size: 24px;
        }

        .receipt-header p,
        .receipt-number span,
        .field span {
            margin: 0;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .receipt-header p,
        .receipt-number span {
            color: rgba(255, 255, 255, .72);
        }

        .receipt-number {
            text-align: right;
        }

        .receipt-number strong {
            display: block;
            margin-top: 4px;
            font-size: 22px;
        }

        .receipt-body {
            padding: 28px 32px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .field {
            padding: 14px;
            border: 1px solid #eef2f7;
            border-radius: 10px;
            background: #f8fafc;
        }

        .field strong {
            display: block;
            margin-top: 5px;
            font-size: 15px;
        }

        .field.full {
            grid-column: span 2;
        }

        .voucher-box {
            grid-column: span 2;
            display: flex;
            gap: 14px;
            align-items: center;
            padding: 14px;
            border: 1px solid #eef2f7;
            border-radius: 10px;
            background: #f8fafc;
        }

        .voucher-preview {
            width: 160px;
            min-height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            overflow: hidden;
        }

        .voucher-preview img {
            width: 100%;
            max-height: 120px;
            object-fit: contain;
        }

        .voucher-preview i {
            color: #dc2626;
            font-size: 32px;
        }

        .voucher-info a {
            display: inline-block;
            margin-top: 8px;
            padding: 7px 10px;
            border-radius: 8px;
            color: #ffffff;
            background: #1e3a5f;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .amount-box {
            margin: 22px 0;
            padding: 18px;
            border-radius: 12px;
            background: #f4f6f9;
            border-left: 5px solid #b08968;
        }

        .amount-box span {
            display: block;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .amount-box strong {
            display: block;
            margin-top: 4px;
            color: #1e3a5f;
            font-size: 28px;
        }

        .actions {
            max-width: 820px;
            margin: 16px auto 0;
            text-align: right;
        }

        .actions button {
            padding: 9px 14px;
            border: 1px solid #1e3a5f;
            border-radius: 8px;
            color: #ffffff;
            background: #1e3a5f;
            cursor: pointer;
            font-weight: 700;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .receipt {
                border: 0;
                border-radius: 0;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    @php
        $voucher = app(\App\Http\Controllers\Admin\MemberShareController::class)->voucherStateForView($share);
    @endphp

    <div class="receipt">
        <div class="receipt-header">
            <div>
                <h1>{{ config('adminlte.title', 'SysCaC') }}</h1>
                <p>Recibo por aporte de acciones</p>
            </div>
            <div class="receipt-number">
                <span>Numero de recibo</span>
                <strong>{{ $receipt->receipt_number }}</strong>
            </div>
        </div>

        <div class="receipt-body">
            <div class="amount-box">
                <span>Monto total pagado</span>
                <strong>S/ {{ number_format((float) ($share->total_paid ?? $share->amount), 2) }}</strong>
            </div>

            <div class="grid">
                <div class="field"><span>Fecha</span><strong>{{ optional($share->date)->format('d/m/Y') }}</strong></div>
                <div class="field"><span>Codigo aporte</span><strong>{{ $share->code }}</strong></div>
                <div class="field full"><span>Socio</span><strong>{{ $share->member?->full_name ?? '-' }}</strong></div>
                <div class="field"><span>DNI</span><strong>{{ $share->member?->dni ?? '-' }}</strong></div>
                <div class="field"><span>Codigo socio</span><strong>{{ $share->member?->code ?? '-' }}</strong></div>
                <div class="field"><span>Valor accion</span><strong>S/ {{ number_format((float) $share->share_value, 2) }}</strong></div>
                <div class="field"><span>Cantidad acciones</span><strong>{{ rtrim(rtrim(number_format((float) $share->shares_quantity, 4, '.', ''), '0'), '.') }}</strong></div>
                <div class="field"><span>Capital para acciones</span><strong>S/ {{ number_format((float) ($share->share_capital_amount ?? $share->amount), 2) }}</strong></div>
                <div class="field"><span>Solidaridad</span><strong>S/ {{ number_format((float) $share->solidarity_amount, 2) }}</strong></div>
                <div class="field"><span>Gastos administrativos</span><strong>S/ {{ number_format((float) $share->administrative_fee_amount, 2) }}</strong></div>
                <div class="field"><span>Total recibido</span><strong>S/ {{ number_format((float) ($share->total_paid ?? $share->amount), 2) }}</strong></div>
                <div class="field"><span>Metodo pago</span><strong>{{ ucfirst($share->payment_method ?? '-') }}</strong></div>
                <div class="field"><span>Referencia</span><strong>{{ $share->payment_method === 'efectivo' ? 'No aplica' : ($share->payment_reference ?: '-') }}</strong></div>
                <div class="voucher-box">
                    <div class="voucher-preview">
                        @if ($voucher['status'] === 'available' && $voucher['type'] === 'image')
                            <img src="{{ $voucher['preview_url'] }}" alt="Comprobante">
                        @elseif ($voucher['status'] === 'available' && $voucher['type'] === 'pdf')
                            <i>PDF</i>
                        @else
                            <i>-</i>
                        @endif
                    </div>
                    <div class="voucher-info">
                        <span>Comprobante</span>
                        <strong>{{ $voucher['message'] }}</strong>
                        @if ($voucher['status'] === 'available')
                            <a href="{{ $voucher['url'] }}" target="_blank" rel="noopener">
                                {{ $voucher['type'] === 'pdf' ? 'Ver PDF / Descargar' : 'Ver comprobante' }}
                            </a>
                        @endif
                    </div>
                </div>
                <div class="field full"><span>Observacion</span><strong>{{ $share->observation ?? '-' }}</strong></div>
                <div class="field"><span>Usuario que registro</span><strong>{{ $share->creator?->name ?? '-' }}</strong></div>
                <div class="field"><span>Estado</span><strong>{{ ucfirst($share->status) }}</strong></div>
            </div>
        </div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>
