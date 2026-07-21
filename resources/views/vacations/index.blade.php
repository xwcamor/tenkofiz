@extends('layouts.app')
@section('title', __('Vacations'))
@section('header-button')
    @if($isManager || $employees->isNotEmpty())
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
            @if($sites->count() > 1)
                <select name="site_id" class="form-control form-control-sm mr-2">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" @selected(request('site_id') == $site->id)>{{ $site->name }}</option>
                    @endforeach
                </select>
            @endif
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
            @if(request()->hasAny(['status', 'site_id']))
                <a href="{{ route('vacations.index') }}" class="btn btn-sm btn-outline-secondary ml-1">{{ __('Clear') }}</a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead><tr>
                @include('partials.th-sort', ['key' => 'employee', 'label' => __('Employee')])
                @if($sites->count() > 1)<th>{{ __('Site') }}</th>@endif
                @include('partials.th-sort', ['key' => 'start', 'label' => __('Start')])
                @include('partials.th-sort', ['key' => 'end', 'label' => __('End')])
                @include('partials.th-sort', ['key' => 'days', 'label' => __('Days')])
                <th>{{ __('Reason') }}</th>
                @include('partials.th-sort', ['key' => 'status', 'label' => __('Status')])
                <th style="width:{{ $canApprove ? 150 : 60 }}px">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse($vacations as $vacation)
                <tr>
                    <td>{{ $vacation->employee->full_name }}</td>
                    @if($sites->count() > 1)<td>{{ $vacation->employee->site?->name ?? '—' }}</td>@endif
                    <td>{{ $vacation->start_date->format('d/m/Y') }}</td>
                    <td>{{ $vacation->end_date->format('d/m/Y') }}</td>
                    <td>{{ $vacation->days }}
                        @if($canApprove && $vacation->status === 'PENDING')
                            <div class="text-muted small" title="{{ __('Remaining days this year') }}">{{ __('left') }}: {{ $balances[$vacation->employee_id] ?? '—' }}</div>
                        @endif
                    </td>
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
                <tr><td colspan="{{ $sites->count() > 1 ? 8 : 7 }}" class="text-center text-muted py-4">{{ __('No requests') }}</td></tr>
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
                    @if($isManager)
                        <select name="employee_id" id="vacationEmployee" class="employee-select @error('employee_id') is-invalid @enderror"
                                data-url="{{ route('employees.search') }}" data-placeholder="{{ __('Search by name or document…') }}"
                                @if($oldEmployee) data-selected-id="{{ $oldEmployee->getRouteKey() }}" data-selected-text="{{ $oldEmployee->full_name }}" @endif></select>
                    @else
                        <select name="employee_id" id="vacationEmployee" class="form-control @error('employee_id') is-invalid @enderror" required>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->getRouteKey() }}" @selected(old('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('employee_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    <small class="text-muted" id="vacationBalanceHint"></small>
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
// Remaining vacation days (current year), shown under the selector
const VACATION_BALANCES = @json($balances);

function showBalanceHint(remaining) {
    document.getElementById('vacationBalanceHint').textContent =
        remaining === undefined || remaining === null
            ? ''
            : @json(__('Available this year:')) + ' ' + remaining + ' ' + @json(__('days'));
}

@if($isManager)
    // The autocomplete already returns each employee's balance with the result
    $('#vacationEmployee')
        .on('select2:select', e => showBalanceHint(e.params.data.balance))
        .on('select2:clear', () => showBalanceHint(null));
@else
    const employeeSelect = document.getElementById('vacationEmployee');
    if (employeeSelect) {
        employeeSelect.addEventListener('change', () => showBalanceHint(VACATION_BALANCES[employeeSelect.value]));
        showBalanceHint(VACATION_BALANCES[employeeSelect.value]);
    }
@endif

@if($errors->any())
    $('#vacationModal').modal('show');
@endif
</script>
@endpush
