<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('System')) | {{ __('Facial Attendance') }}</title>
    <script>
        // Apply the saved theme before first paint (avoids a light flash)
        (function () {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <!-- AdminLTE 3 + plugins (CDN) -->
    <link rel="stylesheet" href="{{ vendor_asset('vendor/fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/adminlte/adminlte.min.css', 'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/datatables/dataTables.bootstrap4.min.css', 'https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/datatables/responsive.bootstrap4.min.css', 'https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/datatables/buttons.bootstrap4.min.css', 'https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.4.2/css/buttons.bootstrap4.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ vendor_asset('vendor/inter/inter.css', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/select2/select2.min.css', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/select2/select2-bootstrap4.min.css', 'https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ @filemtime(public_path('css/theme.css')) ?: 1 }}">
    <style>
        /* Top loading bar while navigating */
        #loading-bar { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: #007bff; z-index: 99999; transition: width .4s ease; }
        a.nav-locked { pointer-events: none; opacity: .65; }
        /* Hover preview for attachments (images and PDFs) */
        #file-preview-pop {
            position: fixed; z-index: 10500; display: none;
            background: #fff; border: 1px solid #e6eaf2; border-radius: 10px;
            box-shadow: 0 12px 32px rgba(16, 24, 40, .22);
            padding: 6px; pointer-events: none;
        }
        #file-preview-pop img { max-width: 300px; max-height: 300px; display: block; border-radius: 6px; }
        #file-preview-pop embed { width: 330px; height: 400px; border: 0; display: block; }
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
            <!-- Profile menu: account, theme, language and sign out in one place -->
            <li class="nav-item dropdown">
                <a class="nav-link d-flex align-items-center py-1" data-toggle="dropdown" href="#" title="{{ $currentUser->name }}">
                    @if($currentUser->photo)
                        <img src="{{ asset($currentUser->photo) }}" alt="" class="img-circle elevation-1" style="width:32px;height:32px;object-fit:cover">
                    @else
                        <span class="d-inline-flex align-items-center justify-content-center img-circle"
                              style="width:32px;height:32px;background:#e8f1fc;color:#2a78d6;font-size:.85rem;font-weight:700">{{ strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                    @endif
                    <i class="fas fa-caret-down ml-2 text-muted"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right" style="min-width:240px">
                    <div class="px-3 py-2">
                        <div class="font-weight-bold">{{ $currentUser->name }}</div>
                        <div class="text-muted small">{{ $currentUser->email }}</div>
                        <span class="badge badge-primary mt-1">{{ $currentUser->profile?->name }}</span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('account.edit') }}" class="dropdown-item"><i class="fas fa-user-cog mr-2 text-muted"></i> {{ __('My account') }}</a>
                    <a href="#" class="dropdown-item" onclick="toggleTheme(); return false;"><i class="fas fa-moon mr-2 text-muted" id="themeIcon"></i> {{ __('Toggle dark mode') }}</a>
                    <div class="dropdown-divider"></div>
                    <span class="dropdown-item-text text-muted small"><i class="fas fa-globe mr-1"></i> {{ __('Language') }}</span>
                    @foreach(['es' => 'Español', 'en' => 'English'] as $code => $label)
                        <form method="POST" action="{{ route('locale.switch') }}">@csrf
                            <input type="hidden" name="locale" value="{{ $code }}">
                            <button class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}">{{ $label }}</button>
                        </form>
                    @endforeach
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="dropdown-item text-danger"><i class="fas fa-sign-out-alt mr-2"></i> {{ __('Sign out') }}</button>
                    </form>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        @php $companySetting = app_setting(); @endphp
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
                                <a href="{{ route('sites.index') }}" class="nav-link {{ request()->routeIs('sites.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-map-marker-alt"></i><p>{{ __('Sites') }}</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('settings.edit') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-cog"></i><p>{{ __('Settings') }}</p>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if($currentUser->hasModule('kiosk'))
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
<script src="{{ vendor_asset('vendor/jquery/jquery.min.js', 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/bootstrap4/bootstrap.bundle.min.js', 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/adminlte/adminlte.min.js', 'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/datatables/jquery.dataTables.min.js', 'https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/datatables/dataTables.bootstrap4.min.js', 'https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/datatables/dataTables.responsive.min.js', 'https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/datatables/responsive.bootstrap4.min.js', 'https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/js/responsive.bootstrap4.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/jszip/jszip.min.js', 'https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/datatables/dataTables.buttons.min.js', 'https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/dataTables.buttons.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/datatables/buttons.bootstrap4.min.js', 'https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.4.2/js/buttons.bootstrap4.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/datatables/buttons.html5.min.js', 'https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.html5.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/datatables/buttons.print.min.js', 'https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.print.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/sweetalert2/sweetalert2.all.min.js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/chartjs/chart.umd.min.js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js') }}"></script>
<script src="{{ vendor_asset('vendor/select2/select2.min.js', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js') }}"></script>
@if(app()->getLocale() === 'es')
<script src="{{ vendor_asset('vendor/select2/i18n/es.js', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/i18n/es.js') }}"></script>
@endif
<script src="{{ asset('js/employee-select.js') }}?v={{ @filemtime(public_path('js/employee-select.js')) ?: 1 }}"></script>
<script>
    @php
        // Inline Spanish strings: no external i18n download
        $dataTableLang = app()->getLocale() === 'es' ? [
            'processing' => 'Procesando...', 'search' => 'Buscar:', 'lengthMenu' => 'Mostrar _MENU_ registros',
            'info' => 'Mostrando _START_ a _END_ de _TOTAL_ registros', 'infoEmpty' => 'Sin registros',
            'infoFiltered' => '(filtrado de _MAX_ registros)', 'loadingRecords' => 'Cargando...',
            'zeroRecords' => 'No se encontraron resultados', 'emptyTable' => 'Sin datos disponibles',
            'paginate' => ['first' => 'Primero', 'previous' => 'Anterior', 'next' => 'Siguiente', 'last' => 'Último'],
        ] : new stdClass();
    @endphp
    const DATATABLE_LANG = @json($dataTableLang);

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

    // Dark mode toggle (persisted per device). Reloads so charts repaint with the new tokens.
    function toggleTheme() {
        const dark = document.documentElement.getAttribute('data-theme') !== 'dark';
        localStorage.setItem('theme', dark ? 'dark' : 'light');
        location.reload();
    }
    function syncThemeIcon() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        const icon = document.getElementById('themeIcon');
        if (icon) icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
    }
    syncThemeIcon();

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

    // Hover preview for attachments: any <a class="file-preview"> shows its image/PDF on mouseover
    (function () {
        const pop = document.createElement('div');
        pop.id = 'file-preview-pop';
        document.body.appendChild(pop);
        let current = null;

        function position(e) {
            const pad = 16;
            const rect = pop.getBoundingClientRect();
            let x = e.clientX + pad;
            let y = e.clientY + pad;
            if (x + rect.width > window.innerWidth - 8) x = e.clientX - rect.width - pad;
            if (y + rect.height > window.innerHeight - 8) y = Math.max(8, e.clientY - rect.height - pad);
            pop.style.left = x + 'px';
            pop.style.top = y + 'px';
        }

        document.addEventListener('mouseover', function (e) {
            const link = e.target.closest('a.file-preview');
            if (!link || link === current) return;

            const url = link.href;
            const ext = url.split('?')[0].split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                pop.innerHTML = `<img src="${url}" alt="">`;
            } else if (ext === 'pdf') {
                pop.innerHTML = `<embed src="${url}#toolbar=0&navpanes=0" type="application/pdf">`;
            } else {
                return; // unknown type: no preview, the click still opens it
            }

            current = link;
            pop.style.display = 'block';
            position(e);
        });

        document.addEventListener('mousemove', function (e) {
            if (current) position(e);
        });

        document.addEventListener('mouseout', function (e) {
            if (current && e.target.closest('a.file-preview') === current && !current.contains(e.relatedTarget)) {
                current = null;
                pop.style.display = 'none';
                pop.innerHTML = '';
            }
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

    // SweetAlert2 confirmation for deletions, asking for the mandatory reason
    $(document).on('submit', '.delete-form', function (e) {
        e.preventDefault();
        const form = this;
        Swal.fire({
            title: @json(__('Are you sure?')),
            text: @json(__('The record will be removed from the lists. Please state the reason:')),
            input: 'textarea',
            inputPlaceholder: @json(__('Reason for deletion (required)')),
            inputAttributes: { maxlength: 300 },
            inputValidator: value => !value.trim() ? @json(__('The deletion reason is required.')) : undefined,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: @json(__('Yes, delete')),
            cancelButtonText: @json(__('Cancel'))
        }).then(result => {
            if (!result.isConfirmed) return;
            let reason = form.querySelector('input[name="delete_reason"]');
            if (!reason) {
                reason = document.createElement('input');
                reason.type = 'hidden';
                reason.name = 'delete_reason';
                form.appendChild(reason);
            }
            reason.value = result.value.trim();
            form.submit();
        });
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
<script src="{{ asset('js/trim-inputs.js') }}?v={{ @filemtime(public_path('js/trim-inputs.js')) ?: 1 }}"></script>
</body>
</html>
