<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        // Server-side pagination + search: this list must stay fast with thousands of employees
        $search = trim((string) $request->input('q'));

        // Deleted-records view: restricted to administrators (settings module)
        $showDeleted = $request->boolean('deleted') && $request->user()->hasModule('settings');

        $employees = Employee::with(['schedule', 'user', 'area', 'position'])
            ->when($showDeleted, fn ($q) => $q->onlyTrashed())
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q
                    ->where('document_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like));
            })
            ->when($request->filled('area_id'), fn ($q) => $q->where('area_id', $request->integer('area_id')))
            ->when($request->input('face') === 'enrolled', fn ($q) => $q->whereNotNull('face_descriptor'))
            ->when($request->input('face') === 'pending', fn ($q) => $q->whereNull('face_descriptor'))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'showDeleted' => $showDeleted,
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'profiles' => Profile::where('is_active', true)->orderBy('name')->get(),
            'availableUsers' => User::whereDoesntHave('employee')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * JSON autocomplete used by every employee selector (Select2). Results are
     * searched and paginated in the database, so the selectors stay instant
     * no matter how many employees exist.
     */
    public function search(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        $search = trim((string) $request->input('q'));
        $year = (int) company_now()->year;

        $employees = Employee::where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q
                    ->where('document_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like));
            })
            // Approved days this year in the same query (avoids one query per result)
            ->withSum(['vacations as approved_vacation_days' => fn ($q) => $q
                ->where('status', 'APPROVED')
                ->whereYear('start_date', $year)], 'days')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20);

        return response()->json([
            'results' => collect($employees->items())->map(fn ($employee) => [
                'id' => $employee->id,
                'text' => $employee->full_name.' — '.$employee->document_number,
                'balance' => max(0, ($employee->vacation_days_per_year ?? 30) - (int) $employee->approved_vacation_days),
            ]),
            'pagination' => ['more' => $employees->hasMorePages()],
        ]);
    }

    public function create()
    {
        return view('employees.form', [
            'employee' => new Employee(),
            'schedules' => Schedule::where('is_active', true)->get(),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Employee::create($this->validated($request));
        return redirect()->route('employees.index')->with('ok', __('Employee registered. You can now enroll their face.'));
    }

    public function edit(Employee $employee)
    {
        return view('employees.form', [
            'employee' => $employee,
            'schedules' => Schedule::where('is_active', true)->get(),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request, $employee));
        return redirect()->route('employees.index')->with('ok', __('Employee updated.'));
    }

    /** Creates a linked user with the chosen profile (initial password: their document number) */
    public function createUser(Request $request, Employee $employee)
    {
        if ($employee->user_id) {
            return response()->json(['ok' => false, 'message' => __('This employee already has a linked user.')], 422);
        }

        $data = $request->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')->withoutTrashed()],
            'profile_id' => ['nullable', Rule::exists('profiles', 'id')->where('is_active', true)],
        ], [
            'email.unique' => __('That email is already registered to another user.'),
        ]);

        // Default profile: Employee (self-service). A supervisor picks their real profile here.
        $profileId = $data['profile_id'] ?? Profile::firstOrCreate(
            ['name' => 'Employee'],
            ['description' => __('Views their attendance and requests vacations'), 'permissions' => []]
        )->id;

        $user = User::create([
            'name' => trim($employee->first_name.' '.$employee->last_name),
            'email' => $data['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($employee->document_number),
            'profile_id' => $profileId,
            'must_change_password' => true,
        ]);

        safe_mail(
            $user->email,
            __('Your access credentials for the Attendance System'),
            __("Hello :name,\n\nYour access account was created:\nEmail: :email\nInitial password: :password\n\nSign in here: :url\n\nYou will be asked to change it on first sign-in.\n\nRegards.", [
                'name' => $employee->first_name,
                'email' => $user->email,
                'password' => $employee->document_number,
                'url' => route('login'),
            ])
        );

        $employee->update(['user_id' => $user->id]);

        AuditLog::record('CREATE', 'Users',
            __('User :email was created and linked to employee :employee', [
                'email' => $user->email,
                'employee' => $employee->full_name,
            ]));

        return response()->json([
            'ok' => true,
            'email' => $user->email,
            'password' => $employee->document_number,
        ]);
    }

    /** Links an existing (unlinked) user to this employee — e.g. a supervisor account created first */
    public function linkUser(Request $request, Employee $employee)
    {
        if ($employee->user_id) {
            return back()->with('error', __('This employee already has a linked user.'));
        }

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', Rule::unique('employees', 'user_id')->withoutTrashed()],
        ], [
            'user_id.unique' => __('That user is already linked to another employee.'),
        ]);

        $employee->update(['user_id' => $data['user_id']]);

        AuditLog::record('UPDATE', 'Employees',
            __('User :email was linked to employee :employee', [
                'email' => User::find($data['user_id'])->email,
                'employee' => $employee->full_name,
            ]));

        return back()->with('ok', __('User linked to the employee.'));
    }

    /** Unlinks the user account (the account survives; the person just stops seeing their own marks) */
    public function unlinkUser(Employee $employee)
    {
        if (!$employee->user_id) {
            return back()->with('error', __('This employee has no linked user.'));
        }

        $email = $employee->user?->email;
        $employee->update(['user_id' => null]);

        AuditLog::record('UPDATE', 'Employees',
            __('User :email was unlinked from employee :employee', [
                'email' => $email,
                'employee' => $employee->full_name,
            ]));

        return back()->with('ok', __('User unlinked from the employee.'));
    }

    public function destroy(Request $request, Employee $employee)
    {
        $data = $request->validate(['delete_reason' => ['required', 'string', 'max:300']],
            ['delete_reason.required' => __('The deletion reason is required.')]);

        // Soft delete: the record (attendance history included) stays recoverable
        $employee->update(['delete_reason' => $data['delete_reason']]);
        $employee->delete();

        AuditLog::record('DELETE', 'Employees',
            __('Employee :name (document :document) was deleted. Reason: :reason', [
                'name' => $employee->full_name,
                'document' => $employee->document_number,
                'reason' => $data['delete_reason'],
            ]),
            $employee->toArray());

        return back()->with('ok', __('Employee deleted. An administrator can restore it from "View deleted".'));
    }

    /** Brings a soft-deleted employee back (administrators only) */
    public function restore(Request $request, Employee $employee)
    {
        abort_unless($request->user()->hasModule('settings'), 403);

        $employee->restore();
        $employee->update(['delete_reason' => null]);

        AuditLog::record('UPDATE', 'Employees',
            __('Employee :name (document :document) was restored', [
                'name' => $employee->full_name,
                'document' => $employee->document_number,
            ]));

        return back()->with('ok', __('Employee restored.'));
    }

    /** Face capture screen using the camera */
    public function enroll(Employee $employee)
    {
        return view('employees.enroll', compact('employee'));
    }

    /**
     * Receives SEVERAL face descriptors (3 samples of 128 values each) for accuracy.
     * Requires the biometric data consent to be accepted before persisting anything.
     */
    public function storeDescriptor(Request $request, Employee $employee)
    {
        // Data protection: biometric data may not be stored without recorded consent
        if (!$employee->hasBiometricConsent() && !$request->boolean('consent')) {
            return response()->json([
                'ok' => false,
                'message' => __('The biometric data consent must be accepted before enrolling.'),
            ], 422);
        }

        $data = $request->validate([
            'descriptors' => ['required', 'array', 'min:1', 'max:5'],
            'descriptors.*' => ['required', 'array', 'size:128'],
            'descriptors.*.*' => ['numeric'],
        ]);

        $employee->update([
            'face_descriptor' => json_encode($data['descriptors']),
            'biometric_consent_at' => $employee->biometric_consent_at ?? now(),
        ]);
        $employee->refresh();

        // Real confirmation against the database (not just an optimistic response)
        if (empty($employee->face_descriptor)) {
            return response()->json(['ok' => false, 'message' => __('The descriptor could not be persisted to the database.')], 500);
        }

        AuditLog::record('UPDATE', 'Employees',
            __('Face enrolled for :name (:count samples, consent recorded)', [
                'name' => $employee->full_name,
                'count' => count($data['descriptors']),
            ]));

        return response()->json([
            'ok' => true,
            'message' => __('Face enrolled with :count samples (verified in the database).', ['count' => count($data['descriptors'])]),
        ]);
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        // Normalize before validating: uppercase, no spaces (passports/CE may mix letters and digits)
        $request->merge(['document_number' => strtoupper(trim((string) $request->input('document_number')))]);

        // Format depends on the document type
        $documentRule = match ($request->input('document_type', 'DNI')) {
            'CE' => ['required', 'regex:/^[0-9A-Z]{9,12}$/'],
            'PASSPORT' => ['required', 'regex:/^[0-9A-Z]{6,12}$/'],
            default => ['required', 'digits:8'], // DNI (Peru) is exactly 8 digits
        };

        return $request->validate([
            'document_type' => ['required', Rule::in(array_keys(Employee::DOCUMENT_TYPES))],
            'document_number' => [...$documentRule, Rule::unique('employees')->ignore($employee)->withoutTrashed()],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'hire_date' => ['nullable', 'date'],
            'vacation_days_per_year' => ['required', 'integer', 'min:0', 'max:60'],
            'schedule_id' => ['required', 'exists:schedules,id'],
        ], [
            'document_number.unique' => __('That document number is already registered to another employee.'),
            'schedule_id.required' => __('You must assign a schedule to the employee (needed to compute tardiness).'),
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
