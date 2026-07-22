{{-- Options for a schedule <select>: shared catalog templates + this employee's
     personalized schedules, grouped. $selected = the currently chosen id (or null). --}}
<option value="">—</option>
@if($schedules->isNotEmpty())
    <optgroup label="{{ __('From the catalog') }}">
        @foreach($schedules as $schedule)
            <option value="{{ $schedule->id }}" @selected(($selected ?? null) == $schedule->id)>{{ $schedule->name }} — {{ $schedule->daysSummary() }}</option>
        @endforeach
    </optgroup>
@endif
@if(($personalSchedules ?? collect())->isNotEmpty())
    <optgroup label="{{ __('Personalized') }}" class="js-personal-group">
        @foreach($personalSchedules as $schedule)
            <option value="{{ $schedule->id }}" @selected(($selected ?? null) == $schedule->id)>{{ $schedule->name }} — {{ $schedule->daysSummary() }}</option>
        @endforeach
    </optgroup>
@endif
