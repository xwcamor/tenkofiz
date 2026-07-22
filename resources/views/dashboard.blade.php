@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')

@php
    $statusBadge = fn ($status) => match ($status) {
        'ON_TIME' => 'success',
        'LATE' => 'warning',
        'EXCUSED' => 'info',
        'APPROVED', 'ACCEPTED' => 'success',
        'REJECTED' => 'danger',
        'PENDING' => 'warning',
        default => 'secondary',
    };
    // Status palette (matches badges): good / warning / critical / info
    $statusColors = ['ON_TIME' => '#0ca30c', 'LATE' => '#fab219', 'ABSENT' => '#d03b3b', 'EXCUSED' => '#2a78d6'];
@endphp

@if($isManager)
{{-- ================= GLOBAL DASHBOARD (managers) ================= --}}
@php $pendingTotal = $pendingVacations + $pendingJustifications; @endphp

@if($sites->count() > 1)
    {{-- Site focus: scope every KPI and chart below to one branch --}}
    <form class="form-inline mb-3" method="GET">
        <label class="mr-2 text-muted"><i class="fas fa-building"></i> {{ __('Site') }}</label>
        <select name="site_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
            <option value="">{{ __('All sites') }}</option>
            @foreach($sites as $site)
                <option value="{{ $site->id }}" @selected($siteId == $site->id)>{{ $site->name }}</option>
            @endforeach
        </select>
        @if($siteId)<a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary">{{ __('Clear') }}</a>@endif
    </form>
@endif

<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">{{ __('Active employees') }}</div>
                <div class="stat-value">{{ $totalEmployees }}</div>
                <div class="stat-sub"><a href="{{ route('employees.index') }}">{{ __('Manage') }} →</a></div>
            </div>
            <div class="stat-chip chip-blue"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">{{ __('Attendance rate today') }}</div>
                <div class="stat-value">{{ $attendanceRate }}%</div>
                <div class="stat-sub">{{ __(':count of :total employees checked in', ['count' => $attendancesToday, 'total' => $totalEmployees]) }}</div>
            </div>
            <div class="stat-chip chip-green"><i class="fas fa-calendar-check"></i></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">{{ __('Late today') }}</div>
                <div class="stat-value">{{ $lateToday }}</div>
                <div class="stat-sub">{{ __('within today\'s marks') }}</div>
            </div>
            <div class="stat-chip chip-amber"><i class="fas fa-user-clock"></i></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">{{ __('Pending approvals') }}</div>
                <div class="stat-value">{{ $pendingTotal }}</div>
                <div class="stat-sub">{{ $pendingVacations }} {{ __('vacations') }} · {{ $pendingJustifications }} {{ __('justifications') }}</div>
            </div>
            <div class="stat-chip chip-red"><i class="fas fa-inbox"></i></div>
        </div>
    </div>
</div>

@if($withoutFace > 0)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('There are :count employee(s) without an enrolled face: they will not be able to mark facial attendance.', ['count' => $withoutFace]) }}</div>
@endif

