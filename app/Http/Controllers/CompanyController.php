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
    public function index()
    {
        $companies = Company::orderBy('name')->get()->map(function (Company $company) {
            $company->users_count = User::withoutGlobalScopes()->where('company_id', $company->id)->count();
            $company->employees_count = Employee::withoutGlobalScopes()->where('company_id', $company->id)->count();
            $company->sites_count = Site::withoutGlobalScopes()->where('company_id', $company->id)->count();

            return $company;
        });

        return view('admin.companies.index', [
            'companies' => $companies,
            'countries' => HolidayTemplate::COUNTRIES,
            'timezones' => \DateTimeZone::listIdentifiers(),
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

        $adminProfile = Profile::firstOrCreate(['name' => 'Administrator'], [
            'description' => 'Full access to the system',
            'permissions' => array_keys(Profile::MODULES),
        ]);

        CompanyScope::actingAs($company->id, function () use ($company, $data, $adminProfile) {
            Setting::firstOrCreate(['company_id' => $company->id], [
                'company_name' => $data['name'],
                'tax_id' => $data['tax_id'] ?? null,
                'timezone' => $data['timezone'],
                'country' => $data['country'],
            ]);

            // Seed the country's recurring holiday templates for this workspace
            foreach (array_keys(HolidayTemplate::COUNTRIES) as $country) {
                foreach (HolidayTemplate::presets($country) as [$month, $day, $offset, $name]) {
                    HolidayTemplate::firstOrCreate(['country' => $country, 'month' => $month, 'day' => $day, 'easter_offset' => $offset, 'name' => $name]);
                }
            }

            User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'profile_id' => $adminProfile->id,
                'company_id' => $company->id,
            ]);
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
