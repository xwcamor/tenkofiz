@extends('layouts.app')
@section('title', __('Worked hours and days report'))
@section('content')
@php $statusColors = ['ON_TIME' => '#0ca30c', 'LATE' => '#fab219', 'ABSENT' => '#d03b3b', 'EXCUSED' => '#2a78d6']; @endphp

@if($rows->isNotEmpty())
<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card h-100 mb-0">
            <div class="card-header"><h3 class="card-title">{{ __('Worked hours by employee (top 10)') }}</h3></div>
            <div class="card-body"><canvas id="hoursChart" height="150"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5 mb-3">
        <div class="card h-100 mb-0">
            <div class="card-header"><h3 class="card-title">{{ __('Status distribution') }}</h3></div>
            <div class="card-body d-flex align-items-center justify-content-center">
                @if(array_sum($statusTotals) > 0)
                    <canvas id="statusChart" style="max-height:240px"></canvas>
                @else
                    <p class="text-muted mb-0">{{ __('No records in the period') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <label class="mr-2">{{ __('From') }}</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm mr-3">
            <label class="mr-2">{{ __('To') }}</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm mr-3">
            @if($sites->count() > 1)
                <select name="site_id" class="form-control form-control-sm mr-3">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" @selected($siteId == $site->id)>{{ $site->name }}</option>
                    @endforeach
                </select>
            @endif
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Generate') }}</button>
            <a href="{{ route('reports.export', array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'site_id' => $siteId])) }}" class="btn btn-sm btn-success ml-2"><i class="fas fa-file-excel"></i> {{ __('Summary (Excel)') }}</a>
            <a href="{{ route('reports.exportDetail', array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'site_id' => $siteId])) }}" class="btn btn-sm btn-outline-success ml-1" title="{{ __('One row per employee per day, with times and worked hours') }}"><i class="fas fa-list"></i> {{ __('Detail (Excel)') }}</a>
            @if(app_setting()->kiosk_breaks_enabled)
                <a href="{{ route('reports.breaks', array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'site_id' => $siteId])) }}" class="btn btn-sm btn-outline-info ml-1" title="{{ __('Who took how long on break, and who went over the limit') }}"><i class="fas fa-mug-hot"></i> {{ __('Break analysis') }}</a>
            @endif
            @if(app_setting()->cutoff_day)
                @php [$periodStart, $periodEnd] = current_period(); @endphp
                <span class="badge badge-info ml-3" title="{{ __('Configured in Settings (cut-off day :day)', ['day' => app_setting()->cutoff_day]) }}">
                    <i class="fas fa-cut"></i> {{ __('Current period') }}: {{ $periodStart->format('d/m') }} – {{ $periodEnd->format('d/m') }}
                </span>
            @endif
        </form>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover report-table">
            <thead>
                <tr>
                    @include('partials.th-sort', ['key' => 'employee', 'label' => __('Employee')])
                    @include('partials.th-sort', ['key' => 'document', 'label' => __('Document')])
                    @include('partials.th-sort', ['key' => 'site', 'label' => __('Site')])
                    @include('partials.th-sort', ['key' => 'area', 'label' => __('Area')])
                    @include('partials.th-sort', ['key' => 'position', 'label' => __('Position')])
                    @include('partials.th-sort', ['key' => 'worked_days', 'label' => __('Worked days')])
                    @include('partials.th-sort', ['key' => 'on_time', 'label' => __('On time')])
                    @include('partials.th-sort', ['key' => 'late', 'label' => __('Late')])
                    @include('partials.th-sort', ['key' => 'late_minutes', 'label' => __('Late minutes')])
                    @include('partials.th-sort', ['key' => 'absent', 'label' => __('Absences')])
                    @include('partials.th-sort', ['key' => 'excused', 'label' => __('Excused')])
                    @include('partials.th-sort', ['key' => 'expected', 'label' => __('Expected hours')])
                    @include('partials.th-sort', ['key' => 'worked', 'label' => __('Worked hours')])
                    @include('partials.th-sort', ['key' => 'balance', 'label' => __('Balance')])
                    @include('partials.th-sort', ['key' => 'vacation', 'label' => __('Vacation days')])
                    <th>{{ __('Sheet') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['employee'] }}</td>
                    <td>{{ $row['document_number'] }}</td>
                    <td>{{ $row['site'] }}@if($row['site_address'])<br><small class="text-muted">{{ $row['site_address'] }}</small>@endif</td>
                    <td>{{ $row['area'] }}</td>
                    <td>{{ $row['position'] }}</td>
                    <td class="text-center">{{ $row['worked_days'] }}</td>
                    <td class="text-center text-success font-weight-bold">{{ $row['on_time'] }}</td>
                    <td class="text-center text-warning font-weight-bold">{{ $row['late'] }}</td>
                    <td class="text-center">{{ $row['late_minutes'] }}</td>
                    <td class="text-center text-danger font-weight-bold">{{ $row['absent'] }}</td>
                    <td class="text-center">{{ $row['excused'] }}</td>
                    <td class="text-center text-muted">{{ $row['expected_hours'] }}</td>
                    <td class="text-center font-weight-bold">{{ $row['worked_hours'] }}</td>
                    <td class="text-center font-weight-bold {{ $row['balance_minutes'] < 0 ? 'text-danger' : 'text-success' }}">{{ $row['balance_hours'] }}</td>
                    <td class="text-center">{{ $row['vacation_days'] }}</td>
                    <td class="text-center">
                        <a href="{{ route('reports.sheet', $row['id']) }}?from={{ $from->toDateString() }}&to={{ $to->toDateString() }}" target="_blank" class="btn btn-sm btn-outline-danger" title="{{ __('Printable formal sheet') }}"><i class="fas fa-file-pdf"></i></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="text-muted mt-2"><i class="fas fa-info-circle"></i> {{ __('Expected = the hours due per their schedule on the days worked; Worked = the hours actually completed; Balance = the difference (a chronic late arrival or early leave shows as a negative). Use the buttons to export to Excel or print.') }}</p>
    </div>
</div>
@endsection

@push('scripts')
@if($rows->isNotEmpty())
<script>
const R_STATUS_COLORS = @json($statusColors);
const R_STATUS_LABELS = @json(collect(\App\Models\Attendance::STATUSES)->mapWithKeys(fn ($s) => [$s => __($s)]));
const CSS = getComputedStyle(document.documentElement);
const INK_MUTED = CSS.getPropertyValue('--ink-3').trim() || '#667085';
const GRID = CSS.getPropertyValue('--hairline').trim() || '#eef1f6';
const BRAND = CSS.getPropertyValue('--brand').trim() || '#2a78d6';
const SURFACE = CSS.getPropertyValue('--card-bg').trim() || '#ffffff';
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.color = INK_MUTED;

// Worked hours by employee — horizontal bars (names read better on the y-axis)
new Chart(document.getElementById('hoursChart'), {
    type: 'bar',
    data: {
        labels: @json($hoursLabels),
        datasets: [{ label: @json(__('Worked hours')), data: @json($hoursData), backgroundColor: BRAND, borderColor: SURFACE, borderWidth: 2, borderRadius: 4, borderSkipped: false, maxBarThickness: 22 }],
    },
    options: {
        indexAxis: 'y',
        maintainAspectRatio: true,
        scales: {
            x: { beginAtZero: true, grid: { color: GRID }, border: { display: false }, title: { display: true, text: @json(__('Hours')) } },
            y: { grid: { display: false }, border: { color: GRID } },
        },
        plugins: { legend: { display: false }, tooltip: { mode: 'index' } },
    },
});

// Status distribution — doughnut
@if(array_sum($statusTotals) > 0)
const totals = @json($statusTotals);
const keys = Object.keys(R_STATUS_COLORS).filter(k => totals[k] > 0);
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: keys.map(k => R_STATUS_LABELS[k]),
        datasets: [{ data: keys.map(k => totals[k]), backgroundColor: keys.map(k => R_STATUS_COLORS[k]), borderColor: SURFACE, borderWidth: 2 }],
    },
    options: {
        cutout: '70%',
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14 } } },
    },
});
@endif
</script>
@endif
@endpush
