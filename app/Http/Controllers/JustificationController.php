<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Justification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JustificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isManager = $user->isManager();
        $canReview = $user->hasModule('justifications_manage');

        $justifications = Justification::with(['employee', 'reviewer'])
            ->when(!$isManager, fn ($q) => $q->whereHas('employee', fn ($w) => $w->where('user_id', $user->id)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('date')
            ->paginate(25)
            ->withQueryString();

        $employees = $isManager
            ? Employee::where('is_active', true)->orderBy('last_name')->get()
            : Employee::where('user_id', $user->id)->get();

        return view('justifications.index', compact('justifications', 'isManager', 'canReview', 'employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id', Rule::unique('justifications')->where('date', $request->input('date'))],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:300'],
            'document' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
        ], [
            'employee_id.unique' => __('A justification already exists for that employee on that date.'),
            'document.mimes' => __('The document must be a PDF or an image (png/jpg).'),
        ]);

        $user = $request->user();
        if (!$user->isManager()) {
            $own = Employee::where('user_id', $user->id)->value('id');
            abort_if((int) $data['employee_id'] !== (int) $own, 403);
        }

        if ($request->hasFile('document')) {
            $dir = public_path('uploads/justifications');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = uniqid('justification_').'.'.$request->file('document')->getClientOriginalExtension();
            $request->file('document')->move($dir, $name);
            $data['document'] = 'uploads/justifications/'.$name;
        }

        $justification = Justification::create($data);
        $justification->load('employee');

        // Let reviewers know there is a justification waiting for them
        notify_module_users(
            'justifications_manage',
            __('Justification pending review'),
            __(":employee submitted a justification for :date.\nReason: :reason\n\nReview it at: :url", [
                'employee' => $justification->employee->full_name,
                'date' => $justification->date->format('d/m/Y'),
                'reason' => $justification->reason,
                'url' => route('justifications.index', ['status' => 'PENDING']),
            ])
        );

        return redirect()->route('justifications.index')->with('ok', __('Justification submitted. It is pending review.'));
    }

    /** Printable business form (PDF via browser): managers see any, employees only their own */
    public function print(Request $request, Justification $justification)
    {
        $user = $request->user();
        if (!$user->isManager() && $justification->employee->user_id !== $user->id) {
            abort(403);
        }

        $justification->load(['employee.area', 'employee.position', 'reviewer']);

        return view('justifications.print', [
            'justification' => $justification,
            'setting' => app_setting(),
        ]);
    }

    /** A reviewer accepts or rejects; accepting marks the day as EXCUSED in attendances */
    public function changeStatus(Request $request, Justification $justification)
    {
        $data = $request->validate(['status' => ['required', 'in:ACCEPTED,REJECTED,PENDING']]);

        $justification->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
        ]);

        if ($data['status'] === 'ACCEPTED') {
            Attendance::updateOrCreate(
                ['employee_id' => $justification->employee_id, 'date' => $justification->date->toDateString()],
                ['status' => 'EXCUSED', 'method' => 'MANUAL', 'note' => __('Justification accepted: :reason', ['reason' => $justification->reason])]
            );
        }

        AuditLog::record('UPDATE', 'Justifications',
            __('Justification of :name (:date) marked as :status', [
                'name' => $justification->employee->full_name,
                'date' => $justification->date->format('d/m/Y'),
                'status' => __($data['status']),
            ]),
            $justification->toArray());

        $justification->load('employee.user');
        safe_mail(
            $justification->employee->user?->email,
            __('Your justification was :status', ['status' => __($data['status'])]),
            __("Hello :name,\n\nYour justification for :date (:reason) was :status.\n\nRegards.", [
                'name' => $justification->employee->first_name,
                'date' => $justification->date->format('d/m/Y'),
                'reason' => $justification->reason,
                'status' => __($data['status']),
            ])
        );

        return back()->with('ok', __('Justification :status.', ['status' => __($data['status'])]));
    }

    public function destroy(Justification $justification)
    {
        AuditLog::record('DELETE', 'Justifications',
            __('Justification of :name for :date was deleted', [
                'name' => $justification->employee->full_name,
                'date' => $justification->date->format('d/m/Y'),
            ]),
            $justification->toArray());

        $justification->delete();
        return back()->with('ok', __('Justification deleted.'));
    }
}
