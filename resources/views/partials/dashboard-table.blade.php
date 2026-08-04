<section class="dashboard-card table-card">
    <div class="table-card-heading"><div class="table-title"><span class="table-icon"><i class="{{ $icon }}"></i></span><div><span class="section-kicker">Actividad reciente</span><h3>{{ $title }}</h3><small>{{ $subtitle }}</small></div></div><a href="{{ $route }}" class="table-more">{{ $button }} <i class="fas fa-arrow-right"></i></a></div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>
    @forelse($rows as $row)
        <tr>
        @if($kind==='cash')
            <td>{{ $row->movement_date?->format('d/m/Y') }}</td><td><span class="type type-{{ $row->type }}">{{ ucfirst($row->type) }}</span></td><td>{{ ucfirst(str_replace('_',' ',$row->category)) }}</td><td class="concept">{{ $row->concept }}</td><td><strong>S/ {{ number_format($row->amount,2) }}</strong></td><td><span class="status">{{ ucfirst($row->status) }}</span></td>
        @elseif($kind==='installment')
            <td>{{ $row->loan?->member?->full_name ?? '—' }}</td><td>{{ $row->loan?->loan_number ?? '—' }}</td><td>#{{ $row->installment_number }}</td><td>{{ $row->due_date?->format('d/m/Y') }}</td><td><strong>S/ {{ number_format($row->remaining_amount,2) }}</strong></td><td><span class="status">{{ ucfirst($row->status) }}</span></td>
        @else
            <td>{{ $row->payment_date?->format('d/m/Y') }}</td><td>{{ $row->member?->full_name ?? '—' }}</td><td>{{ $row->loan?->loan_number ?? '—' }}</td><td><strong>S/ {{ number_format($row->amount,2) }}</strong></td><td>{{ ucfirst(str_replace('_',' ',$row->payment_method)) }}</td><td><span class="status">{{ ucfirst($row->status) }}</span></td>
        @endif
        </tr>
    @empty<tr><td colspan="6"><div class="table-empty"><span class="empty-icon"><i class="far fa-folder-open"></i></span><div><strong>No hay registros para mostrar</strong><small>Cuando existan movimientos, aparecerán en esta sección.</small></div></div></td></tr>@endforelse
    </tbody></table></div>
</section>
