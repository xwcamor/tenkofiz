<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Sistema') | Asistencia Facial</title>
    <!-- AdminLTE 3 + plugins (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.4.2/css/buttons.bootstrap4.min.css">
    <style>
        /* Barra de carga superior al navegar */
        #barra-carga { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: #007bff; z-index: 99999; transition: width .4s ease; }
        a.nav-bloqueado { pointer-events: none; opacity: .65; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar superior -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a></li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item d-flex align-items-center mr-3 text-muted">
                <i class="fas fa-user-circle mr-1"></i> {{ auth()->user()->name }}
                <span class="badge badge-primary ml-2">{{ auth()->user()->perfil?->nombre }}</span>
            </li>
            <li class="nav-item">
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="btn btn-outline-danger btn-sm mt-1"><i class="fas fa-sign-out-alt"></i> Salir</button>
                </form>
            </li>
        </ul>
    </nav>

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        @php($ajusteEmpresa = \App\Models\Ajuste::obtener())
        <a href="{{ route('dashboard') }}" class="brand-link">
            @if($ajusteEmpresa->logo)
                <img src="{{ asset($ajusteEmpresa->logo) }}" alt="logo" class="brand-image img-circle elevation-2" style="opacity:.9">
            @else
                <i class="fas fa-id-badge brand-image ml-3 mt-2"></i>
            @endif
            <span class="brand-text font-weight-light">{{ \Illuminate\Support\Str::limit($ajusteEmpresa->empresa, 18) }}</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
                        </a>
                    </li>

                    @if(auth()->user()->tienePerfil('Administrador','Supervisor'))
                        <li class="nav-header">GESTIÓN</li>
                        <li class="nav-item">
                            <a href="{{ route('empleados.index') }}" class="nav-link {{ request()->routeIs('empleados.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-users"></i><p>Empleados</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('asistencias.index') }}" class="nav-link {{ request()->routeIs('asistencias.index') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-calendar-check"></i><p>Asistencias</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('reportes.index') }}" class="nav-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-chart-bar"></i><p>Reportes</p>
                            </a>
                        </li>
                    @endif

                    <li class="nav-header">MI CUENTA</li>
                    <li class="nav-item">
                        <a href="{{ route('vacaciones.index') }}" class="nav-link {{ request()->routeIs('vacaciones.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-umbrella-beach"></i><p>Vacaciones</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('asistencias.mias') }}" class="nav-link {{ request()->routeIs('asistencias.mias') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-check"></i><p>Mis asistencias</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('justificaciones.index') }}" class="nav-link {{ request()->routeIs('justificaciones.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-file-medical"></i><p>Justificaciones</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('calendario.index') }}" class="nav-link {{ request()->routeIs('calendario.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-alt"></i><p>Calendario</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('reportes.miFicha') }}" target="_blank" class="nav-link">
                            <i class="nav-icon fas fa-file-pdf"></i><p>Mi ficha (PDF)</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('cuenta.password') }}" class="nav-link {{ request()->routeIs('cuenta.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-key"></i><p>Cambiar contraseña</p>
                        </a>
                    </li>

                    @if(auth()->user()->tienePerfil('Administrador'))
                        <li class="nav-header">ADMINISTRACIÓN</li>
                        <li class="nav-item">
                            <a href="{{ route('usuarios.index') }}" class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-user-cog"></i><p>Usuarios</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('perfiles.index') }}" class="nav-link {{ request()->routeIs('perfiles.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-shield-alt"></i><p>Perfiles</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('horarios.index') }}" class="nav-link {{ request()->routeIs('horarios.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-clock"></i><p>Horarios</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('feriados.index') }}" class="nav-link {{ request()->routeIs('feriados.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-calendar-times"></i><p>Feriados</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('auditorias.index') }}" class="nav-link {{ request()->routeIs('auditorias.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-shield-alt"></i><p>Auditoría</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('ajustes.edit') }}" class="nav-link {{ request()->routeIs('ajustes.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-cog"></i><p>Ajustes</p>
                            </a>
                        </li>
                    @endif

                    <li class="nav-header">KIOSCO</li>
                    <li class="nav-item">
                        <a href="{{ route('kiosco') }}" target="_blank" class="nav-link">
                            <i class="nav-icon fas fa-camera"></i><p>Kiosco de marcado</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Contenido -->
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid d-flex justify-content-between">
                <h1 class="h4">@yield('titulo')</h1>
                @yield('boton-header')
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                @yield('contenido')
            </div>
        </section>
    </div>

    <footer class="main-footer text-sm">
        <strong>Sistema de Control de Asistencia con Reconocimiento Facial</strong> — Proyecto de Titulación 2026
    </footer>
</div>

<!-- Scripts: jQuery, Bootstrap, AdminLTE, DataTables, SweetAlert2, Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
    // Plugin DataTables en toda tabla con clase .tabla-datos (idioma español)
    $(function () {
        $('.tabla-datos').DataTable({
            responsive: true,
            pageLength: 10,
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' }
        });
    });

    // Tabla de reportes: con botones de exportación (Excel / Imprimir / Copiar)
    $(function () {
        $('.tabla-reporte').DataTable({
            responsive: true,
            pageLength: 25,
            dom: "<'row'<'col-sm-6'B><'col-sm-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'i><'col-sm-7'p>>",
            buttons: [
                { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm' },
                { extend: 'print', text: '<i class="fas fa-print"></i> Imprimir', className: 'btn btn-secondary btn-sm' },
                { extend: 'copyHtml5', text: '<i class="fas fa-copy"></i> Copiar', className: 'btn btn-info btn-sm' }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' }
        });
    });

    // Prevenir múltiples clicks en enlaces (botones "Nuevo", editar, menú, etc.)
    (function () {
        const barra = document.createElement('div');
        barra.id = 'barra-carga';
        document.body.appendChild(barra);

        document.addEventListener('click', function (e) {
            const a = e.target.closest('a');
            // Ignorar: sin href, anclas, nueva pestaña, o elementos de colapso/toggle
            if (!a || !a.href || a.target === '_blank' || a.getAttribute('href').startsWith('#') || a.dataset.toggle) return;

            // Si ya se hizo click, bloquear los siguientes
            if (a.dataset.navegando) { e.preventDefault(); return; }
            a.dataset.navegando = '1';
            a.classList.add('nav-bloqueado');

            // Barra de progreso visual mientras carga la siguiente página
            barra.style.width = '70%';

            // Liberar por si el usuario vuelve con el botón atrás (bfcache)
            setTimeout(() => { delete a.dataset.navegando; a.classList.remove('nav-bloqueado'); barra.style.width = '0'; }, 6000);
        }, true);

        window.addEventListener('pageshow', () => {
            barra.style.width = '0';
            document.querySelectorAll('a.nav-bloqueado').forEach(a => { delete a.dataset.navegando; a.classList.remove('nav-bloqueado'); });
        });
    })();

    // Protección anti doble submit: bloquea el botón y muestra spinner al enviar
    $(document).on('submit', 'form:not(.form-eliminar)', function () {
        const botones = $(this).find('button[type="submit"], button:not([type])');
        setTimeout(() => {
            botones.prop('disabled', true)
                   .prepend('<span class="spinner-border spinner-border-sm mr-1"></span>');
        }, 10);
    });

    // Plugin SweetAlert2 para confirmar eliminaciones
    $(document).on('submit', '.form-eliminar', function (e) {
        e.preventDefault();
        const form = this;
        Swal.fire({
            title: '¿Está seguro?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(r => { if (r.isConfirmed) form.submit(); });
    });

    // Notificaciones de sesión con SweetAlert2 (toast)
    @if(session('ok'))
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: @json(session('ok')), showConfirmButton: false, timer: 3000 });
    @endif
    @if(session('error'))
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: @json(session('error')), showConfirmButton: false, timer: 4000 });
    @endif
</script>
@stack('scripts')
</body>
</html>
