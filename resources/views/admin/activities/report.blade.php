<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte {{ $activity->code }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#222;margin:32px}
        h1{font-size:22px;margin:0 0 4px}
        h2{font-size:16px;margin-top:24px;border-bottom:1px solid #ddd;padding-bottom:6px}
        table{width:100%;border-collapse:collapse;margin-top:10px;font-size:12px}
        th,td{border:1px solid #ddd;padding:7px;text-align:left}
        th{background:#f3f4f6}
        .summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:18px 0}
        .box{border:1px solid #ddd;padding:10px}
        .box span{display:block;color:#666;font-size:12px}
        .box strong{font-size:17px}
        @media print{button{display:none}}
    </style>
</head>
<body>
    @empty($pdfMode)<button onclick="window.print()">Imprimir</button>@endempty
    <h1>Reporte de actividad {{ $activity->code }}</h1>
    <div>{{ $activity->name }} - {{ optional($activity->activity_date)->format('d/m/Y') }}</div>
    <div>Generado: {{ now()->format('d/m/Y H:i') }}</div>

    <div class="summary">
        <div class="box"><span>Total ingresos</span><strong>S/ {{ number_format((float) $activity->total_income, 2) }}</strong></div>
        <div class="box"><span>Total egresos</span><strong>S/ {{ number_format((float) $activity->total_expense, 2) }}</strong></div>
        <div class="box"><span>Utilidad</span><strong>S/ {{ number_format((float) $activity->profit, 2) }}</strong></div>
    </div>

    <h2>Datos</h2>
    <p><strong>Estado:</strong> {{ ucfirst($activity->status) }}<br><strong>Descripcion:</strong> {{ $activity->description ?: '-' }}<br><strong>Usuario que registro:</strong> {{ $activity->creator?->name ?? '-' }}<br><strong>Cierre:</strong> {{ optional($activity->closed_at)->format('d/m/Y H:i') ?? '-' }} {{ $activity->closer?->name ? 'por '.$activity->closer->name : '' }}</p>

    @foreach(['ingreso' => 'Ingresos', 'egreso' => 'Egresos', 'anulado' => 'Movimientos anulados'] as $type => $title)
        <h2>{{ $title }}</h2>
        @php
            $rows = $type === 'anulado'
                ? $activity->movements->where('status', 'anulado')
                : $activity->movements->where('status', 'registrado')->where('type', $type);
        @endphp
        <table>
            <thead><tr><th>Codigo</th><th>Fecha</th><th>Socio</th><th>Concepto</th><th>Monto</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse($rows as $movement)
                    <tr><td>{{ $movement->code }}</td><td>{{ optional($movement->movement_date ?: $movement->date)->format('d/m/Y') }}</td><td>{{ $movement->member?->full_name ?? '-' }}</td><td>{{ $movement->concept }}</td><td>S/ {{ number_format((float) $movement->amount, 2) }}</td><td>{{ ucfirst($movement->status) }}</td></tr>
                @empty
                    <tr><td colspan="6">Sin registros.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
</body>
</html>
