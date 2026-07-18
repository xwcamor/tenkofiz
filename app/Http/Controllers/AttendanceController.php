<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        // Default range = current payroll cut-off period (configured in Settings)
        [$periodStart, $periodEnd] = current_period();
        $from = $request->date('from') ?? $periodStart;
        $to = $request->date('to') ?? $periodEnd->min(company_now());

        // Deleted-records view: restricted to administrators (settings module)
        $showDeleted = $request->boolean('deleted') && $request->user()->hasModule('settings');

        // Server-side pagination: this table grows without bounds
        $attendances = Attendance::with('employee')
            ->when($showDeleted, fn ($q) => $q->onlyTrashed())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('date')
            ->orderBy('employee_id')
            ->paginate(50)
            ->withQueryString();

        // The employee selectors use AJAX autocomplete; only resolve the labels
        // of the values already chosen (filter and re-opened modal after errors)
        $selectedEmployee = $request->filled('employee_id') ? Employee::find($request->integer('employee_id')) : null;
        $oldEmployee = old('employee_id') ? Employee::find(old('employee_id')) : null;

        return view('attendances.index', compact('attendances', 'selectedEmployee', 'oldEmployee', 'from', 'to', 'showDeleted'));
    }

    /** Manual entry (e.g. corrections) by managers */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'status' => ['required', 'in:ON_TIME,LATE,ABSENT,EXCUSED'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $data['method'] = 'MANUAL';

        Attendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            $data
        );

        return back()->with('ok', __('Attendance recorded manually.'));
    }

    /** Generates the absences of a date with one click (managers) */
    public function markAbsences(Request $request)
    {
        $date = $request->validate(['date' => ['required', 'date', 'before_or_equal:today']])['date'];

        $created = Attendance::markAbsences($date);

        AuditLog::record('CREATE', 'Attendances', __('Automatic absence generation for :date: :count record(s)', ['date' => $date, 'count' => $created]));

        return back()->with('ok', __('Absences for :date: :count record(s) created. (Holidays, non-working days, vacations and excused days are skipped)', ['date' => $date, 'count' => $created]));
    }

    public function update(Request $request, Attendance $attendance)
    {
        $data = $request->validate([
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'status' => ['required', 'in:ON_TIME,LATE,ABSENT,EXCUSED'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $before = $attendance->toArray();
        $attendance->update($data + ['method' => 'MANUAL']);

        AuditLog::record('UPDATE', 'Attendances',
            __('Attendance of :name on :date was edited', [
                'name' => $attendance->employee->full_name,
                'date' => $attendance->date->format('d/m/Y'),
            ]),
            ['before' => $before, 'after' => $attendance->fresh()->toArray()]);

        return redirect()->route('attendances.index')->with('ok', __('Attendance updated (the change is recorded in the audit log).'));
    }

    public function destroy(Request $request, Attendance $attendance)
    {
        $data = $request->validate(['delete_reason' => ['required', 'string', 'max:300']],
            ['delete_reason.required' => __('The deletion reason is required.')]);

        // Soft delete: recoverable by an administrator from "View deleted"
        $attendance->update(['delete_reason' => $data['delete_reason']]);
        $attendance->delete();

        AuditLog::record('DELETE', 'Attendances',
            __('Attendance of :name on :date was deleted. Reason: :reason', [
                'name' => $attendance->employee->full_name,
                'date' => $attendance->date->format('d/m/Y'),
                'reason' => $data['delete_reason'],
            ]),
            $attendance->toArray());

        return back()->with('ok', __('Attendance deleted. An administrator can restore it from "View deleted".'));
    }

    /** Brings a soft-deleted attendance back (administrators only) */
    public function restore(Request $request, Attendance $attendance)
    {
        abort_unless($request->user()->hasModule('settings'), 403);

        $attendance->restore();
        $attendance->update(['delete_reason' => null]);

        AuditLog::record('UPDATE', 'Attendances',
            __('Attendance of :name on :date was restored', [
                'name' => $attendance->employee->full_name,
                'date' => $attendance->date->format('d/m/Y'),
            ]));

        return back()->with('ok', __('Attendance restored.'));
    }

    /** The employee's own history */
    public function mine(Request $request)
    {
        $employee = $request->user()->employee;

        $attendances = $employee
            ? $employee->attendances()->orderByDesc('date')->paginate(31)->withQueryString()
            : null;

        return view('attendances.mine', compact('attendances', 'employee'));
    }
}
