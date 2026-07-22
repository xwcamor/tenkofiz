@extends('layouts.app')
@section('title', __('Schedules'))
@section('header-button')
    <button class="btn btn-primary" onclick="openScheduleModal()"><i class="fas fa-plus"></i> {{ __('New schedule') }}</button>
@endsection
@section('content')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table" data-server-sort>
            <thead><tr>
                @include('partials.th-sort', ['key' => 'name', 'label' => __('Name')])
                <th>{{ __('Working days') }}</th>
                @include('partials.th-sort', ['key' => 'tolerance', 'label' => __('Tolerance')])
                @if(app_setting()->async_hours_enabled)
                    <th title="{{ __('Remote hours credited as done, per working day') }}">{{ __('Credited (async)') }}</th>
                @endif
                @include('partials.th-sort', ['key' => 'employees', 'label' => __('Employees')])
                <th style="width:110px">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse($schedules as $schedule)
                <tr>
                    <td>{{ $schedule->name }}
                        @unless($schedule->is_active)<span class="badge badge-secondary ml-1">{{ __('Inactive') }}</span>@endunless
                    </td>
                    <td class="text-muted">{{ $schedule->daysSummary() }}
                        @if($schedule->isFlexible())
                            <span class="badge badge-warning ml-1"><i class="fas fa-hourglass-half"></i> {{ __('By hours') }}</span>
                        @elseif($schedule->days->contains(fn ($d) => $d->crossesMidnight()))
                            <span class="badge badge-info ml-1" title="{{ __('A shift that ends past midnight') }}"><i class="fas fa-moon"></i> {{ __('overnight') }}</span>
                        @endif
                    </td>
                    <td>{{ $schedule->isFlexible() ? '—' : $schedule->tolerance_minutes.' min' }}</td>
                    @if(app_setting()->async_hours_enabled)
                        <td>
                            @if($schedule->async_minutes_per_day > 0)
                                <span class="badge badge-primary" title="{{ __('Credited as done every working day') }}"><i class="fas fa-wifi"></i> {{ round($schedule->async_minutes_per_day / 60, 1) }} h</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @endif
                    <td><span class="badge badge-info">{{ $schedule->employees_count }}</span></td>
                    <td>
                        @php
                            $payload = json_encode([
                                'action' => route('schedules.update', $schedule),
                                'name' => $schedule->name,
                                'type' => $schedule->type,
                                'tolerance_minutes' => $schedule->tolerance_minutes,
                                'target_hours' => $schedule->target_minutes ? round($schedule->target_minutes / 60, 2) : '',
                                'async_minutes_per_day' => $schedule->async_minutes_per_day,
                                'is_active' => $schedule->is_active,
                                'days' => $schedule->days->mapWithKeys(fn ($d) => [$d->weekday => [
                                    'start' => substr($d->start_time, 0, 5),
                                    'end' => substr($d->end_time, 0, 5),
                                ]]),
                            ]);
                        @endphp
                        <button class="btn btn-sm btn-info" data-payload="{{ $payload }}" onclick="openScheduleModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                        @if($schedules->count() <= 1)
                            <button class="btn btn-sm btn-secondary" disabled title="{{ __('At least one schedule must exist') }}"><i class="fas fa-lock"></i></button>
                        @else
                            <form method="POST" action="{{ route('schedules.destroy', $schedule) }}" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ app_setting()->async_hours_enabled ? 6 : 5 }}" class="text-center text-muted py-4">{{ __('No schedules yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Create / edit modal --}}
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <form method="POST" action="{{ old('_form_action', route('schedules.store')) }}" class="modal-content" id="scheduleForm">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}" id="scheduleMethod">
            <input type="hidden" name="_form_action" value="{{ old('_form_action', route('schedules.store')) }}" id="scheduleFormAction">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clock text-primary mr-1"></i> {{ __('Schedule') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Name') }}</label>
                    <input name="name" id="scheduleName" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required placeholder="{{ __('E.g.: Morning Shift') }}">
                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Schedule type') }}@include('partials.help', ['text' => __('Flexible: no fixed start, no tardiness — the person just has to complete their daily hours (teachers, consultants, part-time).')])</label>
                    <select name="type" id="scheduleType" class="form-control" onchange="applyScheduleType(this.value)">
                        <option value="fixed" @selected(old('type', 'fixed') === 'fixed')>{{ __('Fixed — start time with tolerance (tardiness applies)') }}</option>
                        <option value="flexible" @selected(old('type') === 'flexible')>{{ __('Flexible — complete an hour target (no tardiness)') }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="d-flex align-items-center flex-wrap mb-2" style="gap:.4rem">
                        <label class="mb-0">{{ __('Working days') }} <span id="scheduleHoursLabel">{{ __('and hours') }}</span></label>
                        <span id="scheduleOvernightNote">@include('partials.help', ['text' => __('An end time earlier than the start means the shift crosses midnight (e.g. 22:00 – 06:00).')])</span>
                    </div>
                    @error('days')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    @php $weekdays = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 0 => __('Sunday')]; @endphp
                    <div style="border:1px solid var(--hairline); border-radius:8px; overflow:hidden">
                        <div class="d-flex align-items-center px-3 py-2 fixed-time" style="gap:.5rem; background:var(--brand-soft); border-bottom:1px solid var(--hairline)">
                            <span class="small font-weight-bold text-muted" style="width:130px">{{ __('Day') }}</span>
                            <span class="small font-weight-bold text-muted" style="width:110px">{{ __('Start') }}</span>
                            <span style="width:14px"></span>
                            <span class="small font-weight-bold text-muted" style="width:110px">{{ __('End') }}</span>
                        </div>
                        @foreach($weekdays as $weekday => $label)
                            <div class="d-flex align-items-center px-3 py-2" style="gap:.5rem; border-bottom:1px solid var(--hairline)">
                                <div class="custom-control custom-checkbox mb-0" style="width:130px">
                                    <input type="checkbox" name="days[{{ $weekday }}][on]" value="1" class="custom-control-input day-toggle" id="day{{ $weekday }}" data-weekday="{{ $weekday }}"
                                           @checked(old("days.$weekday.on"))>
                                    <label class="custom-control-label" for="day{{ $weekday }}">{{ $label }}</label>
                                </div>
                                <input type="time" name="days[{{ $weekday }}][start]" id="dayStart{{ $weekday }}" value="{{ old("days.$weekday.start") }}" class="form-control form-control-sm fixed-time" style="width:110px">
                                <span class="text-muted fixed-time" style="width:14px; text-align:center">–</span>
                                <input type="time" name="days[{{ $weekday }}][end]" id="dayEnd{{ $weekday }}" value="{{ old("days.$weekday.end") }}" class="form-control form-control-sm fixed-time" style="width:110px">
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-sm-6" id="scheduleToleranceRow">
                        <label>{{ __('Tardiness tolerance (minutes)') }}</label>
                        <input type="number" name="tolerance_minutes" id="scheduleTolerance" value="{{ old('tolerance_minutes', 5) }}" class="form-control" min="0" max="60" required style="max-width:160px">
                    </div>
                    <div class="form-group col-sm-6" id="scheduleTargetRow" style="display:none">
                        <label>{{ __('Daily hour target') }}@include('partials.help', ['text' => __('Hours the person must complete each working day. Reports show worked hours against this target.')])</label>
                        <input type="number" step="0.5" name="target_hours" id="scheduleTarget" value="{{ old('target_hours') }}" class="form-control @error('target_hours') is-invalid @enderror" min="0.5" max="24" style="max-width:160px">
                        @error('target_hours')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                @if(app_setting()->async_hours_enabled)
                <div class="form-group" id="scheduleAsyncRow">
                    <label>{{ __('Async / credited minutes per day') }}@include('partials.help', ['text' => __('Remote hours that cannot be marked. Counted as done (never a deficit) on each working day. 0 = none.')])</label>
                    <input type="number" name="async_minutes_per_day" id="scheduleAsync" value="{{ old('async_minutes_per_day', 0) }}" class="form-control" min="0" max="600" style="max-width:160px">
                </div>
                @endif
                <div class="custom-control custom-switch mt-2" id="scheduleActiveRow">
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

