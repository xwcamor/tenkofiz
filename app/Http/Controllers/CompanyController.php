<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\HolidayTemplate;
use App\Models\Profile;
use App\Models\Scopes\CompanyScope;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Super-admin workspace management: create companies, enter one to administer it,
 * and leave it. Everything here runs across all companies (the super-admin has no
 * company of their own), so counts bypass the tenant scope explicitly.
 */
class CompanyController extends Controller
{
    use \App\Http\Controllers\Concerns\Sortable;

    public function index(Request $request)
    {
        $companies = Company::withTrashed()->orderBy('name')->get()->map(function (Company $company) {
            $company->users_count = User::withoutGlobalScopes()->where('company_id', $company->id)->count();
            $company->employees_count = Employee::withoutGlobalScopes()->where('company_id', $company->id)->count();
            $company->sites_count = Site::withoutGlobalScopes()->where('company_id', $company->id)->count();
            // Facial-recognition calibration is core: edited only from this console
            $setting = Setting::withoutGlobalScopes()->where('company_id', $company->id)->first();
            $company->recognition = [
                'threshold' => (float) ($setting->kiosk_face_threshold ?? 0.50),
                'seconds' => (int) ($setting->kiosk_verify_seconds ?? 15),
                'match_seconds' => (int) ($setting->kiosk_match_seconds ?? 20),
            ];

            return $company;
        });

        [$companies, $sort, $dir] = $this->sortCollection($companies, $request, [
            'name' => 'name', 'users' => 'users_count', 'employees' => 'employees_count', 'sites' => 'sites_count',
        ], 'name');

        return view('admin.companies.index', [
            'companies' => $companies,
            'sort' => $sort,
            'dir' => $dir,
            'countries' => HolidayTemplate::COUNTRIES,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'modules' => Profile::MODULES,
            'actingCompanyId' => session('acting_company_id'),
        ]);
    }

