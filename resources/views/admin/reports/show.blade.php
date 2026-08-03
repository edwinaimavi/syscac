@extends('layouts.app')

@section('subtitle', $definition['title'])

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8">
                <h1><i class="{{ $definition['icon'] }}"></i> {{ $definition['title'] }}</h1>
                <p class="text-muted mb-0">{{ $definition['description'] }}</p>
            </div>
            <div class="col-sm-4">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.reportes.index') }}">Reportes</a></li>
                    <li class="breadcrumb-item active">{{ $definition['title'] }}</li>
                </ol>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="report-toolbar mb-3">
        <button class="btn btn-light border" type="button" data-toggle="modal" data-target="#reportFiltersModal">
            <i class="fas fa-filter mr-1"></i> Filtros
        </button>
        @can('reportes.print')
        <a class="btn btn-light border" href="{{ route('admin.reportes.print', ['tipo' => $type] + request()->query()) }}" target="_blank">
            <i class="fas fa-print mr-1"></i> Imprimir
        </a>
        @endcan
        @can('reportes.pdf')
        <a class="btn btn-light border" href="{{ route('admin.reportes.pdf', ['tipo' => $type] + request()->query()) }}" target="_blank">
            <i class="fas fa-file-pdf mr-1"></i> PDF
        </a>
        @endcan
        @can('reportes.excel')
        <a class="btn btn-light border" href="{{ route('admin.reportes.excel', ['tipo' => $type] + request()->query()) }}">
            <i class="fas fa-file-csv mr-1"></i> CSV
        </a>
        @endcan
        <a class="btn btn-light border" href="{{ url()->current() }}">
            <i class="fas fa-undo mr-1"></i> Limpiar filtros
        </a>
    </div>

    <div class="report-summary-grid mb-3">
        @foreach ($report['summary'] as $label => $value)
            <div class="report-summary-card">
                <span>{{ $label }}</span>
                <strong>{!! $value !!}</strong>
            </div>
        @endforeach
    </div>

    @if ($type === 'historial-socio' && ($report['member'] ?? null))
        @include('admin.reports.partials.member-history', ['member' => $report['member']])
    @endif

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="reportTable" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
                    <thead>
                        <tr>
                            @foreach ($report['columns'] as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            <tr>
                                @foreach (array_keys($report['columns']) as $key)
                                    <td>{!! $row[$key] ?? '-' !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.reports.partials.filters')
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
@endpush

@push('js')
    @vite(['resources/js/pages/reports.js'])
@endpush