@if(!$siteId && $siteBreakdown->count() > 1)
    {{-- Per-site snapshot: headcount and who is present today, branch by branch --}}
    <div class="card card-primary card-outline mb-3">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-building"></i> {{ __('By site — today') }}</h3></div>
        <div class="card-body">
            <div class="row">
                @foreach($siteBreakdown as $s)
                    <div class="col-md-4 col-lg-3 mb-2">
                        <a href="{{ route('dashboard', ['site_id' => $sites->firstWhere('name', $s['name'])?->id]) }}" class="text-decoration-none">
                            <div class="stat-card h-100">
                                <div>
                                    <div class="stat-label">{{ $s['name'] }}</div>
                                    <div class="stat-value">{{ $s['present'] }}/{{ $s['employees'] }}</div>
                                    <div class="stat-sub">{{ __('present today') }} · {{ $s['rate'] }}%</div>
                                </div>
                                <div class="stat-chip {{ $s['rate'] >= 80 ? 'chip-green' : ($s['rate'] >= 50 ? 'chip-amber' : 'chip-red') }}"><i class="fas fa-users"></i></div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div class="row">
    <div class="col-lg-8 mb-3">
        <div class="card card-primary card-outline h-100 mb-0">
            <div class="card-header"><h3 class="card-title">{{ __('Attendance — last 7 days') }}</h3></div>
            <div class="card-body"><canvas id="weekChart" height="150"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card card-primary card-outline h-100 mb-0">
            <div class="card-header"><h3 class="card-title">{{ __('Today by status') }}</h3></div>
            <div class="card-body d-flex align-items-center justify-content-center">
                @if($attendancesToday > 0)
                    <canvas id="todayChart" style="max-height:240px"></canvas>
                @else
                    <p class="text-muted mb-0">{{ __('No marks today') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card card-primary card-outline h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">{{ __('Latest marks today') }}</h3>
                <a href="{{ route('attendances.index') }}" class="btn btn-sm btn-default">{{ __('View all') }}</a>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @forelse($latest as $attendance)
                        <tr>
                            <td class="font-weight-500">{{ $attendance->employee->full_name }}</td>
                            <td>{{ $attendance->check_in ? substr($attendance->check_in, 0, 5) : '—' }}</td>
                            <td>{{ $attendance->check_out ? substr($attendance->check_out, 0, 5) : '—' }}</td>
                            <td><span class="badge badge-{{ $statusBadge($attendance->status) }}">{{ __($attendance->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No marks today') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-3">
        <div class="card card-primary card-outline h-100 mb-0">
            <div class="card-header"><h3 class="card-title">{{ __('Awaiting your decision') }}</h3></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach($pendingVacationList as $vacation)
                        <li class="list-group-item d-flex justify-content-between align-items-center border-0" style="border-bottom:1px solid var(--hairline) !important">
                            <div>
                                <i class="fas fa-umbrella-beach text-muted mr-2"></i>
                                <span class="text-dark">{{ $vacation->employee->full_name }}</span>
                                <div class="text-muted small ml-4">{{ $vacation->start_date->format('d/m') }} – {{ $vacation->end_date->format('d/m') }} · {{ $vacation->days }} {{ __('days') }}</div>
                            </div>
                            <a href="{{ route('vacations.index', ['status' => 'PENDING']) }}" class="btn btn-sm btn-outline-primary">{{ __('Review') }}</a>
                        </li>
                    @endforeach
                    @foreach($pendingJustificationList as $justification)
                        <li class="list-group-item d-flex justify-content-between align-items-center border-0" style="border-bottom:1px solid var(--hairline) !important">
                            <div>
                                <i class="fas fa-file-medical text-muted mr-2"></i>
                                <span class="text-dark">{{ $justification->employee->full_name }}</span>
                                <div class="text-muted small ml-4">{{ $justification->date->format('d/m/Y') }} · {{ \Illuminate\Support\Str::limit($justification->reason, 40) }}</div>
                            </div>
                            <a href="{{ route('justifications.index', ['status' => 'PENDING']) }}" class="btn btn-sm btn-outline-primary">{{ __('Review') }}</a>
                        </li>
                    @endforeach
                    @if($pendingVacationList->isEmpty() && $pendingJustificationList->isEmpty())
                        <li class="list-group-item border-0 text-center text-muted py-4">
                            <i class="fas fa-check-circle text-success mr-1"></i> {{ __('Everything is up to date.') }}
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

@else
{{-- ================= PERSONAL DASHBOARD (employee profile) ================= --}}
@if(!$employee)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('Your user is not linked to an employee record yet. Ask the administrator to link it to see your information.') }}</div>
@else
    <div class="callout callout-info">
        <h5 class="font-weight-bold"><i class="fas fa-user"></i> {{ __('Hello, :name', ['name' => $employee->first_name]) }}</h5>
        <p class="mb-0 text-muted">{{ $employee->position?->name ?? '' }} {{ $employee->area ? '— '.$employee->area->name : '' }} | {{ __('Schedule') }}: {{ $employee->schedule?->name ?? __('not assigned') }}</p>
    </div>

    <div class="row">
        <div class="col-lg-3 col-6 mb-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">{{ __('My check-in today') }}</div>
                    <div class="stat-value">{{ $todayAttendance?->check_in ? substr($todayAttendance->check_in, 0, 5) : '—' }}</div>
                    <div class="stat-sub">
                        @if($todayAttendance)
                            <span class="badge badge-{{ $statusBadge($todayAttendance->status) }}">{{ __($todayAttendance->status) }}</span>
                        @else
                            {{ __('not marked') }}
                        @endif
                    </div>
                </div>
                <div class="stat-chip {{ $todayAttendance ? ($todayAttendance->status === 'LATE' ? 'chip-amber' : 'chip-green') : 'chip-slate' }}"><i class="fas fa-sign-in-alt"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6 mb-3">@include('partials.stat-card', ['label' => __('Days worked this month'), 'value' => $daysThisMonth, 'icon' => 'fa-calendar-check', 'chip' => 'chip-blue', 'sub' => '<a href="'.route('attendances.mine').'">'.__('View history').' →</a>'])</div>
        <div class="col-lg-3 col-6 mb-3">@include('partials.stat-card', ['label' => __('Late arrivals this month'), 'value' => $lateThisMonth, 'icon' => 'fa-user-clock', 'chip' => 'chip-amber'])</div>
        <div class="col-lg-3 col-6 mb-3">@include('partials.stat-card', ['label' => __('Vacation days left'), 'value' => $remainingVacation, 'icon' => 'fa-umbrella-beach', 'chip' => 'chip-green', 'sub' => '<a href="'.route('vacations.index').'">'.__('Request vacations').' →</a>'])</div>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-3">
            <div class="card card-primary card-outline mb-0">
                <div class="card-header"><h3 class="card-title">{{ __('My worked days — last 6 months') }}</h3></div>
                <div class="card-body"><canvas id="myTrendChart" height="90"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7 mb-3">
            <div class="card card-primary card-outline h-100 mb-0">
                <div class="card-header"><h3 class="card-title">{{ __('My latest attendance') }}</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @forelse($recent as $attendance)
                            <tr>
                                <td>{{ $attendance->date->format('d/m/Y') }}</td>
                                <td>{{ $attendance->check_in ? substr($attendance->check_in, 0, 5) : '—' }}</td>
                                <td>{{ $attendance->check_out ? substr($attendance->check_out, 0, 5) : '—' }}</td>
                                <td><span class="badge badge-{{ $statusBadge($attendance->status) }}">{{ __($attendance->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No attendance recorded') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5 mb-3">
            <div class="card card-primary card-outline h-100 mb-0">
                <div class="card-header"><h3 class="card-title">{{ __('My recent vacations') }}</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Days') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @forelse($myVacations as $vacation)
                            <tr>
                                <td>{{ $vacation->start_date->format('d/m/Y') }}</td>
                                <td>{{ $vacation->end_date->format('d/m/Y') }}</td>
                                <td>{{ $vacation->days }}</td>
                                <td><span class="badge badge-{{ $statusBadge($vacation->status) }}">{{ __($vacation->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No requests') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
@endif
@endsection

@push('scripts')
@if(!$isManager && $employee)
<script>
// Employee trend: worked days per month (single brand-colored series, no legend needed)
const CSS = getComputedStyle(document.documentElement);
const INK_MUTED = CSS.getPropertyValue('--ink-3').trim() || '#667085';
const GRID = CSS.getPropertyValue('--hairline').trim() || '#eef1f6';
const BRAND = CSS.getPropertyValue('--brand').trim() || '#2a78d6';
const SURFACE = CSS.getPropertyValue('--card-bg').trim() || '#ffffff';
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.color = INK_MUTED;
new Chart(document.getElementById('myTrendChart'), {
    type: 'bar',
    data: {
        labels: @json($trendLabels),
        datasets: [{ label: @json(__('Worked days')), data: @json($trendWorked), backgroundColor: BRAND, borderColor: SURFACE, borderWidth: 2, borderRadius: 4, borderSkipped: false, maxBarThickness: 48 }],
    },
    options: {
        maintainAspectRatio: true,
        scales: {
            x: { grid: { display: false }, border: { color: GRID } },
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID }, border: { display: false } },
        },
        plugins: { legend: { display: false }, tooltip: { mode: 'index' } },
    },
});
</script>
@endif
@if($isManager)
<script>
const STATUS_COLORS = @json($statusColors);
const STATUS_LABELS = @json(collect(\App\Models\Attendance::STATUSES)->mapWithKeys(fn ($s) => [$s => __($s)]));
// Chart chrome follows the active theme (light/dark tokens from theme.css)
const THEME_CSS = getComputedStyle(document.documentElement);
const INK_MUTED = THEME_CSS.getPropertyValue('--ink-3').trim() || '#667085';
const INK_STRONG = THEME_CSS.getPropertyValue('--ink').trim() || '#101828';
const GRID = THEME_CSS.getPropertyValue('--hairline').trim() || '#eef1f6';
const SURFACE = THEME_CSS.getPropertyValue('--card-bg').trim() || '#ffffff';

Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.color = INK_MUTED;

// Last 7 days, stacked by status (status colors match the badges)
new Chart(document.getElementById('weekChart'), {
    type: 'bar',
    data: {
        labels: @json($labels),
        datasets: Object.keys(STATUS_COLORS).map(status => ({
            label: STATUS_LABELS[status],
            data: @json($statusSeries)[status],
            backgroundColor: STATUS_COLORS[status],
            borderColor: SURFACE,
            borderWidth: 2,
            borderRadius: 4,
            borderSkipped: false,
            maxBarThickness: 34,
        })),
    },
    options: {
        maintainAspectRatio: true,
        scales: {
            x: { stacked: true, grid: { display: false }, border: { color: GRID } },
            y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID }, border: { display: false } },
        },
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16 } },
            tooltip: { mode: 'index' },
        },
    },
});

// Today's distribution by status
@if($attendancesToday > 0)
const todayData = @json($todayByStatus);
const todayKeys = Object.keys(STATUS_COLORS).filter(status => todayData[status]);
new Chart(document.getElementById('todayChart'), {
    type: 'doughnut',
    data: {
        labels: todayKeys.map(status => STATUS_LABELS[status]),
        datasets: [{
            data: todayKeys.map(status => todayData[status]),
            backgroundColor: todayKeys.map(status => STATUS_COLORS[status]),
            borderColor: SURFACE,
            borderWidth: 2,
        }],
    },
    options: {
        cutout: '72%',
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14 } },
        },
    },
    plugins: [{
        // Total in the middle of the ring
        id: 'centerTotal',
        afterDraw(chart) {
            const { ctx, chartArea } = chart;
            const x = (chartArea.left + chartArea.right) / 2;
            const y = (chartArea.top + chartArea.bottom) / 2;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = INK_STRONG;
            ctx.font = "700 26px 'Inter', system-ui, sans-serif";
            ctx.fillText(@json($attendancesToday), x, y - 8);
            ctx.fillStyle = INK_MUTED;
            ctx.font = "500 12px 'Inter', system-ui, sans-serif";
            ctx.fillText(@json(__('marks')), x, y + 14);
            ctx.restore();
        },
    }],
});
@endif
</script>
@endif
@endpush
