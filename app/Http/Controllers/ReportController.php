<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Report of worked hours and days per employee within a date range */
    public function index(Request $request)
    {
        // Default range = current payroll cut-off period (configured in Settings)
        [$periodStart, $periodEnd] = current_period();
        $from = $request->date('from') ?? $periodStart;
        $to = $request->date('to') ?? $periodEnd->min(company_now());

        $employees = Employee::with([
            'area', 'position', 'schedule',
            'attendances' => fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString()]),
            'vacations' => fn ($q) => $q->where('status', 'APPROVED'),
        ])->where('is_active', true)->orderBy('last_name')->get();

        $rows = $employees->map(function ($employee) {
            $attendances = $employee->attendances;

            $minutes = 0;
            foreach ($attendances as $attendance) {
                if ($attendance->check_in && $attendance->check_out) {
                    $start = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
                    $end = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_out);
                    $minutes += $start->diffInMinutes($end);
                }
            }

            return [
                'id' => $employee->id,
                'employee' => $employee->full_name,
                'document_number' => $employee->document_number,
                'area' => $employee->area?->name ?? '—',
                'position' => $employee->position?->name ?? '—',
                'worked_days' => $attendances->whereNotNull('check_in')->whereIn('status', ['ON_TIME', 'LATE'])->count(),
                'on_time' => $attendances->where('status', 'ON_TIME')->count(),
                'late' => $attendances->where('status', 'LATE')->count(),
                'absent' => $attendances->where('status', 'ABSENT')->count(),
                'excused' => $attendances->where('status', 'EXCUSED')->count(),
                'worked_hours' => sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60),
                'vacation_days' => $employee->vacations->sum('days'),
            ];
        });

        return view('reports.index', compact('rows', 'from', 'to'));
    }

    /** Redirects the logged-in employee to their own report sheet */
    public function mySheet(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (!$employee) {
            return redirect()->route('dashboard')->with('error', __('Your user is not linked to an employee.'));
        }

        return redirect()->route('reports.sheet', ['employee' => $employee->id] + $request->only(['from', 'to']));
    }

    /** Printable formal sheet (PDF via browser): managers see anyone, employees only their own */
    public function sheet(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (!$user->hasModule('reports') && $employee->user_id !== $user->id) {
            abort(403, __('You can only view your own sheet.'));
        }

        // Default range = current payroll cut-off period (configured in Settings)
        [$periodStart, $periodEnd] = current_period();
        $from = $request->date('from') ?? $periodStart;
        $to = $request->date('to') ?? $periodEnd->min(company_now());

        $setting = app_setting();

        $attendances = $employee->attendances()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $minutes = 0;
        foreach ($attendances as $attendance) {
            if ($attendance->check_in && $attendance->check_out) {
                $minutes += \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in)
                    ->diffInMinutes(\Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_out));
            }
        }

        $summary = [
            'days' => $attendances->whereIn('status', ['ON_TIME', 'LATE'])->count(),
            'on_time' => $attendances->where('status', 'ON_TIME')->count(),
            'late' => $attendances->where('status', 'LATE')->count(),
            'absent' => $attendances->where('status', 'ABSENT')->count(),
            'excused' => $attendances->where('status', 'EXCUSED')->count(),
            'hours' => sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60),
        ];

        $vacations = $employee->vacations()
            ->where('status', 'APPROVED')
            ->where(fn ($q) => $q->whereBetween('start_date', [$from, $to])->orWhereBetween('end_date', [$from, $to]))
            ->get();

        $justifications = $employee->justifications()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return view('reports.sheet', compact('employee', 'setting', 'attendances', 'summary', 'vacations', 'justifications', 'from', 'to'));
    }
}