    /** Create a new workspace with its settings, holiday templates and first admin */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('companies', 'name')],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'timezone' => ['required', 'timezone:all'],
            'country' => ['required', Rule::in(array_keys(HolidayTemplate::COUNTRIES))],
            'locale' => ['required', Rule::in(\App\Http\Middleware\SetLocale::SUPPORTED)],
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', Rule::unique('users', 'email')->withoutTrashed()],
            'admin_password' => ['required', 'string', 'min:6'],
        ], [
            'admin_email.unique' => __('That email is already registered to another user.'),
        ]);

        $company = Company::create([
            'name' => $data['name'],
            'tax_id' => $data['tax_id'] ?? null,
            'is_active' => true,
        ]);

        $adminProfile = CompanyScope::actingAs($company->id, function () use ($company, $data) {
            // Every workspace is born with its OWN three base roles (per company,
            // protected via is_system) so it can manage its own data from day one.
            $admin = Profile::firstOrCreate(['name' => 'Administrator', 'company_id' => $company->id], [
                'description' => 'Full access to the system',
                'permissions' => array_keys(Profile::MODULES),
                'is_system' => true,
            ]);
            Profile::firstOrCreate(['name' => 'Supervisor', 'company_id' => $company->id], [
                'description' => 'Manages attendance and approves requests',
                'permissions' => ['employees', 'attendances', 'reports', 'vacations_manage', 'justifications_manage', 'kiosk'],
                'is_system' => true,
            ]);
            Profile::firstOrCreate(['name' => 'Employee', 'company_id' => $company->id], [
                'description' => 'Views their attendance and requests vacations',
                'permissions' => [],
                'is_system' => true,
            ]);

            Setting::firstOrCreate(['company_id' => $company->id], [
                'company_name' => $data['name'],
                'tax_id' => $data['tax_id'] ?? null,
                'timezone' => $data['timezone'],
                'country' => $data['country'],
                'locale' => $data['locale'], // workspace default language
            ]);

            // Seed the country's recurring holiday templates for this workspace
            foreach (array_keys(HolidayTemplate::COUNTRIES) as $country) {
                foreach (HolidayTemplate::presets($country) as [$month, $day, $offset, $name]) {
                    HolidayTemplate::firstOrCreate(['country' => $country, 'month' => $month, 'day' => $day, 'easter_offset' => $offset, 'name' => $name]);
                }
            }

            // Starter kit: a workspace must never be born unusable. A schedule is
            // REQUIRED to register employees, so every new workspace gets a base
            // one (Mon-Sat 08:00-17:00) that its admin can edit or replace.
            $schedule = \App\Models\Schedule::create(['name' => __('General schedule'), 'tolerance_minutes' => 5]);
            foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
                $schedule->days()->create(['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '17:00:00']);
            }

            // Every employee needs a site, so a workspace must be born with one.
            \App\Models\Site::create([
                'name' => __('General'),
                'timezone' => $data['timezone'] ?? config('app.display_timezone', 'America/Lima'),
                'is_active' => true,
            ]);

            User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'profile_id' => $admin->id,
                'company_id' => $company->id,
            ]);

            return $admin;
        });

        safe_mail(
            $data['admin_email'],
            __('Your workspace administrator account'),
            __("A workspace \":company\" was created for you.\n\nEmail: :email\nPassword: :password\n\nSign in here: :url", [
                'company' => $data['name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'url' => route('login'),
            ])
        );

        return back()->with('ok', __('Workspace ":name" created with its administrator.', ['name' => $data['name']]));
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('companies', 'name')->ignore($company)],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $company->update([
            'name' => $data['name'],
            'tax_id' => $data['tax_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('ok', __('Workspace updated.'));
    }

    /**
     * Plan editor: which modules the workspace contracted and its size limits.
     * An empty module selection is stored as NULL = "all modules" (no restriction).
     */
    public function updatePlan(Request $request, Company $company)
    {
        $data = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => [Rule::in(array_keys(Profile::MODULES))],
            'all_modules' => ['boolean'],
            'max_employees' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_sites' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $company->update([
            'modules' => $request->boolean('all_modules') ? null : array_values($data['modules'] ?? []),
            'max_employees' => $data['max_employees'] ?? null,
            'max_sites' => $data['max_sites'] ?? null,
        ]);

        \App\Models\AuditLog::record('UPDATE', 'Workspaces',
            __('Plan updated for workspace :name', ['name' => $company->name]), $company->only('modules', 'max_employees', 'max_sites'));

        return back()->with('ok', __('Plan for ":name" updated.', ['name' => $company->name]));
    }

    /**
     * Core facial-recognition calibration for a workspace's kiosks. These are
     * engine screws (match strictness, verification window): a wrong value can
     * let anyone impersonate anyone, so ONLY the super-admin touches them —
     * workspace admins never even see these fields.
     */
    public function updateRecognition(Request $request, Company $company)
    {
        $data = $request->validate([
            'kiosk_face_threshold' => ['required', 'numeric', 'min:0.35', 'max:0.65'],
            'kiosk_verify_seconds' => ['required', 'integer', 'min:5', 'max:60'],
            'kiosk_match_seconds' => ['required', 'integer', 'min:5', 'max:120'],
        ]);

        Setting::withoutGlobalScopes()->firstOrCreate(['company_id' => $company->id])->update($data);

        \App\Models\AuditLog::record('UPDATE', 'Workspaces',
            __('Recognition calibration updated for workspace :name', ['name' => $company->name]), $data);

        return back()->with('ok', __('Recognition calibration for ":name" updated.', ['name' => $company->name]));
    }

    /** Suspend (e.g. non-payment): users are cut off immediately; data stays intact */
    public function suspend(Request $request, Company $company)
    {
        $data = $request->validate(['suspended_reason' => ['required', 'string', 'max:200']],
            ['suspended_reason.required' => __('Indicate the suspension reason (e.g. pending payment).')]);

        $company->update(['is_active' => false, 'suspended_reason' => $data['suspended_reason']]);

        \App\Models\AuditLog::record('UPDATE', 'Workspaces',
            __('Workspace :name was SUSPENDED. Reason: :reason', ['name' => $company->name, 'reason' => $data['suspended_reason']]));

        return back()->with('ok', __('Workspace ":name" suspended: its users can no longer sign in and its kiosks stopped marking.', ['name' => $company->name]));
    }

    /** Lift the suspension: everything works again exactly as before */
    public function reactivate(Company $company)
    {
        $company->update(['is_active' => true, 'suspended_reason' => null]);

        \App\Models\AuditLog::record('UPDATE', 'Workspaces', __('Workspace :name was reactivated', ['name' => $company->name]));

        return back()->with('ok', __('Workspace ":name" reactivated.', ['name' => $company->name]));
    }

    /** Retire a workspace (soft delete with reason). Its data stays recoverable. */
    public function destroy(Request $request, Company $company)
    {
        $data = $request->validate(['delete_reason' => ['required', 'string', 'max:300']],
            ['delete_reason.required' => __('The deletion reason is required.')]);

        // If the super was administering this workspace, leave it first
        if ((int) session('acting_company_id') === $company->id) {
            $request->session()->forget('acting_company_id');
        }

        $company->update(['delete_reason' => $data['delete_reason']]);
        $company->delete();

        \App\Models\AuditLog::record('DELETE', 'Workspaces',
            __('Workspace :name was deleted. Reason: :reason', ['name' => $company->name, 'reason' => $data['delete_reason']]),
            $company->toArray());

        return back()->with('ok', __('Workspace ":name" deleted. Its users are blocked; you can restore it if needed.', ['name' => $company->name]));
    }

    /** Bring a deleted workspace back (everything as it was) */
    public function restore(Company $company)
    {
        $company->restore();
        $company->update(['delete_reason' => null]);

        \App\Models\AuditLog::record('UPDATE', 'Workspaces', __('Workspace :name was restored', ['name' => $company->name]));

        return back()->with('ok', __('Workspace ":name" restored.', ['name' => $company->name]));
    }

    /** Super-admin enters a workspace: subsequent screens are scoped to it */
    public function enter(Request $request, Company $company)
    {
        $request->session()->put('acting_company_id', $company->id);

        return redirect()->route('dashboard')->with('ok', __('You are now administering ":name".', ['name' => $company->name]));
    }

    /** Leave the current workspace back to the super-admin console */
    public function leave(Request $request)
    {
        $request->session()->forget('acting_company_id');

        return redirect()->route('admin.companies.index')->with('ok', __('Left the workspace.'));
    }
}
