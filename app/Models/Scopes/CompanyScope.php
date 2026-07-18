<?php

namespace App\Models\Scopes;

use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Multi-tenant isolation: restricts a model that has a `company_id` column to the
 * current workspace. The current company is:
 *   - a normal user's own company_id;
 *   - a super-admin's "acting" company (chosen in the workspaces screen), or NULL
 *     to see across all companies;
 *   - for the guest kiosk, the company of the selected site.
 * Console jobs (no request/auth, no site) are not scoped.
 */
class CompanyScope implements Scope
{
    /** Explicit company context for console/seeders/workspace creation (no auth) */
    public static ?int $overrideCompanyId = null;

    /** Run a callback as if operating inside the given company (seeders, workspace bootstrap) */
    public static function actingAs(?int $companyId, \Closure $callback)
    {
        $previous = static::$overrideCompanyId;
        static::$overrideCompanyId = $companyId;

        try {
            return $callback();
        } finally {
            static::$overrideCompanyId = $previous;
        }
    }

    public static function currentCompanyId(): ?int
    {
        if (static::$overrideCompanyId !== null) {
            return static::$overrideCompanyId;
        }

        if (auth()->check()) {
            $user = auth()->user();
            if ($user->company_id) {
                return (int) $user->company_id;
            }
            // Super-admin: scoped only into the workspace they explicitly entered
            if ($user->is_super_admin) {
                return session('acting_company_id') ? (int) session('acting_company_id') : null;
            }

            return null;
        }

        // Console (migrations, seeders, commands) uses the explicit override above
        if (app()->runningInConsole()) {
            return null;
        }

        // Guest (kiosk): the company is implied by the selected site
        $siteId = session('kiosk_site') ?: request()->query('site');
        if ($siteId) {
            return Site::withoutGlobalScopes()->whereKey($siteId)->value('company_id');
        }

        return null;
    }

    public function apply(Builder $builder, Model $model): void
    {
        $companyId = static::currentCompanyId();

        if ($companyId) {
            $builder->where($model->getTable().'.company_id', $companyId);
        }
    }
}
