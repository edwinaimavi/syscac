<?php

return [

    'title' => 'SysCaC',
    'title_prefix' => '',
    'title_postfix' => '',

    'use_ico_only' => false,
    'use_full_favicon' => false,

    'google_fonts' => [
        'allowed' => false,
    ],

    /*
    |------------------------------------------------------------------
    | Branding
    |------------------------------------------------------------------
    */
    'logo' => '<span class="syscac-brand-name"><b>Sys</b>CaC</span>',
    'logo_img' => 'vendor/adminlte/dist/img/logo1.png',
    'logo_img_class' => 'brand-image elevation-0 syscac-brand-logo',
    'logo_img_alt' => 'Asociación CAC Promoción 90 JP',

    'auth_logo' => [
        'enabled' => true,
        'img' => [
            'path' => 'vendor/adminlte/dist/img/logo1.png',
            'alt' => 'Asociación CAC Promoción 90 JP',
            'class' => 'syscac-auth-logo elevation-0',
            'width' => 92,
            'height' => 92,
        ],
    ],

    /*
    |------------------------------------------------------------------
    | Preloader
    |------------------------------------------------------------------
    */
    'preloader' => [
        'enabled' => true,
        'mode' => 'fullscreen',
        'img' => [
            'path' => 'vendor/adminlte/dist/img/logo1.png',
            'alt' => 'Cargando SysCaC...',
            'effect' => 'animation__pulse',
            'width' => 80,
            'height' => 80,
        ],
    ],

    /*
    |------------------------------------------------------------------
    | User Menu
    |------------------------------------------------------------------
    */
    'usermenu_enabled' => true,
    'usermenu_header' => true,
    'usermenu_header_class' => 'syscac-user-header',
    'usermenu_image' => false,
    'usermenu_desc' => false,
    'usermenu_profile_url' => false,

    /*
    |------------------------------------------------------------------
    | Layout
    |------------------------------------------------------------------
    */
    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => true,
    'layout_fixed_navbar' => true,
    'layout_fixed_footer' => null,
    'layout_light_mode' => null,

    /*
    |------------------------------------------------------------------
    | Auth Views
    |------------------------------------------------------------------
    */
    'classes_auth_card' => 'syscac-auth-card',
    'classes_auth_btn' => 'btn-primary',

    /*
    |------------------------------------------------------------------
    | Admin Panel Classes
    |------------------------------------------------------------------
    */
    'classes_body' => 'text-sm syscac-theme',
    'classes_sidebar' => 'sidebar-dark-primary elevation-0',
    'classes_topnav' => 'navbar-white navbar-light elevation-0',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container-fluid',

    /*
    |------------------------------------------------------------------
    | Sidebar
    |------------------------------------------------------------------
    */
    'sidebar_mini' => 'lg',
    'sidebar_collapse' => false,
    'sidebar_scrollbar_theme' => 'os-theme-dark',
    'sidebar_scrollbar_auto_hide' => 'l',
    'sidebar_nav_accordion' => true,
    'sidebar_nav_animation_speed' => 200,

    /*
    |------------------------------------------------------------------
    | URLs
    |------------------------------------------------------------------
    */
    'use_route_url' => false,
    'dashboard_url' => 'home',
    'logout_url' => 'logout',
    'login_url' => 'login',
    'register_url' => 'register',
    'profile_url' => 'admin/profile',

    /*
    |------------------------------------------------------------------
    | Menu
    |------------------------------------------------------------------
    */
    'menu' => [

        ['type' => 'fullscreen-widget', 'topnav_right' => true],

        ['header' => 'ADMINISTRACIÓN'],
        ['text' => 'Dashboard', 'url' => 'home', 'icon' => 'fas fa-chart-line'],

        [
            'text' => 'Usuarios',
            'icon' => 'fas fa-users-cog',
            'submenu' => [
                ['text' => 'Roles', 'url' => 'admin/roles', 'icon' => 'fas fa-user-shield', 'can' => 'admin.roles.index'],
                ['text' => 'Usuarios', 'url' => 'admin/users', 'icon' => 'fas fa-users', 'can' => 'admin.users.index'],
            ],
        ],

        ['header' => 'SISTEMA SYSCAC'],
        [
            'text' => 'Socios',
            'icon' => 'fas fa-users',
            'submenu' => [
                ['text' => 'Socios', 'url' => 'admin/socios', 'icon' => 'fas fa-users', 'can' => 'admin.socios.index'],
                ['text' => 'Avales / Garantes', 'url' => 'admin/avales', 'icon' => 'fas fa-user-shield', 'can' => 'avales.index'],
            ],
        ],
        ['text' => 'Retiro de socios', 'url' => 'admin/retiros-socios', 'icon' => 'fas fa-user-slash', 'can' => 'retiros.index'],
        ['text' => 'Familiares', 'url' => 'admin/familiares', 'icon' => 'fas fa-user-friends', 'can' => 'admin.socios.index'],
        ['text' => 'Acciones / Aportes', 'url' => 'admin/acciones', 'icon' => 'fas fa-coins', 'can' => 'admin.acciones.index'],
        [
            'text' => 'Prestamos',
            'icon' => 'fas fa-hand-holding-usd',
            'can' => 'admin.simulaciones.index',
            'submenu' => [
                ['text' => 'Simulador', 'url' => 'admin/prestamos/simulador', 'icon' => 'fas fa-calculator', 'can' => 'admin.simulaciones.index'],
                ['text' => 'Prestamos', 'url' => 'admin/prestamos', 'icon' => 'fas fa-file-invoice-dollar', 'can' => 'admin.prestamos.index'],
                ['text' => 'Cronograma de cuotas', 'url' => 'admin/cuotas', 'icon' => 'fas fa-calendar-alt', 'can' => 'admin.prestamos.index'],
                ['text' => 'Cobros', 'url' => 'admin/cobros', 'icon' => 'fas fa-cash-register', 'can' => 'admin.cobros.index'],
                ['text' => 'Configuración de mora', 'url' => 'admin/mora', 'icon' => 'fas fa-clock', 'can' => 'mora.index'],
                ['text' => 'Refinanciamientos', 'url' => 'admin/refinanciamientos', 'icon' => 'fas fa-sync-alt', 'can' => 'admin.refinanciamientos.index'],
            ],
        ],
        [
            'text' => 'Caja',
            'icon' => 'fas fa-cash-register',
            'can' => 'admin.caja.index',
            'submenu' => [
                ['text' => 'Movimientos de caja', 'url' => 'admin/caja', 'icon' => 'fas fa-cash-register', 'can' => 'admin.caja.index'],
                ['text' => 'Solidaridad', 'url' => 'admin/solidaridad', 'icon' => 'fas fa-hands-helping', 'can' => 'admin.solidaridad.index'],
                ['text' => 'Fondo administrativo', 'url' => 'admin/fondo-administrativo', 'icon' => 'fas fa-file-invoice-dollar', 'can' => 'admin.fondo-administrativo.index'],
                ['text' => 'Actividades', 'url' => 'admin/actividades', 'icon' => 'fas fa-calendar-check', 'can' => 'admin.actividades.index'],
            ],
        ],
        [
            'text' => 'Utilidades',
            'url' => 'admin/utilidades',
            'icon' => 'fas fa-chart-pie',
            'can' => 'admin.utilidades.index',
        ],
        [
            'text' => 'Reportes',
            'url' => 'admin/reportes',
            'icon' => 'fas fa-chart-bar',
            'can' => 'reportes.index',
            'submenu' => [
                ['text' => 'Socios', 'url' => 'admin/reportes/socios-vigentes', 'icon' => 'fas fa-users', 'can' => 'reportes.socios_vigentes'],
                ['text' => 'Acciones', 'url' => 'admin/reportes/acciones-general', 'icon' => 'fas fa-coins', 'can' => 'reportes.acciones_general'],
                ['text' => 'Prestamos', 'url' => 'admin/reportes/prestamos-activos', 'icon' => 'fas fa-hand-holding-usd', 'can' => 'reportes.prestamos_activos'],
                ['text' => 'Cobros', 'url' => 'admin/reportes/cobros-diarios', 'icon' => 'fas fa-receipt', 'can' => 'reportes.cobros_diarios'],
                ['text' => 'Caja', 'url' => 'admin/reportes/caja-general', 'icon' => 'fas fa-cash-register', 'can' => 'reportes.caja_general'],
                ['text' => 'Solidaridad', 'url' => 'admin/reportes/solidaridad', 'icon' => 'fas fa-hands-helping', 'can' => 'reportes.solidaridad'],
                ['text' => 'Actividades', 'url' => 'admin/reportes/actividades', 'icon' => 'fas fa-calendar-check', 'can' => 'reportes.actividades'],
                ['text' => 'Utilidades', 'url' => 'admin/reportes/utilidades-socio', 'icon' => 'fas fa-chart-pie', 'can' => 'reportes.utilidades_socio'],
                ['text' => 'Historial por socio', 'url' => 'admin/reportes/historial-socio', 'icon' => 'fas fa-address-card', 'can' => 'reportes.historial_socio'],
                ['text' => 'Historial crediticio', 'url' => 'admin/reportes/historial-crediticio', 'icon' => 'fas fa-chart-line', 'can' => 'credit-history.report'],
                ['text' => 'Mora', 'url' => 'admin/mora-reporte', 'icon' => 'fas fa-clock', 'can' => 'mora.report'],
            ],
        ],
        ['text' => 'Recibos', 'url' => 'admin/recibos', 'icon' => 'fas fa-receipt', 'can' => 'admin.recibos.index'],

        ['header' => 'CUENTA'],
        ['text' => 'Perfil', 'url' => 'admin/settings', 'icon' => 'fas fa-user'],
        ['text' => 'Cerrar sesión', 'url' => 'logout', 'icon' => 'fas fa-sign-out-alt'],
    ],

    /*
    |------------------------------------------------------------------
    | Menu Filters
    |------------------------------------------------------------------
    */
    'filters' => [
        JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter::class,
    ],

    /*
    |------------------------------------------------------------------
    | Plugins
    |------------------------------------------------------------------
    */

    'plugins' => [
        'Datatables' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css',
                ],
            ],
        ],
        'Select2' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.css',
                ],
            ],
        ],
        'Chartjs' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.bundle.min.js',
                ],
            ],
        ],
        'Sweetalert2' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.jsdelivr.net/npm/sweetalert2@8',
                ],
            ],
        ],
        'Pace' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/themes/blue/pace-theme-center-radar.min.css',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js',
                ],
            ],
        ],
    ],


    /*
    |------------------------------------------------------------------
    | Custom CSS
    |------------------------------------------------------------------
    */
    'custom_css' => [
        'css/cicosys-theme.css',
        'css/syscac-theme.css',
    ],

    'livewire' => false,
];
