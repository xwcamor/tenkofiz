@extends('layouts.app')
@section('title', __('Attendance'))
@section('header-button')
    <div class="d-flex">
        @if(auth()->user()->hasModule('settings'))
            @if($showDeleted)
                <a href="{{ route('attendances.index', request()->except('deleted')) }}" class="btn btn-outline-secondary btn-sm mr-2"><i class="fas fa-arrow-left"></i> {{ __('Back to list') }}</a>
            @else
                <a href="{{ route('attendances.index', ['deleted' => 1] + request()->query()) }}" class="btn btn-outline-secondary btn-sm mr-2" title="{{ __('Deleted records (only administrators see this view)') }}"><i class="fas fa-trash-restore"></i> {{ __('View deleted') }}</a>
            @endif
        @endif
        @unless($showDeleted)
            <form method="POST" action="{{ route('attendances.markAbsences') }}" class="form-inline mr-2">
                @csrf
                <input type="date" name="date" value="{{ company_now()->toDateString() }}" max="{{ company_now()->toDateString() }}" class="form-control form-control-sm mr-1" required>
                <button class="btn btn-danger btn-sm" title="{{ __('Marks ABSENT everyone without an attendance record that day (skips holidays, non-working days, vacations)') }}"><i class="fas fa-user-times"></i> {{ __('Generate absences') }}</button>
            </form>
            <button class="btn btn-outline-primary btn-sm" onclick="openManualModal()"><i class="fas fa-plus"></i> {{ __('Manual entry') }}</button>
        @endunless
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
    // Worked hours for a single row (break time already subtracted by the model)
    $workedHours = function ($attendance) {
        if (!$attendance->check_in || !$attendance->check_out) {
            return '—';
        }
        $minutes = $attendance->workedMinutes();
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    };
    $breakLimit = (int) (app_setting()->break_limit_minutes ?? 60);
@endphp

