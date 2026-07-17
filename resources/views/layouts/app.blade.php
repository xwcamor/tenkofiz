<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('System')) | {{ __('Facial Attendance') }}</title>
    <!-- AdminLTE 3 + plugins (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.4.2/css/buttons.bootstrap4.min.css">
    <style>
        /* Top loading bar while navigating */
        #loading-bar { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: #007bff; z-index: 99999; transition: width .4s ease; }
        a.nav-locked { pointer-events: none; opacity: .65; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
@php
    $currentUser = auth()->user();
    $canApproveVacations = $currentUser->hasModule('vacations_manage');
    $canReviewJustifications = $currentUser->hasModule('justifications_manage');
    $pendingVacations = $canApproveVacations ? \App\Models\Vacation::pending()->count() : 0;
    $pendingJustifications = $canReviewJustifications ? \App\Models\Justification::pending()->count() : 0;
    $pendingTotal = $pendingVacations + $pendingJustifications;
@endphp
<div class="wrapper">

    <!-- Top navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a></li>
        </ul>
        <ul class="navbar-nav ml-auto">
            @if($canApproveVacations || $canReviewJustifications)
                <!-- Pending approvals bell -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#" title="{{ __('Pending approvals') }}">
                        <i class="far fa-bell"></i>
                        @if($pendingTotal > 0)
                            <span class="badge badge-danger navbar-badge">{{ $pendingTotal }}</span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                        <span class="dropdown-item dropdown-header">{{ __('Pending approvals') }}</span>
                        <div class="dropdown-divider"></div>
                        @if($canApproveVacations)
                            <a href="{{ route('vacations.index', ['status' => 'PENDING']) }}" class="dropdown-item">
                                <i class="fas fa-umbrella-beach mr-2"></i> {{ __('Vacation requests') }}
                                <span class="float-right text-muted text-sm">{{ $pendingVacations }}</span>
                            </a>
                        @endif
                        @if($canReviewJustifications)
                            <a href="{{ route('justifications.index', ['status' => 'PENDING']) }}" class="dropdown-item">
                                <i class="fas fa-file-medical mr-2"></i> {{ __('Justifications') }}
                                <span class="float-right text-muted text-sm">{{ $pendingJustifications }}</span>
                            </a>
                        @endif
                        @if($pendingTotal === 0)
                            <span class="dropdown-item text-muted">{{ __('Nothing pending. Well done!') }}</span>
                        @endif
                    </div>
                </li>
            @endif
            <!-- Language switcher -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#" title="{{ __('Language') }}">
                    <i class="fas fa-globe"></i> {{ strtoupper(app()->getLocale()) }}
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    @foreach(['es' => 'Español', 'en' => 'English'] as $code => $label)
                        <form method="POST" action="{{ route('locale.switch') }}">@csrf
                            <input type="hidden" name="locale" value="{{ $code }}">
                            <button class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
            </li>
            <li class="nav-item d-flex align-items-center mr-3 text-muted">
                <i class="fas fa-user-circle mr-1"></i> {{ $currentUser->name }}
                <span class="badge badge-primary ml-2">{{ $currentUser->profile?->name }}</span>
            </li>
            <li class="nav-item">
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="btn btn-outline-danger btn-sm mt-1"><i class="fas fa-sign-out-alt"></i> {{ __('Sign out') }}</button>
                </form>
            </li>
        </ul>
    </nav>

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        @php($companySetting = app_setting())
        <a href="{{ route('dashboard') }}" class="brand-link">
            @if($companySetting->logo)
                <img src="{{ asset($companySetting->logo) }}" alt="logo" class="brand-image img-circle elevation-2" style="opacity:.9">
            @else
                <i class="fas fa-id-badge brand-image ml-3 mt-2"></i>
            @endif
            <span class="brand-text font-weight-light">{{ \Illuminate\Support\Str::limit($companySetting->company_name, 18) }}</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i><p>{{ __('Dashboard') }}</p>
                        </a>
                    </li>

                    @if($currentUser->hasAnyModule('employees', 'attendances', 'reports'))
                        <li class="nav-header">{{ __('MANAGEMENT') }}</li>
                        @if($currentUser->hasModule('employees'))
                            <li class="nav-item">
                                <a href="{{ route('employees.index') }}" class="nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-users"></i><p>{{ __('Employees') }}</p>
                                </a>
                            </li>
                        @endif
                        @if($currentUser->hasModule('attendances'))
                            <li class="nav-item">
                                <a href="{{ route('attendances.index') }}" class="nav-link {{ request()->routeIs('attendances.index') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-calendar-check"></i><p>{{ __('Attendance') }}</p>
                                </a>
                            </li>
                        @endif
                        @if($currentUser->hasModule('reports'))
                            <li class="nav-item">
                                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-chart-bar"></i><p>{{ __('Reports') }}</p>
                                </a>
                            </li>
                        @endif
                    @endif

                    <li class="nav-header">{{ __('MY ACCOUNT') }}</li>
                    <li class="nav-item">
                        <a href="{{ route('vacations.index') }}" class="nav-link {{ request()->routeIs('vacations.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-umbrella-beach"></i><p>{{ __('Vacations') }}
                                @if($pendingVacations > 0)<span class="badge badge-danger right">{{ $pendingVacations }}</span>@endif
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('attendances.mine') }}" class="nav-link {{ request()->routeIs('attendances.mine') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-check"></i><p>{{ __('My attendance') }}</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('justifications.index') }}" class="nav-link {{ request()->routeIs('justifications.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-file-medical"></i><p>{{ __('Justifications') }}
                                @if($pendingJustifications > 0)<span class="badge badge-danger right">{{ $pendingJustifications }}</span>@endif
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('calendar.index') }}" class="nav-link {{ request()->routeIs('calendar.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-alt"></i><p>{{ __('Calendar') }}</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('reports.mySheet') }}" target="_blank" class="nav-link">
                            <i class="nav-icon fas fa-file-pdf"></i><p>{{ __('My sheet (PDF)') }}</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('account.edit') }}" class="nav-link {{ request()->routeIs('account.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-cog"></i><p>{{ __('My account') }}</p>
                        </a>
                    </li>

                    @if($currentUser->hasAnyModule('users', 'profiles', 'schedules', 'holidays', 'audit_logs', 'settings'))
                        <li class="nav-header">{{ __('ADMINISTRATION') }}</li>
                        @if($currentUser->hasModule('users'))
                            <li class="nav-item">
                                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-user-cog"></i><p>{{ __('Users') }}</p>
                                </a>
                            </li>
                        @endif
                        @if($currentUser->hasModule('profiles'))
                            <li class="nav-item">
                                <a href="{{ route('profiles.index') }}" class="nav-link {{ request()->routeIs('profiles.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-shield-alt"></i><p>{{ __('Profiles') }}</p>
                                </a>
                            </li>
                        @endif
                        @if($currentUser->hasModule('schedules'))
                            <li class="nav-item">
                                <a href="{{ route('schedules.index') }}" class="nav-link {{ request()->routeIs('schedules.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-clock"></i><p>{{ __('Schedules') }}</p>
                                </a>
                            </li>
                        @endif
                        @if($currentUser->hasModule('holidays'))
                            <li class="nav-item">
                                <a href="{{ route('holidays.index') }}" class="nav-link {{ request()->routeIs('holidays.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-calendar-times"></i><p>{{ __('Holidays') }}</p>
                                </a>
                            </li>
                        @endif
                        @if($currentUser->hasModule('audit_logs'))
                            <li class="nav-item">
                                <a href="{{ route('audit.index') }}" class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-shield-alt"></i><p>{{ __('Audit log') }}</p>
                                </a>
                            </li>
                        @endif
                        @if($currentUser->hasModule('settings'))
                            <li class="nav-item">
                                <a href="{{ route('settings.edit') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-cog"></i><p>{{ __('Settings') }}</p>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if($currentUser->hasModule('settings'))
                        <li class="nav-header">{{ __('KIOSK') }}</li>
                        <li class="nav-item">
                            <a href="{{ route('kiosk', app_setting()->kiosk_token ? ['token' => app_setting()->kiosk_token] : []) }}" target="_blank" class="nav-link">
                                <i class="nav-icon fas fa-camera"></i><p>{{ __('Marking kiosk') }}</p>
                            </a>
                        </li>
                    @endif
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content -->
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid d-flex justify-content-between">
                <h1 class="h4">@yield('title')</h1>
                @yield('header-button')
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>

    <footer class="main-footer text-sm">
        <strong>{{ __('Attendance Control System with Facial Recognition') }}</strong>
        <span class="float-right text-muted">{{ __('Times shown in') }}: {{ user_timezone() }}</span>
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
    const DATATABLE_LANG = @json(app()->getLocale() === 'es' ? ['url' => 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'] : new stdClass());

    // DataTables on every table with .data-table (client-side; used for small catalogs)
    $(function () {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 10,
            language: DATATABLE_LANG
        });
    });

    // Report table: with export buttons (Excel / Print / Copy)
    $(function () {
        $('.report-table').DataTable({
            responsive: true,
            pageLength: 25,
            dom: "<'row'<'col-sm-6'B><'col-sm-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'i><'col-sm-7'p>>",
            buttons: [
                { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm' },
                { extend: 'print', text: '<i class="fas fa-print"></i> {{ __('Print') }}', className: 'btn btn-secondary btn-sm' },
                { extend: 'copyHtml5', text: '<i class="fas fa-copy"></i> {{ __('Copy') }}', className: 'btn btn-info btn-sm' }
            ],
            language: DATATABLE_LANG
        });
    });

    // Prevent multiple clicks on links (New/Edit buttons, menu, etc.)
    (function () {
        const bar = document.createElement('div');
        bar.id = 'loading-bar';
        document.body.appendChild(bar);

        document.addEventListener('click', function (e) {
            const link = e.target.closest('a');
            // Skip: no href, anchors, new tab, or collapse/toggle elements
            if (!link || !link.href || link.target === '_blank' || link.getAttribute('href').startsWith('#') || link.dataset.toggle) return;

            // Block further clicks after the first one
            if (link.dataset.navigating) { e.preventDefault(); return; }
            link.dataset.navigating = '1';
            link.classList.add('nav-locked');

            // Visual progress bar while the next page loads
            bar.style.width = '70%';

            // Release in case the user comes back with the back button (bfcache)
            setTimeout(() => { delete link.dataset.navigating; link.classList.remove('nav-locked'); bar.style.width = '0'; }, 6000);
        }, true);

        window.addEventListener('pageshow', () => {
            bar.style.width = '0';
            document.querySelectorAll('a.nav-locked').forEach(link => { delete link.dataset.navigating; link.classList.remove('nav-locked'); });
        });
    })();

    // Anti double-submit: disables the button and shows a spinner on submit
    $(document).on('submit', 'form:not(.delete-form)', function () {
        const buttons = $(this).find('button[type="submit"], button:not([type])');
        setTimeout(() => {
            buttons.prop('disabled', true)
                   .prepend('<span class="spinner-border spinner-border-sm mr-1"></span>');
        }, 10);
    });

    // SweetAlert2 confirmation for deletions
    $(document).on('submit', '.delete-form', function (e) {
        e.preventDefault();
        const form = this;
        Swal.fire({
            title: @json(__('Are you sure?')),
            text: @json(__('This action cannot be undone.')),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: @json(__('Yes, delete')),
            cancelButtonText: @json(__('Cancel'))
        }).then(result => { if (result.isConfirmed) form.submit(); });
    });

    // Session notifications as SweetAlert2 toasts
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
