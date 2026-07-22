<?php

use App\Models\Company;
use App\Models\Setting;
use App\Models\Site;
use Illuminate\Database\Migrations\Migration;

/**
 * Every employee requires a site, but older workspaces were created without one.
 * Give any company that has no site a default "General" site (using its own
 * timezone) so it can register employees right away.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Company::withoutGlobalScopes()->withTrashed()->get() as $company) {
            $hasSite = Site::withoutGlobalScopes()->where('company_id', $company->id)->exists();
            if ($hasSite) {
                continue;
            }

            $timezone = Setting::withoutGlobalScopes()->where('company_id', $company->id)->value('timezone')
                ?: config('app.display_timezone', 'America/Lima');

            Site::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => 'General',
                'timezone' => $timezone,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: keep the sites (employees may already reference them).
    }
};