<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <label class="mr-2">{{ __('From') }}</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm mr-3">
            <label class="mr-2">{{ __('To') }}</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm mr-3">
            <select name="employee_id" class="employee-select mr-3" data-url="{{ route('employees.search') }}"
                    data-placeholder="{{ __('All employees') }}" data-width="240px"
                    @if($selectedEmployee) data-selected-id="{{ $selectedEmployee->id }}" data-selected-text="{{ $selectedEmployee->full_name }}" @endif></select>
            <select name="status" class="form-control form-control-sm mr-3">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(\App\Models\Attendance::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') == $status)>{{ __($status) }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
            @if(app_setting()->cutoff_day)
                @php [$periodStart, $periodEnd] = current_period(); @endphp
                <span class="badge badge-info ml-3" title="{{ __('Configured in Settings (cut-off day :day)', ['day' => app_setting()->cutoff_day]) }}">
                    <i class="fas fa-cut"></i> {{ __('Current period') }}: {{ $periodStart->format('d/m') }} – {{ $periodEnd->format('d/m') }}
                </span>
            @endif
        </form>
    </div>
    <div class="card-body">
        @if($showDeleted)
            <div class="alert alert-warning py-2"><i class="fas fa-trash-restore"></i> {{ __('You are viewing deleted records. Restoring brings them back with all their history.') }}</div>
        @endif
        <table class="table table-bordered table-hover">
            @if($showDeleted)
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Status') }}</th><th>{{ __('Deleted on') }}</th><th>{{ __('Reason for deletion') }}</th><th style="width:120px">{{ __('Actions') }}</th></tr></thead>
            @else
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Status') }}</th><th>{{ __('Method') }}</th><th>{{ __('Note') }}</th><th style="width:90px">{{ __('Actions') }}</th></tr></thead>
            @endif
            <tbody>
            @forelse($attendances as $attendance)
                @if($showDeleted)
                    <tr>
                        <td>{{ $attendance->date->format('d/m/Y') }}</td>
                        <td>{{ $attendance->employee->full_name }}</td>
                        <td><span class="badge badge-{{ $statusBadge($attendance->status) }}">{{ __($attendance->status) }}</span></td>
                        <td>{{ to_user_tz($attendance->deleted_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $attendance->delete_reason ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('attendances.restore', $attendance->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" title="{{ __('Restore') }}"><i class="fas fa-trash-restore"></i> {{ __('Restore') }}</button>
                            </form>
                        </td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $attendance->date->format('d/m/Y') }}</td>
                        <td>{{ $attendance->employee->full_name }}</td>
                        <td>{{ $attendance->check_in ? substr($attendance->check_in, 0, 5) : '—' }}
                            @if($attendance->marks->isNotEmpty())
                                <button class="btn btn-xs btn-outline-secondary ml-1" title="{{ __('Show raw punches (kiosk log)') }}" onclick="toggleMarks({{ $attendance->id }})"><i class="fas fa-stream"></i> {{ $attendance->marks->count() }}</button>
                            @endif
                        </td>
                        <td>
                            @if($attendance->check_out)
                                {{ substr($attendance->check_out, 0, 5) }}
                            @elseif($attendance->isOpen() && $attendance->date->lt(\Carbon\Carbon::today()))
                                <span class="badge badge-danger" title="{{ __('Checked in but never checked out — review or justify') }}"><i class="fas fa-triangle-exclamation"></i> {{ __('Open') }}</span>
                            @else
                                —
                            @endif
                            @if($attendance->breakMinutes() > 0)
                                <br><small class="{{ $breakLimit > 0 && $attendance->breakExceededMinutes($breakLimit) > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}" title="{{ __('Break :out–:in', ['out' => substr($attendance->break_out,0,5), 'in' => substr($attendance->break_in,0,5)]) }}">
                                    <i class="fas fa-mug-hot"></i> {{ $attendance->breakMinutes() }}m{{ $breakLimit > 0 && $attendance->breakExceededMinutes($breakLimit) > 0 ? ' ('.__('exceeded :n', ['n' => $attendance->breakExceededMinutes($breakLimit)]).')' : '' }}
                                </small>
                            @endif
                        </td>
                        <td class="text-center font-weight-bold">{{ $workedHours($attendance) }}</td>
                        <td><span class="badge badge-{{ $statusBadge($attendance->status) }}">{{ __($attendance->status) }}</span></td>
                        <td>
                            @if($attendance->method === 'DNI')
                                <span class="badge badge-warning" title="{{ __('Marked by typing the document number: verify with the evidence photo') }}"><i class="fas fa-keyboard"></i> DNI</span>
                            @else
                                <i class="fas fa-{{ $attendance->method === 'FACIAL' ? 'id-badge' : 'pencil-alt' }}"></i> {{ __($attendance->method) }}
                            @endif
                            @if($attendance->evidence_photo)
                                <a href="{{ asset($attendance->evidence_photo) }}" target="_blank" class="btn btn-xs btn-outline-secondary ml-1 file-preview" title="{{ __('View evidence photo') }}"><i class="fas fa-camera"></i></a>
                            @endif
                        </td>
                        <td class="text-muted">{{ $attendance->note }}</td>
                        <td class="text-center text-nowrap">
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
                            <form method="POST" action="{{ route('attendances.destroy', $attendance) }}" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @if($attendance->marks->isNotEmpty())
                        <tr id="marks-{{ $attendance->id }}" style="display:none">
                            <td></td>
                            <td colspan="8" class="bg-light">
                                <small class="text-muted mr-1"><i class="fas fa-stream"></i> {{ __('Raw punches (kiosk log):') }}</small>
                                @foreach($attendance->marks as $mark)
                                    <span class="badge badge-light border mr-1">
                                        {{ to_user_tz($mark->marked_at)->format('H:i:s') }} ·
                                        @switch($mark->kind)
                                            @case('CHECK_IN'){{ __('Check-in') }}@break
                                            @case('CHECK_OUT'){{ __('Check-out') }}@break
                                            @case('BREAK_OUT'){{ __('Break start') }}@break
                                            @case('BREAK_IN'){{ __('Break end') }}@break
                                            @default{{ $mark->kind }}
                                        @endswitch ·
                                        {{ __($mark->method) }}
                                        @if($mark->hasLocation())
                                            <a href="https://www.google.com/maps?q={{ $mark->lat }},{{ $mark->lng }}" target="_blank" rel="noopener" class="ml-1 text-danger" title="{{ __('Location') }}: {{ $mark->lat }}, {{ $mark->lng }}@if($mark->accuracy) (±{{ $mark->accuracy }} m)@endif"><i class="fas fa-map-marker-alt"></i></a>
                                        @endif
                                    </span>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                @endif
            @empty
                <tr><td colspan="{{ $showDeleted ? 6 : 9 }}" class="text-center text-muted py-4">{{ $showDeleted ? __('No deleted records.') : __('No records in the period') }}</td></tr>
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
                    <select name="employee_id" class="employee-select @error('employee_id') is-invalid @enderror"
                            data-url="{{ route('employees.search') }}" data-placeholder="{{ __('Search by name or document…') }}"
                            @if($oldEmployee) data-selected-id="{{ $oldEmployee->id }}" data-selected-text="{{ $oldEmployee->full_name }}" @endif></select>
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
function toggleMarks(id) {
    const row = document.getElementById('marks-' + id);
    if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}

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
