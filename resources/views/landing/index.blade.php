<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SysCaC, sistema de caja y créditos para asociaciones.">
    <title>SysCaC | Caja y Créditos</title>
    <link rel="icon" href="{{ asset('vendor/adminlte/dist/img/logo1.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="shell nav-wrap">
            <a class="brand" href="{{ route('landing') }}" aria-label="Inicio SysCaC">
                <img src="{{ asset('vendor/adminlte/dist/img/logo1.png') }}" alt="Logo SysCaC">
                <span><strong>SysCaC</strong><small>Gestión para asociaciones</small></span>
            </a>
            <nav aria-label="Navegación principal">
                <a href="#modulos">Módulos</a><a href="#ventajas">Ventajas</a>
                <a class="login-link" href="{{ route('login') }}"><i class="far fa-user"></i> Iniciar sesión</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="shell hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow"><i class="fas fa-shield-alt"></i> Gestión segura y centralizada</span>
                    <h1>Sistema de Caja y Créditos para asociaciones</h1>
                    <p>Administra socios, aportes, préstamos, cobros, caja, utilidades y reportes en un solo lugar, con información clara para tomar mejores decisiones.</p>
                    <div class="hero-actions"><a class="button button-primary" href="{{ route('login') }}">Ingresar al sistema <i class="fas fa-arrow-right"></i></a><a class="button button-ghost" href="#modulos">Conocer módulos</a></div>
                    <div class="trust-row"><span><i class="fas fa-check"></i> Control financiero</span><span><i class="fas fa-check"></i> Trazabilidad</span><span><i class="fas fa-check"></i> Acceso seguro</span></div>
                </div>
                <div class="hero-visual" aria-label="Vista resumida del sistema">
                    <div class="visual-glow"></div>
                    <div class="system-window">
                        <div class="window-top"><span class="mini-brand"><img src="{{ asset('vendor/adminlte/dist/img/logo1.png') }}" alt=""> SysCaC</span><span class="avatar"><i class="far fa-user"></i></span></div>
                        <div class="window-body"><aside><i class="fas fa-chart-pie active"></i><i class="fas fa-users"></i><i class="fas fa-coins"></i><i class="fas fa-hand-holding-usd"></i><i class="fas fa-file-alt"></i></aside><div class="mock-content"><div class="mock-title"><span></span><small></small></div><div class="mock-stats"><span><i class="fas fa-wallet"></i><b></b></span><span><i class="fas fa-users"></i><b></b></span><span><i class="fas fa-chart-line"></i><b></b></span></div><div class="mock-panel"><div class="bars"><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="mock-list"><span></span><span></span><span></span><span></span></div></div></div></div>
                    </div>
                    <div class="floating-note note-one"><i class="fas fa-check-circle"></i><span><b>Operaciones al día</b><small>Control en tiempo real</small></span></div>
                    <div class="floating-note note-two"><i class="fas fa-lock"></i><span><b>Información segura</b><small>Acceso por permisos</small></span></div>
                </div>
            </div>
        </section>

        <section class="modules section" id="modulos">
            <div class="shell"><div class="section-heading"><span>Todo en un solo sistema</span><h2>Módulos para una gestión completa</h2><p>Cada operación conectada para mantener información consistente, auditable y disponible.</p></div>
                <div class="module-grid">
                    @foreach([['fas fa-users','Socios','Registro e historial de miembros.'],['fas fa-coins','Acciones y aportes','Capital y contribuciones al día.'],['fas fa-hand-holding-usd','Préstamos','Evaluación, aprobación y cronogramas.'],['fas fa-receipt','Cobros','Capital, intereses y moras cobradas.'],['fas fa-cash-register','Caja','Ingresos, egresos y saldo disponible.'],['fas fa-chart-pie','Utilidades','Cálculo y distribución transparente.'],['fas fa-chart-bar','Reportes','Información clara para decisiones.'],['fas fa-hands-helping','Solidaridad','Control independiente del fondo.'],['fas fa-building','Fondo administrativo','Seguimiento de recursos administrativos.']] as [$icon,$title,$text])
                    <article class="module-card"><span><i class="{{ $icon }}"></i></span><div><h3>{{ $title }}</h3><p>{{ $text }}</p></div></article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="advantages section" id="ventajas"><div class="shell advantages-grid"><div><span class="section-tag">Control con confianza</span><h2>Información ordenada para gestionar mejor</h2><p>SysCaC reúne la operación financiera y administrativa de la asociación, reduciendo tareas repetitivas y facilitando el seguimiento.</p><a href="{{ route('login') }}" class="text-link">Acceder a la plataforma <i class="fas fa-arrow-right"></i></a></div><div class="benefit-grid">@foreach([['fa-layer-group','Control centralizado'],['fa-search-dollar','Seguimiento de créditos'],['fa-chart-line','Distribución de utilidades'],['fa-history','Historial y auditoría'],['fa-file-invoice','Reportes claros'],['fa-user-shield','Seguridad de acceso']] as [$icon,$label])<div><i class="fas {{ $icon }}"></i><span>{{ $label }}</span></div>@endforeach</div></div></section>

        <section class="final-cta"><div class="shell cta-box"><div><span>Da el siguiente paso</span><h2>Gestiona tu asociación con orden y control</h2><p>Ingresa a SysCaC y centraliza tus operaciones desde hoy.</p></div><a class="button button-light" href="{{ route('login') }}">Ingresar al sistema <i class="fas fa-arrow-right"></i></a></div></section>
    </main>

    <footer><div class="shell footer-wrap"><div class="brand footer-brand"><img src="{{ asset('vendor/adminlte/dist/img/logo1.png') }}" alt="Logo SysCaC"><span><strong>SysCaC</strong><small>Sistema de Caja y Créditos</small></span></div><p>Gestión financiera clara para asociaciones.</p><small>© {{ now()->year }} SysCaC · Versión {{ config('app.version','1.0.0') }}</small></div></footer>
</body>
</html>
