<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    use \App\Http\Controllers\Concerns\Sortable;

    public function index(Request $request)
    {
        // Only the shared catalog templates. Personalized (per-employee) schedules
        // live on their employee and never clutter this page.
        $query = Schedule::shared()->withCount('employees')->with('days');
        [$sort, $dir] = $this->applySort($query, $request, [
            'name' => 'name', 'tolerance' => 'tolerance_minutes', 'employees' => 'employees_count',
        ], 'name');
        $schedules = $query->get();
        return view('schedules.index', compact('schedules', 'sort', 'dir'));
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

    /**
     * Quick-create a fixed schedule from the employee form (the "+ New schedule"
     * shortcut and the per-period "personalize" pencil), with its OWN hours per
     * weekday. Returns JSON so the caller can add it to the select.
     */
    public function quickStore(Request $request)
    {
        return $this->quickSave($request, null);
    }

    /**
     * Edit a PERSONALIZED (per-employee, non-catalog) schedule from the employee
     * form. Shared catalog schedules are managed on the Schedules page instead, so
     * editing one here (which would affect everyone) is refused.
     */
    public function quickUpdate(Request $request, $id)
    {
        // findOrFail runs through the tenant scope, so another company's id 404s.
        $schedule = Schedule::findOrFail($id);
        abort_unless(!$schedule->is_shared && $schedule->company_id === current_company_id(), 403);

        return $this->quickSave($request, $schedule);
    }

    /**
     * Shared create/update for the employee-form schedule editor. Accepts a per-day
     * payload (days[] each with its own weekday/start/end) so a schedule can have
     * different hours per day; an end before the start is a valid overnight shift.
     */
    private function quickSave(Request $request, ?Schedule $schedule)
    {
        $shared = $schedule ? (bool) $schedule->is_shared : $request->boolean('is_shared', true);

        // Personalized (per-employee) schedules may repeat a name; only the shared
        // catalog enforces uniqueness.
        $nameRule = ['required', 'string', 'max:100'];
        if ($shared) {
            $nameRule[] = Rule::unique('schedules')->ignore($schedule)->where('company_id', current_company_id())->where('is_shared', true);
        }

        $data = $request->validate([
            'name' => $nameRule,
            'days' => ['required', 'array', 'min:1'],
            'days.*.weekday' => ['required', 'integer', 'between:0,6'],
            'days.*.start' => ['required', 'date_format:H:i'],
            'days.*.end' => ['required', 'date_format:H:i'],
            'tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'async_minutes_per_day' => ['nullable', 'integer', 'min:0', 'max:600'],
        ], [
            'name.unique' => __('A schedule with that name already exists.'),
        ]);

        // One row per weekday (dedup), each with its own hours. An end EQUAL to the
        // start is invalid; an end before the start is an overnight shift (allowed).
        $days = collect($data['days'])->unique('weekday')->map(function ($d) {
            if ($d['start'] === $d['end']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'days' => __('Each working day needs a start and an end time (an end before the start means the shift crosses midnight).'),
                ]);
            }

            return ['weekday' => (int) $d['weekday'], 'start_time' => $d['start'], 'end_time' => $d['end']];
        })->values()->all();

        $attributes = [
            'name' => $data['name'],
            'is_shared' => $shared,
            'type' => Schedule::TYPE_FIXED,
            'tolerance_minutes' => $data['tolerance_minutes'] ?? 5,
            'async_minutes_per_day' => (int) ($data['async_minutes_per_day'] ?? 0),
            'is_active' => true,
        ];

        $schedule = DB::transaction(function () use ($schedule, $attributes, $days) {
            if ($schedule) {
                $schedule->update($attributes);
                $schedule->days()->delete();
            } else {
                $schedule = Schedule::create($attributes);
            }
            $schedule->days()->createMany($days);

            return $schedule;
        });

        AuditLog::record($schedule->wasRecentlyCreated ? 'CREATE' : 'UPDATE', 'Schedules',
            __('Quick schedule ":name" saved from the employee form', ['name' => $schedule->name]));

        $schedule->load('days');

        return response()->json([
            'id' => $schedule->id,
            'name' => $schedule->name,
            'summary' => $schedule->daysSummary(),
            'rules' => $schedule->rulesSummary(),
            'shared' => (bool) $schedule->is_shared,
            'tolerance_minutes' => (int) $schedule->tolerance_minutes,
            'async_minutes_per_day' => (int) $schedule->async_minutes_per_day,
            'days' => $schedule->days->mapWithKeys(fn ($d) => [$d->weekday => [
                'start' => substr($d->start_time, 0, 5),
                'end' => substr($d->end_time, 0, 5),
            ]]),
        ]);
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
        // Design rule: a workspace must always keep at least one schedule, so new
        // employees can always be registered. The last one in the catalog is locked.
        if (Schedule::shared()->count() <= 1) {
            return back()->with('error', __('You must keep at least one schedule.'));
        }

        // Blocked while in use — as someone's base schedule OR inside a dated period
        // (vigencia). Without the second check the DB foreign key would 500.
        if ($schedule->employees()->exists() || \App\Models\EmployeeSchedule::where('schedule_id', $schedule->id)->exists()) {
            return back()->with('error', __('Cannot delete: there are employees assigned (as their schedule or in a scheduled period).'));
        }
        AuditLog::record('DELETE', 'Schedules',
            __('Schedule :name was deleted', ['name' => $schedule->name]), $schedule->toArray());
        $schedule->delete();
        return back()->with('ok', __('Schedule deleted.'));
    }

    /** Returns [schedule attributes, day rows]. Days come as days[weekday][on|start|end]. */
    private function validated(Request $request, ?Schedule $schedule = null): array
    {
        $isFree = $request->input('type') === Schedule::TYPE_FREE;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('schedules')->ignore($schedule)->where('company_id', current_company_id())->where('is_shared', true)],
            'type' => ['required', Rule::in([Schedule::TYPE_FIXED, Schedule::TYPE_FLEXIBLE, Schedule::TYPE_FREE])],
            'tolerance_minutes' => [$isFree ? 'nullable' : 'required', 'integer', 'min:0', 'max:60'],
            'target_hours' => ['nullable', 'numeric', 'min:0.5', 'max:24'],
            'async_minutes_per_day' => ['nullable', 'integer', 'min:0', 'max:600'],
            // Free mode has no working days or times to configure.
            'days' => [$isFree ? 'nullable' : 'required', 'array'],
            'days.*.on' => ['nullable', 'boolean'],
            'days.*.start' => ['nullable', 'date_format:H:i'],
            'days.*.end' => ['nullable', 'date_format:H:i'],
        ], [
            'days.required' => __('Select at least one working day.'),
        ]);

        // Free mode: no days, no tolerance, no target — just a named marking mode.
        if ($isFree) {
            return [
                [
                    'name' => $data['name'],
                    'type' => Schedule::TYPE_FREE,
                    'tolerance_minutes' => 0,
                    'target_minutes' => null,
                    'async_minutes_per_day' => 0,
                    'is_active' => $schedule ? $request->boolean('is_active') : true,
                ],
                [], // no day rows
            ];
        }

        $flexible = $data['type'] === Schedule::TYPE_FLEXIBLE;

        if ($flexible && empty($data['target_hours'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'target_hours' => __('A flexible schedule needs a daily hour target.'),
            ]);
        }

        $days = [];
        foreach (range(0, 6) as $weekday) {
            $day = $data['days'][$weekday] ?? null;
            if (!($day['on'] ?? false)) {
                continue;
            }
            if ($flexible) {
                // Flexible: only WHICH days are worked matters (for absences); the
                // times are not used to judge punctuality, so store placeholders.
                $days[] = ['weekday' => $weekday, 'start_time' => '00:00', 'end_time' => '00:00'];

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
                'type' => $data['type'],
                'tolerance_minutes' => $flexible ? 0 : $data['tolerance_minutes'],
                'target_minutes' => $flexible ? (int) round($data['target_hours'] * 60) : null,
                'async_minutes_per_day' => (int) ($data['async_minutes_per_day'] ?? 0),
                'is_active' => $schedule ? $request->boolean('is_active') : true,
            ],
            $days,
        ];
    }
}
