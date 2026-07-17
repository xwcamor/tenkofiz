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
        $employees = Employee::with(['schedule', 'user', 'area', 'position'])
            ->orderBy('last_name')
            ->get();

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        return view('employees.form', [
            'employee' => new Employee(),
            'schedules' => Schedule::where('is_active', true)->get(),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('name')->get(),
            'users' => User::whereDoesntHave('employee')->orderBy('name')->get(),
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
            'users' => User::whereDoesntHave('employee')->orWhere('id', $employee->user_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request, $employee));
        return redirect()->route('employees.index')->with('ok', __('Employee updated.'));
    }

    /** Creates a linked user with the Employee profile (initial password: their document number) */
    public function createUser(Request $request, Employee $employee)
    {
        if ($employee->user_id) {
            return response()->json(['ok' => false, 'message' => __('This employee already has a linked user.')], 422);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
        ], [
            'email.unique' => __('That email is already registered to another user.'),
        ]);

        $employeeProfile = Profile::firstOrCreate(
            ['name' => 'Employee'],
            ['description' => __('Views their attendance and requests vacations'), 'permissions' => []]
        );

        $user = User::create([
            'name' => trim($employee->first_name.' '.$employee->last_name),
            'email' => $data['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($employee->document_number),
            'profile_id' => $employeeProfile->id,
            'must_change_password' => true,
        ]);

        safe_mail(
            $user->email,
            __('Your access credentials for the Attendance System'),
            __("Hello :name,\n\nYour access account was created:\nEmail: :email\nInitial password: :password\n\nYou will be asked to change it on first sign-in.\n\nRegards.", [
                'name' => $employee->first_name,
                'email' => $user->email,
                'password' => $employee->document_number,
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

    public function destroy(Employee $employee)
    {
        AuditLog::record('DELETE', 'Employees',
            __('Employee :name (document :document) was deleted', [
                'name' => $employee->full_name,
                'document' => $employee->document_number,
            ]),
            $employee->toArray());
        $employee->delete();
        return back()->with('ok', __('Employee deleted.'));
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
        return $request->validate([
            'document_number' => ['required', 'digits_between:8,12', Rule::unique('employees')->ignore($employee)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'hire_date' => ['nullable', 'date'],
            'schedule_id' => ['required', 'exists:schedules,id'],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees')->ignore($employee)],
        ], [
            'document_number.unique' => __('That document number is already registered to another employee.'),
            'schedule_id.required' => __('You must assign a schedule to the employee (needed to compute tardiness).'),
            'user_id.unique' => __('That user is already linked to another employee.'),
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
