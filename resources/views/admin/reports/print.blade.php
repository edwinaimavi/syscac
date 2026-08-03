<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $definition['title'] }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#222;margin:28px}
        h1{font-size:22px;margin:0 0 6px}
        .muted{color:#666;font-size:12px}
        .summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:18px 0}
        .box{border:1px solid #ddd;padding:10px}.box span{display:block;color:#666;font-size:12px}.box strong{font-size:15px}
        table{width:100%;border-collapse:collapse;font-size:11px}th,td{border:1px solid #ddd;padding:6px;text-align:left}th{background:#f3f4f6}
        button{margin-bottom:14px}
        @media print{button{display:none}.summary{grid-template-columns:repeat(4,1fr)}}
    </style>
</head>
<body>
    @empty($pdfMode)<button onclick="window.print()">Imprimir</button>@endempty
    <h1>SysCaC - {{ $definition['title'] }}</h1>
    <div class="muted">{{ $definition['description'] }}</div>
    <div class="muted">Generado: {{ $generatedAt->format('d/m/Y H:i') }} | Usuario: {{ $generatedBy }}</div>
    <div class="muted">
        Filtros:
        @forelse ($filterLabels as $label => $value)
            {{ $label }}: {{ $value }}{{ ! $loop->last ? ' | ' : '' }}
        @empty
            Sin filtros aplicados
        @endforelse
    </div>

    <div class="summary">
        @foreach ($report['summary'] as $label => $value)
            <div class="box"><span>{{ $label }}</span><strong>{!! $value !!}</strong></div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($report['columns'] as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach (array_keys($report['columns']) as $key)
                        <td>{!! $row[$key] ?? '-' !!}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}">No se encontraron registros para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
