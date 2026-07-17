@extends('layouts.app')
@section('title', __('Schedules'))
@section('header-button')
    <button class="btn btn-primary" onclick="openScheduleModal()"><i class="fas fa-plus"></i> {{ __('New schedule') }}</button>
@endsection
@section('content')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Tolerance') }}</th><th>{{ __('Employees') }}</th><th style="width:110px">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($schedules as $schedule)
                <tr>
                    <td>{{ $schedule->name }}</td>
                    <td>{{ substr($schedule->start_time, 0, 5) }}</td>
                    <td>{{ substr($schedule->end_time, 0, 5) }}</td>
                    <td>{{ $schedule->tolerance_minutes }} min</td>
                    <td><span class="badge badge-info">{{ $schedule->employees_count }}</span></td>
                    <td>
                        @php
                            $payload = json_encode([
                                'action' => route('schedules.update', $schedule),
                                'name' => $schedule->name,
                                'start_time' => substr($schedule->start_time, 0, 5),
                                'end_time' => substr($schedule->end_time, 0, 5),
                                'tolerance_minutes' => $schedule->tolerance_minutes,
                                'is_active' => $schedule->is_active,
                            ]);
                        @endphp
                        <button class="btn btn-sm btn-info" data-payload="{{ $payload }}" onclick="openScheduleModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                        <form method="POST" action="{{ route('schedules.destroy', $schedule) }}" class="d-inline delete-form">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Create / edit modal --}}
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ old('_form_action', route('schedules.store')) }}" class="modal-content" id="scheduleForm">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}" id="scheduleMethod">
            <input type="hidden" name="_form_action" value="{{ old('_form_action', route('schedules.store')) }}" id="scheduleFormAction">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clock"></i> {{ __('Schedule') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Name') }}</label>
                    <input name="name" id="scheduleName" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required placeholder="{{ __('E.g.: Morning Shift') }}">
                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="row">
                    <div class="col form-group">
                        <label>{{ __('Start time') }}</label>
                        <input type="time" name="start_time" id="scheduleStart" value="{{ old('start_time') }}" class="form-control @error('start_time') is-invalid @enderror" required>
                        @error('start_time')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col form-group">
                        <label>{{ __('End time') }}</label>
                        <input type="time" name="end_time" id="scheduleEnd" value="{{ old('end_time') }}" class="form-control @error('end_time') is-invalid @enderror" required>
                        @error('end_time')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ __('Tardiness tolerance (minutes)') }}</label>
                    <input type="number" name="tolerance_minutes" id="scheduleTolerance" value="{{ old('tolerance_minutes', 10) }}" class="form-control" min="0" max="60" required>
                </div>
                <div class="custom-control custom-switch">
                    <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="scheduleActive" @checked(old('is_active', true))>
                    <label class="custom-control-label" for="scheduleActive">{{ __('Active') }}</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const SCHEDULE_STORE_URL = @json(route('schedules.store'));

function openScheduleModal(data = null) {
    const form = document.getElementById('scheduleForm');
    form.action = data ? data.action : SCHEDULE_STORE_URL;
    document.getElementById('scheduleFormAction').value = form.action;
    document.getElementById('scheduleMethod').value = data ? 'PUT' : 'POST';
    document.getElementById('scheduleName').value = data ? data.name : '';
    document.getElementById('scheduleStart').value = data ? data.start_time : '';
    document.getElementById('scheduleEnd').value = data ? data.end_time : '';
    document.getElementById('scheduleTolerance').value = data ? data.tolerance_minutes : 10;
    document.getElementById('scheduleActive').checked = data ? !!data.is_active : true;
    $('#scheduleModal').modal('show');
}

@if($errors->any())
    $('#scheduleModal').modal('show');
@endif
</script>
@endpush
