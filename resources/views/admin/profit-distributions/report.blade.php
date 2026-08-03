<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte {{ $distribution->code }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#222;margin:32px} h1{font-size:22px;margin:0 0 6px} h2{font-size:16px;margin-top:22px;border-bottom:1px solid #ddd;padding-bottom:6px}
        table{width:100%;border-collapse:collapse;margin-top:10px;font-size:12px} th,td{border:1px solid #ddd;padding:7px;text-align:left} th{background:#f3f4f6}
        .summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:18px 0}.box{border:1px solid #ddd;padding:10px}.box span{display:block;color:#666;font-size:12px}.box strong{font-size:16px}
        @media print{button{display:none}}
    </style>
</head>
<body>
    @empty($pdfMode)<button onclick="window.print()">Imprimir</button>@endempty
    <h1>Reporte de utilidades {{ $distribution->code }}</h1>
    <div>Periodo: {{ ($distribution->period_month ? str_pad($distribution->period_month, 2, '0', STR_PAD_LEFT).'/' : '') . $distribution->period_year }}</div>
    <div>Generado: {{ now()->format('d/m/Y H:i') }}</div>
    <div>Calculado: {{ optional($distribution->calculated_at ?: $distribution->created_at)->format('d/m/Y H:i') }} por {{ $distribution->calculator?->name ?: $distribution->creator?->name ?: '-' }}</div>
    <div>Aprobado: {{ optional($distribution->approved_at)->format('d/m/Y H:i') ?: '-' }} por {{ $distribution->approver?->name ?: '-' }}</div>
    <div class="summary">
        <div class="box"><span>Utilidad total</span><strong>S/ {{ number_format((float) $distribution->total_profit, 2) }}</strong></div>
        <div class="box"><span>Total acción-mes</span><strong>{{ number_format((float) ((float) $distribution->total_action_month > 0 ? $distribution->total_action_month : $distribution->total_shares), 4) }}</strong></div>
        <div class="box"><span>Utilidad por acción-mes</span><strong>S/ {{ number_format((float) ((float) $distribution->profit_per_action_month > 0 ? $distribution->profit_per_action_month : $distribution->profit_per_share), 8) }}</strong></div>
        <div class="box"><span>Estado</span><strong>{{ ucfirst($distribution->status) }}</strong></div>
    </div>
    <h2>Detalle por socio</h2>
    <table>
        <thead><tr><th>Socio</th><th>DNI</th><th>Acciones</th><th>Meses</th><th>Acción-mes</th><th>Participación</th><th>Utilidad</th><th>Pagado</th><th>Pendiente</th><th>Estado</th></tr></thead>
        <tbody>
            @foreach($distribution->details as $detail)
                <tr><td>{{ $detail->member?->full_name }}</td><td>{{ $detail->member?->dni }}</td><td>{{ number_format((float) ((float) $detail->actions_considered > 0 ? $detail->actions_considered : $detail->shares_quantity), 4) }}</td><td>{{ $detail->months_considered }}</td><td>{{ number_format((float) ((float) $detail->action_month > 0 ? $detail->action_month : $detail->shares_quantity), 4) }}</td><td>{{ number_format((float) $detail->participation_percentage, 4) }}%</td><td>S/ {{ number_format((float) $detail->profit_amount, 2) }}</td><td>S/ {{ number_format((float) $detail->paid_amount, 2) }}</td><td>S/ {{ number_format((float) $detail->profit_amount - (float) $detail->paid_amount, 2) }}</td><td>{{ ucfirst($detail->status) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
