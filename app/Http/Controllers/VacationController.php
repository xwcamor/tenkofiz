<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Vacation;
use Illuminate\Http\Request;

class VacationController extends Controller
{
    use \App\Http\Controllers\Concerns\Sortable;

    public function index(Request $request)
    {
        $user = $request->user();
        $isManager = $user->isManager();
        $canApprove = $user->hasModule('vacations_manage');

        $vacations = Vacation::with(['employee.site', 'approver'])
            ->inCurrentSite()
            ->when(!$isManager, function ($q) use ($user) {
                $q->whereHas('employee', fn ($w) => $w->where('user_id', $user->id));
            })
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('employee', fn ($w) => $w->where('site_id', $request->integer('site_id'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        [$sort, $dir] = $this->applySort($vacations, $request, [
            'employee' => fn ($q, $d) => $q->orderBy(Employee::withTrashed()->select('last_name')->whereColumn('employees.id', 'vacations.employee_id'), $d),
            'start' => 'start_date',
            'end' => 'end_date',
            'days' => 'days',
            'status' => 'status',
        ], 'start', 'desc');

        $vacations = $vacations->paginate(25)->withQueryString();

        // Managers pick the employee with an AJAX autocomplete (which returns the
        // balance); non-managers only get their own employee in the modal
        $employees = $isManager
            ? collect()
            : Employee::where('user_id', $user->id)->get();

        // Remaining days this year, shown in the request modal and next to the listed rows
        $balances = $employees->mapWithKeys(fn ($e) => [$e->id => $e->remainingVacationDays()]);
        foreach ($vacations as $vacation) {
            if (!$balances->has($vacation->employee_id)) {
                $balances[$vacation->employee_id] = $vacation->employee->remainingVacationDays();
            }
        }

        $oldEmployee = old('employee_id') ? Employee::find(old('employee_id')) : null;

        $sites = $isManager ? $this->visibleSites($request) : collect();

        return view('vacations.index', compact('vacations', 'isManager', 'canApprove', 'employees', 'balances', 'oldEmployee', 'sites', 'sort', 'dir'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:300'],
        ]);

        $user = $request->user();
        if (!$user->isManager()) {
            $own = Employee::where('user_id', $user->id)->value('id');
            abort_if((int) $data['employee_id'] !== (int) $own, 403);
        }

        $data['days'] = \Carbon\Carbon::parse($data['start_date'])
            ->diffInDays(\Carbon\Carbon::parse($data['end_date'])) + 1;

        // Annual balance guard: the request cannot exceed the remaining days
        $employee = Employee::findOrFail($data['employee_id']);
        $remaining = $employee->remainingVacationDays((int) \Carbon\Carbon::parse($data['start_date'])->year);
        if ($data['days'] > $remaining) {
            return back()->withInput()->withErrors([
                'end_date' => __('Insufficient vacation balance: :remaining day(s) available this year.', ['remaining' => $remaining]),
            ]);
        }

        $vacation = Vacation::create($data);
        $vacation->load('employee');

        // Let approvers know there is a request waiting for them
        notify_module_users(
            'vacations_manage',
            __('Vacation request pending approval'),
            __(":employee requested vacations from :from to :to (:days days).\nReason: :reason\n\nReview it at: :url", [
                'employee' => $vacation->employee->full_name,
                'from' => $vacation->start_date->format('d/m/Y'),
                'to' => $vacation->end_date->format('d/m/Y'),
                'days' => $vacation->days,
                'reason' => $vacation->reason,
                'url' => route('vacations.index', ['status' => 'PENDING']),
            ])
        );

        notify_telegram(__(":employee requested vacations from :from to :to (:days days).\nReason: :reason\n\nReview it at: :url", [
            'employee' => $vacation->employee->full_name,
            'from' => $vacation->start_date->format('d/m/Y'),
            'to' => $vacation->end_date->format('d/m/Y'),
            'days' => $vacation->days,
            'reason' => $vacation->reason,
            'url' => route('vacations.index', ['status' => 'PENDING']),
        ]));

        return redirect()->route('vacations.index')->with('ok', __('Vacation request submitted.'));
    }

    /** Printable business form (PDF via browser): managers see any, employees only their own */
    public function print(Request $request, Vacation $vacation)
    {
        $user = $request->user();
        if (!$user->isManager() && $vacation->employee->user_id !== $user->id) {
            abort(403);
        }

        $vacation->load(['employee.area', 'employee.position', 'approver']);

        $data = ['vacation' => $vacation, 'setting' => app_setting()];

        if ($request->input('format') === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('vacations.print', $data + ['pdf' => true])
                ->setPaper('a4')
                ->download('vacaciones_'.$vacation->employee->document_number.'_'.$vacation->id.'.pdf');
        }

        return view('vacations.print', $data);
    }

    public function changeStatus(Request $request, Vacation $vacation)
    {
        $data = $request->validate(['status' => ['required', 'in:APPROVED,REJECTED,PENDING']]);

        // Balance re-check at approval time (requests may compete for the same days)
        if ($data['status'] === 'APPROVED' && $vacation->status !== 'APPROVED') {
            $remaining = $vacation->employee->remainingVacationDays((int) $vacation->start_date->year);
            if ($vacation->days > $remaining) {
                return back()->with('error', __('Cannot approve: the employee only has :remaining day(s) left this year.', ['remaining' => $remaining]));
            }
        }

        $vacation->update([
            'status' => $data['status'],
            'approved_by' => $request->user()->id,
        ]);

        $vacation->load('employee.user');
        safe_mail(
            $vacation->employee->user?->email,
            __('Your vacation request was :status', ['status' => __($data['status'])]),
            __("Hello :name,\n\nYour vacation request from :from to :to (:days days) was :status.\n\nRegards.", [
                'name' => $vacation->employee->first_name,
                'from' => $vacation->start_date->format('d/m/Y'),
                'to' => $vacation->end_date->format('d/m/Y'),
                'days' => $vacation->days,
                'status' => __($data['status']),
            ])
        );

        return back()->with('ok', __('Request :status.', ['status' => __($data['status'])]));
    }
}
