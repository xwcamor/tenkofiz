<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Vacation;
use Illuminate\Http\Request;

class VacationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isManager = $user->isManager();
        $canApprove = $user->hasModule('vacations_manage');

        $vacations = Vacation::with(['employee', 'approver'])
            ->when(!$isManager, function ($q) use ($user) {
                $q->whereHas('employee', fn ($w) => $w->where('user_id', $user->id));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $employees = $isManager
            ? Employee::where('is_active', true)->orderBy('last_name')->get()
            : Employee::where('user_id', $user->id)->get();

        return view('vacations.index', compact('vacations', 'isManager', 'canApprove', 'employees'));
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

        return view('vacations.print', [
            'vacation' => $vacation,
            'setting' => app_setting(),
        ]);
    }

    public function changeStatus(Request $request, Vacation $vacation)
    {
        $data = $request->validate(['status' => ['required', 'in:APPROVED,REJECTED,PENDING']]);

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
