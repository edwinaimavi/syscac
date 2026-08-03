@extends('layouts.app')

@section('subtitle', 'Socios')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>
                    <i class="fas fa-users"></i> Socios

                    @can('admin.socios.create')
                        <button class="btn btn-app bg-dark" type="button" data-toggle="modal" data-target="#memberModal">
                            <i class="fas fa-plus-circle"></i> Nuevo socio
                        </button>
                    @endcan
                </h1>
            </div>

            <div class="col-sm-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item">
                            <a href="{{ route('home') }}">
                                <i class="fa fa-fw fa-house-user"></i> Home
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <i class="fas fa-users"></i> Socios
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tableMember" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Foto</th>
                            <th>Codigo</th>
                            <th>DNI</th>
                            <th>Socio</th>
                            <th>Telefono</th>
                            <th>Tipo socio</th>
                            <th>Estado civil</th>
                            <th>Fecha ingreso</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    @include('admin.members.partials.modal')
    @include('admin.members.partials.detail-modal')
@stop

@push('js')
    <script>
        window.memberRoutes = {
            list: "{{ route('admin.socios.list') }}",
            store: "{{ route('admin.socios.store') }}",
            base: "{{ url('admin/socios') }}",
            nextCode: "{{ route('admin.socios.next-code') }}",
            consultarDni: "{{ url('admin/socios/consultar-dni') }}",
            verifyDni: "{{ url('admin/socios/verificar-dni') }}",
            guarantorStore: "{{ route('admin.avales.store') }}",
            guarantorList: "{{ route('admin.avales.list') }}",
            guarantorSelect: "{{ route('admin.socios.buscar-avales') }}",
            defaultAvatar: "https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg"
        };

        function previewMemberImage(event) {
            const input = event.target;
            if (!input.files.length) return;

            const objectURL = URL.createObjectURL(input.files[0]);
            $('#memberImgPreview').attr('src', objectURL);
        }
    </script>

    @vite(['resources/js/pages/member.js'])
@endpush
