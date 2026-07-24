{{-- Options for a schedule <select>: shared catalog templates + this employee's
     personalized schedules, grouped. $selected = the currently chosen id (or null). --}}
<option value="">—</option>
@if($schedules->isNotEmpty())
    <optgroup label="{{ __('From the catalog') }}">
        @foreach($schedules as $schedule)
            <option value="{{ $schedule->id }}" data-shared="1" @selected(($selected ?? null) == $schedule->id)>{{ $schedule->name }} — {{ $schedule->daysSummary() }}@if($schedule->rulesSummary()) · {{ $schedule->rulesSummary() }}@endif</option>
        @endforeach
    </optgroup>
@endif
@if(($personalSchedules ?? collect())->isNotEmpty())
    <optgroup label="{{ __('Personalized') }}" class="js-personal-group">
        @foreach($personalSchedules as $schedule)
            <option value="{{ $schedule->id }}" data-shared="0"
                data-tolerance="{{ (int) $schedule->tolerance_minutes }}"
                data-async="{{ (int) $schedule->async_minutes_per_day }}"
                data-days="{{ json_encode($schedule->days->mapWithKeys(fn ($d) => [$d->weekday => ['start' => substr($d->start_time, 0, 5), 'end' => substr($d->end_time, 0, 5)]])) }}"
                @selected(($selected ?? null) == $schedule->id)>{{ $schedule->name }} — {{ $schedule->daysSummary() }}@if($schedule->rulesSummary()) · {{ $schedule->rulesSummary() }}@endif</option>
        @endforeach
    </optgroup>
@endif
