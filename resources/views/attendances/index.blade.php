@extends('layouts.app')
@section('title', __('Attendance'))
@section('header-button')
    <div class="d-flex">
        <form method="POST" action="{{ route('attendances.markAbsences') }}" class="form-inline mr-2">
            @csrf
            <input type="date" name="date" value="{{ company_now()->toDateString() }}" max="{{ company_now()->toDateString() }}" class="form-control form-control-sm mr-1" required>
            <button class="btn btn-danger btn-sm" title="{{ __('Marks ABSENT everyone without an attendance record that day (skips holidays, non-working days, vacations)') }}"><i class="fas fa-user-times"></i> {{ __('Generate absences') }}</button>
        </form>
        <button class="btn btn-outline-primary btn-sm" onclick="openManualModal()"><i class="fas fa-plus"></i> {{ __('Manual entry') }}</button>
    </div>
@endsection
@section('content')
@php
    $statusBadge = fn ($status) => match ($status) {
        'ON_TIME' => 'success',
        'LATE' => 'warning',
        'ABSENT' => 'danger',
        default => 'info',
    };
@endphp

<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <label class="mr-2">{{ __('From') }}</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm mr-3">
            <label class="mr-2">{{ __('To') }}</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm mr-3">
            <select name="employee_id" class="form-control form-control-sm mr-3">
                <option value="">{{ __('All employees') }}</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control form-control-sm mr-3">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(\App\Models\Attendance::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') == $status)>{{ __($status) }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
        </form>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Status') }}</th><th>{{ __('Method') }}</th><th>{{ __('Note') }}</th><th style="width:60px">{{ __('Edit') }}</th></tr></thead>
            <tbody>
            @forelse($attendances as $attendance)
                <tr>
                    <td>{{ $attendance->date->format('d/m/Y') }}</td>
                    <td>{{ $attendance->employee->full_name }}</td>
                    <td>{{ $attendance->check_in ?? '—' }}</td>
                    <td>{{ $attendance->check_out ?? '—' }}</td>
                    <td><span class="badge badge-{{ $statusBadge($attendance->status) }}">{{ __($attendance->status) }}</span></td>
                    <td><i class="fas fa-{{ $attendance->method === 'FACIAL' ? 'id-badge' : 'pencil-alt' }}"></i> {{ __($attendance->method) }}</td>
                    <td class="text-muted">{{ $attendance->note }}</td>
                    <td class="text-center">
                        @php
                            $payload = json_encode([
                                'action' => route('attendances.update', $attendance),
                                'employee' => $attendance->employee->full_name,
                                'date' => $attendance->date->format('d/m/Y'),
                                'check_in' => $attendance->check_in ? substr($attendance->check_in, 0, 5) : '',
                                'check_out' => $attendance->check_out ? substr($attendance->check_out, 0, 5) : '',
                                'status' => $attendance->status,
                                'note' => $attendance->note,
                            ]);
                        @endphp
                        <button class="btn btn-sm btn-info" title="{{ __('Edit times/status') }}" data-payload="{{ $payload }}" onclick="openEditModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No records in the period') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="d-flex justify-content-center">{{ $attendances->links() }}</div>
    </div>
</div>

{{-- Manual entry modal --}}
<div class="modal fade" id="manualModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('attendances.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pencil-alt"></i> {{ __('Manual attendance entry') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Employee') }}</label>
                    <select name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" required>
                        <option value="">— {{ __('Select') }} —</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                    @error('employee_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="row">
                    <div class="col form-group">
                        <label>{{ __('Date') }}</label>
                        <input type="date" name="date" value="{{ old('date') }}" class="form-control @error('date') is-invalid @enderror" required>
                        @error('date')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col form-group">
                        <label>{{ __('Status') }}</label>
                        <select name="status" class="form-control" required>
                            @foreach(\App\Models\Attendance::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status') == $status)>{{ __($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col form-group">
                        <label>{{ __('Check-in') }}</label>
                        <input type="time" name="check_in" value="{{ old('check_in') }}" class="form-control @error('check_in') is-invalid @enderror">
                        @error('check_in')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col form-group">
                        <label>{{ __('Check-out') }}</label>
                        <input type="time" name="check_out" value="{{ old('check_out') }}" class="form-control @error('check_out') is-invalid @enderror">
                        @error('check_out')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ __('Note') }}</label>
                    <input name="note" value="{{ old('note') }}" class="form-control" maxlength="200">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-warning"><i class="fas fa-save"></i> {{ __('Register') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit modal --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ old('_form_action') }}" class="modal-content" id="editForm">
            @csrf @method('PUT')
            <input type="hidden" name="_form_action" value="{{ old('_form_action') }}" id="editFormAction">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pencil-alt"></i> <span id="editTitle">{{ __('Edit attendance') }}</span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2"><i class="fas fa-shield-alt"></i> {!! __('This edit will be recorded in the system <strong>audit log</strong>.') !!}</div>
                <div class="row">
                    <div class="col form-group">
                        <label>{{ __('Check-in') }}</label>
                        <input type="time" name="check_in" id="editCheckIn" value="{{ old('check_in') }}" class="form-control @error('check_in') is-invalid @enderror">
                        @error('check_in')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col form-group">
                        <label>{{ __('Check-out') }}</label>
                        <input type="time" name="check_out" id="editCheckOut" value="{{ old('check_out') }}" class="form-control @error('check_out') is-invalid @enderror">
                        @error('check_out')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ __('Status') }}</label>
                    <select name="status" id="editStatus" class="form-control" required>
                        @foreach(\App\Models\Attendance::STATUSES as $status)
                            <option value="{{ $status }}">{{ __($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ __('Note') }}</label>
                    <input name="note" id="editNote" value="{{ old('note') }}" class="form-control" maxlength="200" placeholder="{{ __('Reason for the correction') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-warning"><i class="fas fa-save"></i> {{ __('Save changes') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openManualModal() {
    $('#manualModal').modal('show');
}

function openEditModal(data) {
    const form = document.getElementById('editForm');
    form.action = data.action;
    document.getElementById('editFormAction').value = data.action;
    document.getElementById('editTitle').textContent = data.employee + ' — ' + data.date;
    document.getElementById('editCheckIn').value = data.check_in;
    document.getElementById('editCheckOut').value = data.check_out;
    document.getElementById('editStatus').value = data.status;
    document.getElementById('editNote').value = data.note || '';
    $('#editModal').modal('show');
}

@if($errors->any())
    @if(old('_form_action'))
        $('#editModal').modal('show');
    @else
        $('#manualModal').modal('show');
    @endif
@endif
</script>
@endpush
