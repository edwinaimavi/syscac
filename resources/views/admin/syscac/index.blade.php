@extends('layouts.app')

@section('subtitle', $title)

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="{{ $icon }}"></i> {{ $title }}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home') }}">
                            <i class="fa fa-fw fa-house-user"></i> Home
                        </a>
                    </li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body">
            <h5 class="mb-2">{{ $title }}</h5>
            <p class="mb-0 text-muted">{{ $description }}</p>
        </div>
    </div>
@stop