function applyScheduleType(type) {
    const flexible = type === 'flexible';
    document.getElementById('scheduleToleranceRow').style.display = flexible ? 'none' : '';
    document.getElementById('scheduleTargetRow').style.display = flexible ? '' : 'none';
    document.getElementById('scheduleOvernightNote').style.display = flexible ? 'none' : '';
    document.getElementById('scheduleHoursLabel').style.display = flexible ? 'none' : '';
    document.querySelectorAll('.fixed-time').forEach(el => el.style.display = flexible ? 'none' : '');
}

function openScheduleModal(data = null) {
    const form = document.getElementById('scheduleForm');
    form.action = data ? data.action : SCHEDULE_STORE_URL;
    document.getElementById('scheduleFormAction').value = form.action;
    document.getElementById('scheduleMethod').value = data ? 'PUT' : 'POST';
    document.getElementById('scheduleName').value = data ? data.name : '';
    document.getElementById('scheduleType').value = data ? (data.type || 'fixed') : 'fixed';
    document.getElementById('scheduleTolerance').value = data ? data.tolerance_minutes : 5;
    document.getElementById('scheduleTarget').value = data ? (data.target_hours || '') : '';
    const asyncEl = document.getElementById('scheduleAsync');
    if (asyncEl) asyncEl.value = data ? (data.async_minutes_per_day || 0) : 0;
    document.getElementById('scheduleActive').checked = data ? !!data.is_active : true;
    document.getElementById('scheduleActiveRow').style.display = data ? '' : 'none';
    applyScheduleType(document.getElementById('scheduleType').value);

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
    applyScheduleType(document.getElementById('scheduleType').value);
    $('#scheduleModal').modal('show');
@endif
</script>
@endpush
