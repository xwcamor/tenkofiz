<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::withCount('employees')->orderBy('name')->get();
        return view('schedules.index', compact('schedules'));
    }

    public function store(Request $request)
    {
        Schedule::create($this->validated($request));
        return redirect()->route('schedules.index')->with('ok', __('Schedule created.'));
    }

    public function update(Request $request, Schedule $schedule)
    {
        $schedule->update($this->validated($request, $schedule));
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

    private function validated(Request $request, ?Schedule $schedule = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('schedules')->ignore($schedule)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'tolerance_minutes' => ['required', 'integer', 'min:0', 'max:60'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
