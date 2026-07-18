<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::withCount('employees')->with('days')->orderBy('name')->get();
        return view('schedules.index', compact('schedules'));
    }

    public function store(Request $request)
    {
        [$data, $days] = $this->validated($request);

        DB::transaction(function () use ($data, $days) {
            $schedule = Schedule::create($data);
            $schedule->days()->createMany($days);
        });

        return redirect()->route('schedules.index')->with('ok', __('Schedule created.'));
    }

    public function update(Request $request, Schedule $schedule)
    {
        [$data, $days] = $this->validated($request, $schedule);

        DB::transaction(function () use ($schedule, $data, $days) {
            $schedule->update($data);
            $schedule->days()->delete();
            $schedule->days()->createMany($days);
        });

        return redirect()->route('schedules.index')->with('ok', __('Schedule updated.'));
    }

    public function destroy(Schedule $schedule)
    {
        if ($schedule->employees()->exists()) {
            return back()->with('error', __('Cannot delete: there are employees assigned.'));
        }
        AuditLog::record('DELETE', 'Schedules',
            __('Schedule :name was deleted', ['name' => $schedule->name]), $schedule->toArray());
        $schedule->delete();
        return back()->with('ok', __('Schedule deleted.'));
    }

    /** Returns [schedule attributes, day rows]. Days come as days[weekday][on|start|end]. */
    private function validated(Request $request, ?Schedule $schedule = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('schedules')->ignore($schedule)->where('company_id', current_company_id())],
            'tolerance_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'days' => ['required', 'array'],
            'days.*.on' => ['nullable', 'boolean'],
            'days.*.start' => ['nullable', 'date_format:H:i'],
            'days.*.end' => ['nullable', 'date_format:H:i'],
        ], [
            'days.required' => __('Select at least one working day.'),
        ]);

        $days = [];
        foreach (range(0, 6) as $weekday) {
            $day = $data['days'][$weekday] ?? null;
            if (!($day['on'] ?? false)) {
                continue;
            }
            if (empty($day['start']) || empty($day['end']) || $day['start'] === $day['end']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'days' => __('Each working day needs a start and an end time (an end before the start means the shift crosses midnight).'),
                ]);
            }
            $days[] = ['weekday' => $weekday, 'start_time' => $day['start'], 'end_time' => $day['end']];
        }

        if (!$days) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'days' => __('Select at least one working day.'),
            ]);
        }

        return [
            [
                'name' => $data['name'],
                'tolerance_minutes' => $data['tolerance_minutes'],
                'is_active' => $schedule ? $request->boolean('is_active') : true,
            ],
            $days,
        ];
    }
}
