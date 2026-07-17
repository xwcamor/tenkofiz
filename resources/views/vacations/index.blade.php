@extends('layouts.app')
@section('title', __('Vacations'))
@section('header-button')
    @if($employees->isNotEmpty())
        <button class="btn btn-primary" onclick="$('#vacationModal').modal('show')"><i class="fas fa-plus"></i> {{ __('Request vacations') }}</button>
    @endif
@endsection
@section('content')
@php
    $statusBadge = fn ($status) => match ($status) {
        'APPROVED' => 'success',
        'REJECTED' => 'danger',
        default => 'warning',
    };
@endphp
<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <select name="status" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(['PENDING', 'APPROVED', 'REJECTED'] as $status)
                    <option value="{{ $status }}" @selected(request('status') == $status)>{{ __($status) }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
        </form>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Days') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th style="width:{{ $canApprove ? 150 : 60 }}px">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @forelse($vacations as $vacation)
                <tr>
                    <td>{{ $vacation->employee->full_name }}</td>
                    <td>{{ $vacation->start_date->format('d/m/Y') }}</td>
                    <td>{{ $vacation->end_date->format('d/m/Y') }}</td>
                    <td>{{ $vacation->days }}</td>
                    <td class="text-muted">{{ $vacation->reason }}</td>
                    <td>
                        <span class="badge badge-{{ $statusBadge($vacation->status) }}">{{ __($vacation->status) }}</span>
                        @if($vacation->approver)<div class="text-muted small">{{ __('by') }} {{ $vacation->approver->name }}</div>@endif
                    </td>
                    <td>
                        <a href="{{ route('vacations.print', $vacation) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="{{ __('Printable formal sheet') }}"><i class="fas fa-file-pdf"></i></a>
                        @if($canApprove && $vacation->status === 'PENDING')
                            <form method="POST" action="{{ route('vacations.status', $vacation) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="APPROVED">
                                <button class="btn btn-sm btn-success" title="{{ __('Approve') }}"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="POST" action="{{ route('vacations.status', $vacation) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="REJECTED">
                                <button class="btn btn-sm btn-danger" title="{{ __('Reject') }}"><i class="fas fa-times"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No requests') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="d-flex justify-content-center">{{ $vacations->links() }}</div>
    </div>
</div>

{{-- Request modal --}}
<div class="modal fade" id="vacationModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('vacations.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-umbrella-beach"></i> {{ __('New vacation request') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Employee') }}</label>
                    <select name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" required {{ $employees->count() === 1 ? 'readonly' : '' }}>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                    @error('employee_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="row">
                    <div class="col form-group">
                        <label>{{ __('Start date') }}</label>
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="form-control @error('start_date') is-invalid @enderror" required>
                        @error('start_date')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col form-group">
                        <label>{{ __('End date') }}</label>
                        <input type="date" name="end_date" value="{{ old('end_date') }}" class="form-control @error('end_date') is-invalid @enderror" required>
                        @error('end_date')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ __('Reason') }} <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control @error('reason') is-invalid @enderror" rows="2" required maxlength="300" placeholder="{{ __('Required: briefly explain the reason for the request') }}">{{ old('reason') }}</textarea>
                    @error('reason')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> {{ __('Submit request') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
@if($errors->any())
    $('#vacationModal').modal('show');
@endif
</script>
@endpush
