@extends('layouts.app')
@section('title', __('Justifications'))
@section('header-button')
    <div>
        @if(auth()->user()->hasModule('settings'))
            @if($showDeleted)
                <a href="{{ route('justifications.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> {{ __('Back to list') }}</a>
            @else
                <a href="{{ route('justifications.index', ['deleted' => 1]) }}" class="btn btn-outline-secondary" title="{{ __('Deleted records (only administrators see this view)') }}"><i class="fas fa-trash-restore"></i> {{ __('View deleted') }}</a>
            @endif
        @endif
        @if($isManager || $employees->isNotEmpty())
            <button class="btn btn-primary" onclick="$('#justificationModal').modal('show')"><i class="fas fa-plus"></i> {{ __('New justification') }}</button>
        @endif
    </div>
@endsection
@section('content')
@php
    $statusBadge = fn ($status) => match ($status) {
        'ACCEPTED' => 'success',
        'REJECTED' => 'danger',
        default => 'warning',
    };
@endphp
<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <select name="status" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(['PENDING', 'ACCEPTED', 'REJECTED'] as $status)
                    <option value="{{ $status }}" @selected(request('status') == $status)>{{ __($status) }}</option>
                @endforeach
            </select>
            @if(!$showDeleted && $sites->count() > 1)
                <select name="site_id" class="form-control form-control-sm mr-2">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" @selected(request('site_id') == $site->id)>{{ $site->name }}</option>
                    @endforeach
                </select>
            @endif
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
            @if(request()->hasAny(['status', 'site_id']))
                <a href="{{ route('justifications.index') }}" class="btn btn-sm btn-outline-secondary ml-1">{{ __('Clear') }}</a>
            @endif
        </form>
    </div>
    <div class="card-body">
        @if($showDeleted)
            <div class="alert alert-warning py-2"><i class="fas fa-trash-restore"></i> {{ __('You are viewing deleted records. Restoring brings them back with all their history.') }}</div>
        @endif
        <table class="table table-bordered table-hover">
            <thead>
                @if($showDeleted)
                    <tr><th>{{ __('Employee') }}</th><th>{{ __('Date') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Deleted on') }}</th><th>{{ __('Reason for deletion') }}</th><th style="width:130px">{{ __('Actions') }}</th></tr>
                @else
                    <tr>
                        @include('partials.th-sort', ['key' => 'employee', 'label' => __('Employee')])
                        @if($sites->count() > 1)<th>{{ __('Site') }}</th>@endif
                        @include('partials.th-sort', ['key' => 'date', 'label' => __('Date')])
                        <th>{{ __('Reason') }}</th>
                        <th>{{ __('Document') }}</th>
                        @include('partials.th-sort', ['key' => 'status', 'label' => __('Status')])
                        <th style="width:{{ $canReview ? 180 : 60 }}px">{{ __('Actions') }}</th>
                    </tr>
                @endif
            </thead>
            <tbody>
            @forelse($justifications as $justification)
                @if($showDeleted)
                    <tr>
                        <td>{{ $justification->employee->full_name }}</td>
                        <td>{{ $justification->date->format('d/m/Y') }}</td>
                        <td>{{ $justification->reason }}</td>
                        <td>{{ to_user_tz($justification->deleted_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $justification->delete_reason ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('justifications.restore', $justification) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" title="{{ __('Restore') }}"><i class="fas fa-trash-restore"></i> {{ __('Restore') }}</button>
                            </form>
                        </td>
                    </tr>
                    @continue
                @endif
                <tr>
                    <td>{{ $justification->employee->full_name }}</td>
                    @if($sites->count() > 1)<td>{{ $justification->employee->site?->name ?? '—' }}</td>@endif
                    <td>{{ $justification->date->format('d/m/Y') }}</td>
                    <td>{{ $justification->reason }}</td>
                    <td>
                        @if($justification->document)
                            <a href="{{ asset($justification->document) }}" target="_blank" class="btn btn-sm btn-outline-primary file-preview"><i class="fas fa-file-alt"></i> {{ __('View') }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $statusBadge($justification->status) }}">{{ __($justification->status) }}</span>
                        @if($justification->reviewer)<div class="text-muted small">{{ __('by') }} {{ $justification->reviewer->name }}</div>@endif
                    </td>
                    <td>
                        <a href="{{ route('justifications.print', $justification) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="{{ __('Printable formal sheet') }}"><i class="fas fa-file-pdf"></i></a>
                        @if($canReview)
                        @if($justification->status === 'PENDING')
                            <form method="POST" action="{{ route('justifications.status', $justification) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="ACCEPTED">
                                <button class="btn btn-sm btn-success" title="{{ __('Accept (marks the day as EXCUSED)') }}"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="POST" action="{{ route('justifications.status', $justification) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="REJECTED">
                                <button class="btn btn-sm btn-danger" title="{{ __('Reject') }}"><i class="fas fa-times"></i></button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('justifications.destroy', $justification) }}" class="d-inline delete-form">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ !$showDeleted && $sites->count() > 1 ? 7 : 6 }}" class="text-center text-muted py-4">{{ $showDeleted ? __('No deleted records.') : __('No justifications') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="d-flex justify-content-center">{{ $justifications->links() }}</div>
        <p class="text-muted mt-2"><i class="fas fa-info-circle"></i> {!! __('When a justification is <strong>accepted</strong>, that day is recorded as <span class="badge badge-info">EXCUSED</span> in the employee\'s attendance.') !!}</p>
    </div>
</div>

{{-- Create modal --}}
<div class="modal fade" id="justificationModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('justifications.store') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-medical"></i> {{ __('Register justification') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Employee') }}</label>
                    @if($isManager)
                        <select name="employee_id" class="employee-select @error('employee_id') is-invalid @enderror"
                                data-url="{{ route('employees.search') }}" data-placeholder="{{ __('Search by name or document…') }}"
                                @if($oldEmployee) data-selected-id="{{ $oldEmployee->getRouteKey() }}" data-selected-text="{{ $oldEmployee->full_name }}" @endif></select>
                    @else
                        <select name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" required>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->getRouteKey() }}" @selected(old('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('employee_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Date to justify') }}</label>
                    <input type="date" name="date" value="{{ old('date') }}" class="form-control @error('date') is-invalid @enderror" required max="{{ company_now()->toDateString() }}">
                    @error('date')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Reason') }} <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control @error('reason') is-invalid @enderror" rows="3" required maxlength="300" placeholder="{{ __('E.g.: Medical appointment — certificate attached') }}">{{ old('reason') }}</textarea>
                    @error('reason')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Supporting document') }} <small class="text-muted">({{ __('PDF or image, max. 2MB') }})</small></label>
                    <input type="file" name="document" class="form-control-file @error('document') is-invalid @enderror" accept=".pdf,image/png,image/jpeg">
                    @error('document')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> {{ __('Submit justification') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
@if($errors->any())
    $('#justificationModal').modal('show');
@endif
</script>
@endpush
