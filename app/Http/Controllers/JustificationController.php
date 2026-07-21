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
    use \App\Http\Controllers\Concerns\Sortable;

    public function index(Request $request)
    {
        $user = $request->user();
        $isManager = $user->isManager();
        $canReview = $user->hasModule('justifications_manage');

        // Deleted-records view: restricted to administrators (settings module)
        $showDeleted = $request->boolean('deleted') && $user->hasModule('settings');

        $justifications = Justification::with(['employee.site', 'reviewer'])
            ->inCurrentSite()
            ->when($showDeleted, fn ($q) => $q->onlyTrashed())
            ->when(!$isManager, fn ($q) => $q->whereHas('employee', fn ($w) => $w->where('user_id', $user->id)))
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('employee', fn ($w) => $w->where('site_id', $request->integer('site_id'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        [$sort, $dir] = $this->applySort($justifications, $request, [
            'employee' => fn ($q, $d) => $q->orderBy(Employee::withTrashed()->select('last_name')->whereColumn('employees.id', 'justifications.employee_id'), $d),
            'date' => 'date',
            'status' => 'status',
        ], 'date', 'desc');

        $justifications = $justifications->paginate(25)->withQueryString();

        // Managers pick the employee with an AJAX autocomplete; non-managers
        // only get their own employee in the modal
        $employees = $isManager ? collect() : Employee::where('user_id', $user->id)->get();
        $oldEmployee = old('employee_id') ? Employee::find(old('employee_id')) : null;

        $sites = $isManager ? $this->visibleSites($request) : collect();

        return view('justifications.index', compact('justifications', 'isManager', 'canReview', 'employees', 'oldEmployee', 'showDeleted', 'sites', 'sort', 'dir'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id', Rule::unique('justifications')->where('date', $request->input('date'))->withoutTrashed()],
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

        notify_telegram(__(":employee submitted a justification for :date.\nReason: :reason\n\nReview it at: :url", [
            'employee' => $justification->employee->full_name,
            'date' => $justification->date->format('d/m/Y'),
            'reason' => $justification->reason,
            'url' => route('justifications.index', ['status' => 'PENDING']),
        ]));

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

        $data = ['justification' => $justification, 'setting' => app_setting()];

        if ($request->input('format') === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('justifications.print', $data + ['pdf' => true])
                ->setPaper('a4')
                ->download('justificacion_'.$justification->employee->document_number.'_'.$justification->id.'.pdf');
        }

        return view('justifications.print', $data);
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

    public function destroy(Request $request, Justification $justification)
    {
        $data = $request->validate(['delete_reason' => ['required', 'string', 'max:300']],
            ['delete_reason.required' => __('The deletion reason is required.')]);

        // Soft delete: recoverable by an administrator from "View deleted"
        $justification->update(['delete_reason' => $data['delete_reason']]);
        $justification->delete();

        AuditLog::record('DELETE', 'Justifications',
            __('Justification of :name for :date was deleted. Reason: :reason', [
                'name' => $justification->employee->full_name,
                'date' => $justification->date->format('d/m/Y'),
                'reason' => $data['delete_reason'],
            ]),
            $justification->toArray());

        return back()->with('ok', __('Justification deleted. An administrator can restore it from "View deleted".'));
    }

    /** Brings a soft-deleted justification back (administrators only) */
    public function restore(Request $request, Justification $justification)
    {
        abort_unless($request->user()->hasModule('settings'), 403);

        $justification->restore();
        $justification->update(['delete_reason' => null]);

        AuditLog::record('UPDATE', 'Justifications',
            __('Justification of :name for :date was restored', [
                'name' => $justification->employee->full_name,
                'date' => $justification->date->format('d/m/Y'),
            ]));

        return back()->with('ok', __('Justification restored.'));
    }
}
