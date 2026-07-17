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
@endphp

@if($isManager)
{{-- ================= GLOBAL DASHBOARD (managers) ================= --}}
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $totalEmployees }}</h3><p>{{ __('Active employees') }}</p></div>
            <div class="icon"><i class="fas fa-users"></i></div>
            <a href="{{ route('employees.index') }}" class="small-box-footer">{{ __('See more') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ $attendancesToday }}</h3><p>{{ __('Attendance today') }}</p></div>
            <div class="icon"><i class="fas fa-calendar-check"></i></div>
            <a href="{{ route('attendances.index') }}" class="small-box-footer">{{ __('See more') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ $lateToday }}</h3><p>{{ __('Late today') }}</p></div>
            <div class="icon"><i class="fas fa-user-clock"></i></div>
            <span class="small-box-footer">&nbsp;</span>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ $pendingVacations }}</h3><p>{{ __('Pending vacations') }}</p></div>
            <div class="icon"><i class="fas fa-umbrella-beach"></i></div>
            <a href="{{ route('vacations.index') }}" class="small-box-footer">{{ __('See more') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

@if($withoutFace > 0)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('There are :count employee(s) without an enrolled face: they will not be able to mark facial attendance.', ['count' => $withoutFace]) }}</div>
@endif

<div class="row">
    <div class="col-md-7">
        <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-line"></i> {{ __('Attendance in the last 7 days') }}</h3></div>
            <div class="card-body"><canvas id="weekChart" height="180"></canvas></div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-list"></i> {{ __('Latest marks today') }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover table-sm mb-0">
                    <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @forelse($latest as $attendance)
                        <tr>
                            <td>{{ $attendance->employee->full_name }}</td>
                            <td>{{ $attendance->check_in }}</td>
                            <td>{{ $attendance->check_out ?? '—' }}</td>
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
</div>

@else
{{-- ================= PERSONAL DASHBOARD (employee profile) ================= --}}
@if(!$employee)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('Your user is not linked to an employee record yet. Ask the administrator to link it to see your information.') }}</div>
@else
    <div class="callout callout-info">
        <h5><i class="fas fa-user"></i> {{ __('Hello, :name', ['name' => $employee->first_name]) }}</h5>
        <p class="mb-0">{{ $employee->position?->name ?? '' }} {{ $employee->area ? '— '.$employee->area->name : '' }} | {{ __('Schedule') }}: {{ $employee->schedule?->name ?? __('not assigned') }}</p>
    </div>

    <div class="row">
        <div class="col-lg-4 col-12">
            <div class="small-box bg-{{ $todayAttendance ? ($todayAttendance->status === 'LATE' ? 'warning' : 'success') : 'secondary' }}">
                <div class="inner">
                    <h3>{{ $todayAttendance?->check_in ?? '—' }}</h3>
                    <p>{{ __('My check-in today') }} {{ $todayAttendance ? '('.__($todayAttendance->status).')' : '('.__('not marked').')' }}</p>
                </div>
                <div class="icon"><i class="fas fa-sign-in-alt"></i></div>
                <span class="small-box-footer">{{ __('Check-out') }}: {{ $todayAttendance?->check_out ?? __('pending') }}</span>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="small-box bg-info">
                <div class="inner"><h3>{{ $daysThisMonth }}</h3><p>{{ __('Days worked this month') }}</p></div>
                <div class="icon"><i class="fas fa-calendar-check"></i></div>
                <a href="{{ route('attendances.mine') }}" class="small-box-footer">{{ __('View history') }} <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="small-box bg-warning">
                <div class="inner"><h3>{{ $lateThisMonth }}</h3><p>{{ __('Late arrivals this month') }}</p></div>
                <div class="icon"><i class="fas fa-user-clock"></i></div>
                <span class="small-box-footer">&nbsp;</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-list"></i> {{ __('My latest attendance') }}</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @forelse($recent as $attendance)
                            <tr>
                                <td>{{ $attendance->date->format('d/m/Y') }}</td>
                                <td>{{ $attendance->check_in ?? '—' }}</td>
                                <td>{{ $attendance->check_out ?? '—' }}</td>
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
        <div class="col-md-5">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-umbrella-beach"></i> {{ __('My recent vacations') }}</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-sm mb-0">
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
@if($isManager)
<script>
new Chart(document.getElementById('weekChart'), {
    type: 'bar',
    data: {
        labels: @json($labels),
        datasets: [
            { label: @json(__('Attendance')), data: @json($attendanceSeries), backgroundColor: 'rgba(0,123,255,.6)' },
            { label: @json(__('Late')), data: @json($lateSeries), backgroundColor: 'rgba(255,193,7,.7)' }
        ]
    },
    options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>
@endif
@endpush
