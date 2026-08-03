<div class="card shadow-sm border-0 rounded-lg mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between">
            <div>
                <h5 class="mb-1">{{ $member->full_name }}</h5>
                <div class="text-muted">{{ $member->code }} - DNI {{ $member->dni }}</div>
            </div>
            <div class="text-right">
                <span class="badge badge-{{ $member->status === 'vigente' ? 'success' : 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $member->status)) }}</span>
                <div class="small text-muted mt-1">Ingreso: {{ optional($member->admission_date)->format('d/m/Y') ?? '-' }}</div>
            </div>
        </div>
    </div>
</div>

@foreach ([
    'Aportes' => $member->shares,
    'Prestamos' => $member->loans,
    'Cobros' => $member->loanPayments,
    'Utilidades' => $member->profitDistributionDetails,
    'Retiros / cierres' => $member->accountClosures,
] as $title => $items)
    <div class="card shadow-sm border-0 rounded-lg mb-3">
        <div class="card-header bg-white">
            <h3 class="card-title mb-0">{{ $title }}</h3>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>{{ $item->code ?? $item->loan_number ?? $item->payment_number ?? '-' }}</td>
                                <td>{{ optional($item->date ?? $item->start_date ?? $item->payment_date ?? $item->closure_date ?? $item->created_at)->format('d/m/Y') }}</td>
                                <td>{{ $item->concept ?? $item->purpose ?? $item->observation ?? '-' }}</td>
                                <td>S/ {{ number_format((float) ($item->amount ?? $item->approved_amount ?? $item->profit_amount ?? $item->final_balance ?? 0), 2) }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $item->status ?? '-')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Sin registros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endforeach
