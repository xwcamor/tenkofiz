@extends('layouts.app')
@section('title', __('Schedules'))
@section('header-button')
    <button class="btn btn-primary" onclick="openScheduleModal()"><i class="fas fa-plus"></i> {{ __('New schedule') }}</button>
@endsection
@section('content')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Working days') }}</th><th>{{ __('Tolerance') }}</th><th>{{ __('Employees') }}</th><th style="width:110px">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($schedules as $schedule)
                <tr>
                    <td>{{ $schedule->name }}
                        @unless($schedule->is_active)<span class="badge badge-secondary ml-1">{{ __('Inactive') }}</span>@endunless
                    </td>
                    <td class="text-muted">{{ $schedule->daysSummary() }}
                        @if($schedule->days->contains(fn ($d) => $d->crossesMidnight()))
                            <span class="badge badge-info ml-1" title="{{ __('A shift that ends past midnight') }}"><i class="fas fa-moon"></i> {{ __('overnight') }}</span>
                        @endif
                    </td>
                    <td>{{ $schedule->tolerance_minutes }} min</td>
                    <td><span class="badge badge-info">{{ $schedule->employees_count }}</span></td>
                    <td>
                        @php
                            $payload = json_encode([
                                'action' => route('schedules.update', $schedule),
                                'name' => $schedule->name,
                                'tolerance_minutes' => $schedule->tolerance_minutes,
                                'is_active' => $schedule->is_active,
                                'days' => $schedule->days->mapWithKeys(fn ($d) => [$d->weekday => [
                                    'start' => substr($d->start_time, 0, 5),
                                    'end' => substr($d->end_time, 0, 5),
                                ]]),
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
                <div class="form-group">
                    <label>{{ __('Working days and hours') }}</label>
                    <small class="text-muted d-block mb-2">{{ __('An end time earlier than the start means the shift crosses midnight (e.g. 22:00 – 06:00).') }}</small>
                    @error('days')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    @php $weekdays = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 0 => __('Sunday')]; @endphp
                    @foreach($weekdays as $weekday => $label)
                        <div class="d-flex align-items-center mb-1" style="gap:.5rem">
                            <div class="custom-control custom-checkbox" style="width:120px">
                                <input type="checkbox" name="days[{{ $weekday }}][on]" value="1" class="custom-control-input day-toggle" id="day{{ $weekday }}" data-weekday="{{ $weekday }}"
                                       @checked(old("days.$weekday.on"))>
                                <label class="custom-control-label" for="day{{ $weekday }}">{{ $label }}</label>
                            </div>
                            <input type="time" name="days[{{ $weekday }}][start]" id="dayStart{{ $weekday }}" value="{{ old("days.$weekday.start") }}" class="form-control form-control-sm" style="width:110px">
                            <span class="text-muted">–</span>
                            <input type="time" name="days[{{ $weekday }}][end]" id="dayEnd{{ $weekday }}" value="{{ old("days.$weekday.end") }}" class="form-control form-control-sm" style="width:110px">
                        </div>
                    @endforeach
                </div>
                <div class="form-group">
                    <label>{{ __('Tardiness tolerance (minutes)') }}</label>
                    <input type="number" name="tolerance_minutes" id="scheduleTolerance" value="{{ old('tolerance_minutes', 10) }}" class="form-control" min="0" max="60" required>
                </div>
                <div class="custom-control custom-switch" id="scheduleActiveRow">
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
    document.getElementById('scheduleTolerance').value = data ? data.tolerance_minutes : 10;
    document.getElementById('scheduleActive').checked = data ? !!data.is_active : true;
    document.getElementById('scheduleActiveRow').style.display = data ? '' : 'none';

    for (let weekday = 0; weekday <= 6; weekday++) {
        const dayData = data && data.days ? data.days[weekday] : null;
        // Sensible default for a new schedule: Monday-Saturday 08:00-17:00
        const defaultOn = !data && weekday >= 1 && weekday <= 6;
        document.getElementById('day' + weekday).checked = dayData ? true : defaultOn;
        document.getElementById('dayStart' + weekday).value = dayData ? dayData.start : (defaultOn ? '08:00' : '');
        document.getElementById('dayEnd' + weekday).value = dayData ? dayData.end : (defaultOn ? '17:00' : '');
    }

    $('#scheduleModal').modal('show');
}

// Ticking a day without hours prefills the previous day's hours
document.querySelectorAll('.day-toggle').forEach(toggle => {
    toggle.addEventListener('change', function () {
        const weekday = this.dataset.weekday;
        const start = document.getElementById('dayStart' + weekday);
        if (this.checked && !start.value) {
            start.value = '08:00';
            document.getElementById('dayEnd' + weekday).value = '17:00';
        }
    });
});

@if($errors->any())
    $('#scheduleModal').modal('show');
@endif
</script>
@endpush
