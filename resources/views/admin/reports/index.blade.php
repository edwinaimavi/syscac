@extends('layouts.app')

@section('subtitle', 'Reportes')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-chart-bar"></i> Reportes</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li>
                    <li class="breadcrumb-item active">Reportes</li>
                </ol>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="report-dashboard-grid">
        @foreach ($cards as $card)
            @can($card['permission'])
                <div class="report-access-card">
                    <div class="report-access-icon"><i class="{{ $card['icon'] }}"></i></div>
                    <div class="report-access-body">
                        <h3>{{ $card['title'] }}</h3>
                        <p>{{ $card['description'] }}</p>
                    </div>
                    <a href="{{ $card['route'] }}" class="btn btn-dark btn-sm">
                        <i class="fas fa-eye mr-1"></i> Ver reporte
                    </a>
                </div>
            @endcan
        @endforeach
    </div>
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
@endpush
