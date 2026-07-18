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
        default => 'secondary',
    };
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

{{-- Month summary --}}
<div class="row mb-3">
    <div class="col-6 col-md-3">
        <div class="small-box bg-info"><div class="inner"><h3>{{ $summary['days'] }}</h3><p>{{ __('Worked days') }}</p></div><div class="icon"><i class="fas fa-calendar-check"></i></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-success"><div class="inner"><h3>{{ $summary['hours'] }}</h3><p>{{ __('Worked hours') }}</p></div><div class="icon"><i class="fas fa-clock"></i></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-warning"><div class="inner"><h3>{{ $summary['late'] }}</h3><p>{{ __('Late') }}</p></div><div class="icon"><i class="fas fa-user-clock"></i></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-danger"><div class="inner"><h3>{{ $summary['absent'] }}</h3><p>{{ __('Absences') }}</p></div><div class="icon"><i class="fas fa-user-times"></i></div></div>
    </div>
</div>

<div class="card card-primary card-outline">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-user-check"></i> {{ __('History') }} — {{ $employee->full_name }}</h3></div>
    <div class="card-body">
        <table class="table table-bordered table-hover">
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
@endif
@endsection
