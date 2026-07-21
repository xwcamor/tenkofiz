<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use \App\Http\Controllers\Concerns\Sortable;

    public function index(Request $request)
    {
        // Default range = current payroll cut-off period (configured in Settings)
        [$periodStart, $periodEnd] = current_period();
        $from = $request->date('from') ?? $periodStart;
        $to = $request->date('to') ?? $periodEnd->min(company_now());

        // Deleted-records view: restricted to administrators (settings module)
        $showDeleted = $request->boolean('deleted') && $request->user()->hasModule('settings');

        // Server-side pagination: this table grows without bounds
        $attendances = Attendance::with('employee.site', 'marks')
            ->inCurrentSite()
            ->when($showDeleted, fn ($q) => $q->onlyTrashed())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('site_id', $request->integer('site_id'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $employeeName = fn ($d) => Employee::withTrashed()->select('last_name')->whereColumn('employees.id', 'attendances.employee_id');
        [$sort, $dir] = $this->applySort($attendances, $request, [
            'date' => fn ($q, $d) => $q->orderBy('date', $d)->orderBy('employee_id'),
            'employee' => fn ($q, $d) => $q->orderBy($employeeName($d), $d),
            'site' => fn ($q, $d) => $q->orderBy(Employee::withTrashed()->select('site_id')->whereColumn('employees.id', 'attendances.employee_id'), $d),
            'check_in' => 'check_in',
            'check_out' => 'check_out',
            'status' => 'status',
            'method' => 'method',
        ], 'date', 'desc');

        $attendances = $attendances->paginate(50)->withQueryString();

        // The employee selectors use AJAX autocomplete; only resolve the labels
        // of the values already chosen (filter and re-opened modal after errors)
        $selectedEmployee = $request->filled('employee_id') ? Employee::find($request->integer('employee_id')) : null;
        $oldEmployee = old('employee_id') ? Employee::find(old('employee_id')) : null;
        $sites = $this->visibleSites($request);

        return view('attendances.index', compact('attendances', 'selectedEmployee', 'oldEmployee', 'from', 'to', 'showDeleted', 'sites', 'sort', 'dir'));
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

    /** The employee's own history, filtered by month (bounded: max 31 rows) */
    public function mine(Request $request)
    {
        $employee = $request->user()->employee;

        $month = $request->filled('month')
            ? \Carbon\Carbon::parse($request->input('month').'-01')
            : company_now();
        $selectedMonth = $month->format('Y-m');

        $attendances = collect();
        $summary = ['days' => 0, 'late' => 0, 'absent' => 0, 'hours' => '0:00'];

        if ($employee) {
            $attendances = $employee->attendances()
                ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->orderByDesc('date')
                ->get();

            $minutes = 0;
            foreach ($attendances as $attendance) {
                if ($attendance->check_in && $attendance->check_out) {
                    $start = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
                    $end = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_out);
                    if ($end->lessThan($start)) {
                        $end->addDay();
                    }
                    $minutes += (int) $start->diffInMinutes($end);
                }
            }

            $summary = [
                'days' => $attendances->whereIn('status', ['ON_TIME', 'LATE'])->count(),
                'late' => $attendances->where('status', 'LATE')->count(),
                'absent' => $attendances->where('status', 'ABSENT')->count(),
                'hours' => sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60),
            ];
        }

        // Status breakdown for the month's doughnut chart
        $statusCounts = collect(Attendance::STATUSES)
            ->mapWithKeys(fn ($s) => [$s => $attendances->where('status', $s)->count()])
            ->filter();

        return view('attendances.mine', compact('attendances', 'employee', 'selectedMonth', 'summary', 'statusCounts'));
    }
}
