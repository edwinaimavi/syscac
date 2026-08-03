@extends('adminlte::master')

@php
    $loginUrl = config('adminlte.login_url', 'login');
    $registerUrl = config('adminlte.register_url', 'register');
    $passResetUrl = config('adminlte.password_reset_url', 'password/reset');

    if (config('adminlte.use_route_url', false)) {
        $loginUrl = $loginUrl ? route($loginUrl) : '';
        $registerUrl = $registerUrl ? route($registerUrl) : '';
        $passResetUrl = $passResetUrl ? route($passResetUrl) : '';
    } else {
        $loginUrl = $loginUrl ? url($loginUrl) : '';
        $registerUrl = $registerUrl ? url($registerUrl) : '';
        $passResetUrl = $passResetUrl ? url($passResetUrl) : '';
    }
@endphp

@section('title', 'Iniciar sesión | SysCaC')
@section('classes_body', 'login-page syscac-login-page')

@section('adminlte_css_pre')
    <link rel="stylesheet" href="{{ asset('vendor/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/syscac-login.css') }}?v={{ filemtime(public_path('css/syscac-login.css')) }}">
@stop

@section('body')
    <main class="sys-login-wrapper">
        <div class="sys-login-card">
            <section class="sys-login-brand-panel" aria-label="Presentación de SysCaC">
                <span class="sys-login-orb sys-login-orb-one" aria-hidden="true"></span>
                <span class="sys-login-orb sys-login-orb-two" aria-hidden="true"></span>

                <div class="sys-login-brand-content">
                    <div class="sys-login-logo-box">
                        <img src="{{ asset('vendor/adminlte/dist/img/logo1.png') }}"
                             alt="Asociación CAC Promoción 90 JP">
                    </div>
                    <span class="sys-login-eyebrow">Asociación CAC Promoción 90 JP</span>
                    <h1>SysCaC</h1>
                    <h2>Gestión integral para asociaciones CAC</h2>
                    <p>Control de socios, aportes, préstamos, caja y utilidades en un solo lugar.</p>
                </div>

                <div class="sys-login-brand-footer">
                    <i class="fas fa-shield-alt" aria-hidden="true"></i>
                    <span>Acceso seguro al sistema administrativo</span>
                </div>
            </section>

            <section class="sys-login-form-panel">
                <div class="sys-login-form-heading">
                    <span class="sys-login-form-icon"><i class="fas fa-user-lock" aria-hidden="true"></i></span>
                    <div>
                        <h2>Iniciar sesión</h2>
                        <p class="login-subtitle">Accede con tus credenciales</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="sys-login-alert" role="alert">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        <div>
                            <strong>No pudimos iniciar la sesión</strong>
                            <span>Revisa tus credenciales e inténtalo nuevamente.</span>
                        </div>
                    </div>
                @endif

                <form action="{{ $loginUrl }}" method="post" class="sys-login-form">
                    @csrf

                    <div class="form-group">
                        <label for="email">Correo electrónico</label>
                        <div class="input-group">
                            <input id="email" type="email" name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" placeholder="nombre@correo.com"
                                   autocomplete="email" autofocus required>
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                            </div>
                            @error('email')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="input-group">
                            <input id="password" type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   placeholder="Ingresa tu contraseña" autocomplete="current-password" required>
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-lock" aria-hidden="true"></i></span>
                            </div>
                            @error('password')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>

                    <div class="sys-login-options">
                        <div class="icheck-primary">
                            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label for="remember">Recordarme</label>
                        </div>
                        @if ($passResetUrl)
                            <a href="{{ $passResetUrl }}">¿Olvidaste tu contraseña?</a>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-login-sys btn-block">
                        <span>Acceder al sistema</span>
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                @if ($registerUrl)
                    <p class="sys-login-register">¿Necesitas una cuenta? <a href="{{ $registerUrl }}">Solicitar registro</a></p>
                @endif

                <p class="sys-login-copyright">© {{ date('Y') }} SysCaC · Gestión CAC</p>
            </section>
        </div>
    </main>
@stop

