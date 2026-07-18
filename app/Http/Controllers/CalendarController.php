<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /** Calendar of attendances, holidays and vacations (FullCalendar plugin) */
    public function index(Request $request)
    {
        $user = $request->user();
        $isManager = $user->isManager();

        // Managers can pick an employee; employees see their own calendar
        $employee = $isManager && $request->filled('employee_id')
            ? Employee::find($request->integer('employee_id'))
            : Employee::where('user_id', $user->id)->first();

        $events = [];

        if ($employee) {
            // Bounded window: loading every attendance ever would grow without limit
            $calendarStart = company_now()->subMonths(12)->startOfMonth()->toDateString();

            foreach ($employee->attendances()->where('date', '>=', $calendarStart)->orderBy('date')->get() as $attendance) {
                $color = match ($attendance->status) {
                    'ON_TIME' => '#28a745',
                    'LATE' => '#ffc107',
                    'EXCUSED' => '#17a2b8',
                    default => '#dc3545',
                };
                $title = $attendance->check_in
                    ? __('In').': '.substr($attendance->check_in, 0, 5).($attendance->check_out ? ' | '.__('Out').': '.substr($attendance->check_out, 0, 5) : '')
                    : __($attendance->status);
                $events[] = ['title' => $title, 'start' => $attendance->date->toDateString(), 'color' => $color];
            }

            foreach ($employee->vacations()->where('status', 'APPROVED')->get() as $vacation) {
                $events[] = [
                    'title' => __('Vacations'),
                    'start' => $vacation->start_date->toDateString(),
                    'end' => $vacation->end_date->addDay()->toDateString(),
                    'color' => '#6f42c1',
                ];
            }
        }

        foreach (Holiday::all() as $holiday) {
            $events[] = ['title' => '🎉 '.$holiday->name, 'start' => $holiday->date->toDateString(), 'display' => 'background', 'color' => '#f8d7da'];
        }

        return view('calendar.index', compact('events', 'employee', 'isManager'));
    }
}
