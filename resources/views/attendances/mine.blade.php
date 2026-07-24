@extends('layouts.app')
@section('title', __('My attendance'))
@section('content')
@if(!$employee)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('Your user is not linked to an employee. Ask the administrator to link it.') }}</div>
@else
@php
    $statusBadge = fn ($status) => match ($status) {
        'ON_TIME' => 'success',
        'LATE' => 'warning',
        'EXCUSED' => 'info',
        'FREE' => 'info',
        default => 'secondary',
    };
    $statusColors = ['ON_TIME' => '#0ca30c', 'LATE' => '#fab219', 'ABSENT' => '#d03b3b', 'EXCUSED' => '#2a78d6'];
    $workedHours = function ($attendance) {
        if (!$attendance->check_in || !$attendance->check_out) {
            return '—';
        }
        $start = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
        $end = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_out);
        if ($end->lessThan($start)) {
            $end->addDay();
        }
        $minutes = (int) $start->diffInMinutes($end);
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    };
@endphp

{{-- Month filter --}}
<div class="card card-primary card-outline mb-3">
    <div class="card-body py-2">
        <form class="form-inline">
            <label class="mr-2 font-weight-bold">{{ __('Month') }}:</label>
            <input type="month" name="month" value="{{ $selectedMonth }}" max="{{ company_now()->format('Y-m') }}" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
            <button class="btn btn-sm btn-primary mr-3"><i class="fas fa-filter"></i> {{ __('View') }}</button>
            <a href="{{ route('reports.mySheet', ['month' => $selectedMonth]) }}" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i> {{ __('Sheet (PDF) for this month') }}</a>
        </form>
    </div>
</div>

{{-- Month summary (professional stat tiles) --}}
<div class="row">
    <div class="col-6 col-lg-3 mb-3">@include('partials.stat-card', ['label' => __('Worked days'), 'value' => $summary['days'], 'icon' => 'fa-calendar-check', 'chip' => 'chip-blue'])</div>
    <div class="col-6 col-lg-3 mb-3">@include('partials.stat-card', ['label' => __('Worked hours'), 'value' => $summary['hours'], 'icon' => 'fa-clock', 'chip' => 'chip-green'])</div>
    <div class="col-6 col-lg-3 mb-3">@include('partials.stat-card', ['label' => __('Late'), 'value' => $summary['late'], 'icon' => 'fa-user-clock', 'chip' => 'chip-amber'])</div>
    <div class="col-6 col-lg-3 mb-3">@include('partials.stat-card', ['label' => __('Absences'), 'value' => $summary['absent'], 'icon' => 'fa-user-times', 'chip' => 'chip-red'])</div>
</div>

<div class="row">
    <div class="col-lg-8 mb-3">
        <div class="card h-100 mb-0">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-user-check"></i> {{ __('History') }} — {{ $employee->full_name }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @forelse($attendances as $attendance)
                        <tr>
                            <td>{{ $attendance->date->format('d/m/Y') }}</td>
                            <td>{{ $attendance->check_in ? substr($attendance->check_in, 0, 5) : '—' }}</td>
                            <td>{{ $attendance->check_out ? substr($attendance->check_out, 0, 5) : '—' }}</td>
                            <td class="text-center font-weight-bold">{{ $workedHours($attendance) }}</td>
                            <td><span class="badge badge-{{ $statusBadge($attendance->status) }}">{{ __($attendance->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No attendance recorded this month') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card h-100 mb-0">
            <div class="card-header"><h3 class="card-title">{{ __('Month breakdown') }}</h3></div>
            <div class="card-body d-flex align-items-center justify-content-center">
                @if($statusCounts->isNotEmpty())
                    <canvas id="myMonthChart" style="max-height:260px"></canvas>
                @else
                    <p class="text-muted mb-0">{{ __('No attendance recorded this month') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if($employee && $statusCounts->isNotEmpty())
<script>
const MY_STATUS_COLORS = @json($statusColors);
const MY_STATUS_LABELS = @json(collect(\App\Models\Attendance::STATUSES)->mapWithKeys(fn ($s) => [$s => __($s)]));
const MY_COUNTS = @json($statusCounts);
const CSS = getComputedStyle(document.documentElement);
const SURFACE = CSS.getPropertyValue('--card-bg').trim() || '#ffffff';
const INK = CSS.getPropertyValue('--ink').trim() || '#101828';
const INK_MUTED = CSS.getPropertyValue('--ink-3').trim() || '#667085';
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.color = INK_MUTED;

const keys = Object.keys(MY_COUNTS);
const total = keys.reduce((s, k) => s + MY_COUNTS[k], 0);
new Chart(document.getElementById('myMonthChart'), {
    type: 'doughnut',
    data: {
        labels: keys.map(k => MY_STATUS_LABELS[k]),
        datasets: [{ data: keys.map(k => MY_COUNTS[k]), backgroundColor: keys.map(k => MY_STATUS_COLORS[k]), borderColor: SURFACE, borderWidth: 2 }],
    },
    options: {
        cutout: '70%',
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14 } } },
    },
    plugins: [{
        id: 'centerTotal',
        afterDraw(chart) {
            const { ctx, chartArea } = chart;
            const x = (chartArea.left + chartArea.right) / 2, y = (chartArea.top + chartArea.bottom) / 2;
            ctx.save();
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillStyle = INK; ctx.font = "700 26px 'Inter', system-ui, sans-serif";
            ctx.fillText(total, x, y - 8);
            ctx.fillStyle = INK_MUTED; ctx.font = "500 12px 'Inter', system-ui, sans-serif";
            ctx.fillText(@json(__('days')), x, y + 14);
            ctx.restore();
        },
    }],
});
</script>
@endif
@endpush
