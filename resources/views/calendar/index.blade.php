@extends('layouts.app')
@section('title', __('Attendance calendar'))
@section('content')
@if($isManager)
<div class="card card-outline card-primary mb-3">
    <div class="card-body py-2">
        <form class="form-inline">
            <label class="mr-2">{{ __('View calendar of:') }}</label>
            <select name="employee_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">— {{ __('Select an employee') }} —</option>
                @foreach($employees as $employeeOption)
                    <option value="{{ $employeeOption->id }}" @selected($employee?->id == $employeeOption->id)>{{ $employeeOption->full_name }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>
@endif

@if(!$employee && !$isManager)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('Your user is not linked to an employee.') }}</div>
@else
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-alt"></i> {{ $employee?->full_name ?? __('Select an employee') }}</h3>
        <div class="card-tools small">
            <span class="badge" style="background:#28a745;color:#fff">{{ __('ON_TIME') }}</span>
            <span class="badge" style="background:#ffc107">{{ __('LATE') }}</span>
            <span class="badge" style="background:#17a2b8;color:#fff">{{ __('EXCUSED') }}</span>
            <span class="badge" style="background:#6f42c1;color:#fff">{{ __('Vacations') }}</span>
            <span class="badge" style="background:#f8d7da">{{ __('Holiday') }}</span>
        </div>
    </div>
    <div class="card-body"><div id="calendar"></div></div>
</div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
@if(app()->getLocale() === 'es')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.global.min.js"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('calendar');
    if (!el) return;
    const calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        locale: @json(app()->getLocale()),
        height: 'auto',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
        events: @json($events)
    });
    calendar.render();
});
</script>
@endpush
