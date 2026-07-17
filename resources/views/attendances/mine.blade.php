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
@endphp
<div class="card card-primary card-outline">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-user-check"></i> {{ __('History') }} — {{ $employee->full_name }}</h3></div>
    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse($attendances as $attendance)
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
        <div class="d-flex justify-content-center">{{ $attendances->links() }}</div>
    </div>
</div>
@endif
@endsection
