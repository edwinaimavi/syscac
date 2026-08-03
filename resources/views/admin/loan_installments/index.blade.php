@extends('layouts.app')

@section('subtitle', 'Cronograma de cuotas')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-calendar-alt"></i><span>Cronograma de cuotas<small>Consulta y seguimiento de cuotas programadas</small></span></h1>
            </div>
            <div class="col-sm-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-calendar-alt"></i> Cronograma de cuotas</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="card shadow-sm border-0 rounded-lg mb-3">
        <div class="card-body p-3">
            <div class="form-row align-items-end">
                <div class="form-group col-md-4">
                    <label class="small font-weight-bold text-secondary">Prestamo</label>
                    <select id="installment_filter_loan_id" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($loans as $loan)
                            <option value="{{ $loan->id }}">{{ $loan->loan_number }} - {{ $loan->member?->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label class="small font-weight-bold text-secondary">Socio</label>
                    <select id="installment_filter_member_id" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Estado</label>
                    <select id="installment_filter_status" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="parcial">Parcial</option>
                        <option value="pagado">Pagado</option>
                        <option value="vencido">Vencido</option>
                    </select>
                </div>
                <div class="form-group col-md-2"><button type="button" class="btn btn-light border btn-block" id="btnClearInstallmentFilters"><i class="fas fa-undo"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tableLoanInstallment" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
                    <thead><tr><th>#</th><th>Prestamo</th><th>Socio</th><th>Cuota</th><th>Vencimiento</th><th>Capital</th><th>Interes</th><th>Monto cuota</th><th>Pendiente</th><th>Estado</th></tr></thead>
                </table>
            </div>
        </div>
    </div>
@stop

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = $('#tableLoanInstallment').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.cuotas.list') }}",
                    data: function (data) {
                        data.loan_id = $('#installment_filter_loan_id').val();
                        data.member_id = $('#installment_filter_member_id').val();
                        data.status = $('#installment_filter_status').val();
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'loan_number', name: 'loan.loan_number' },
                    { data: 'member_name', name: 'loan.member.full_name' },
                    { data: 'installment_number', name: 'installment_number' },
                    { data: 'due_date', name: 'due_date' },
                    { data: 'principal_amount', name: 'principal_amount' },
                    { data: 'interest_amount', name: 'interest_amount' },
                    { data: 'installment_amount', name: 'installment_amount' },
                    { data: 'remaining_amount', name: 'remaining_amount' },
                    { data: 'status', name: 'status', orderable: false, searchable: false }
                ],
                responsive: true,
                language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
            });

            $('#installment_filter_loan_id, #installment_filter_member_id, #installment_filter_status').on('change', function () {
                table.ajax.reload();
            });

            $('#btnClearInstallmentFilters').on('click', function () {
                $('#installment_filter_loan_id, #installment_filter_member_id, #installment_filter_status').val('');
                table.ajax.reload();
            });
        });
    </script>
@endpush
