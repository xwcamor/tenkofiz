<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\SiteScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * For models that don't own company_id/site_id columns but belong to an Employee
 * that does (attendances, vacations, justifications). `inCurrentSite()` filters
 * them to the current tenant: the company (workspace) and — when the user is
 * bound to one — the site. It is a no-op for a super-admin browsing across all
 * companies and for guests/console. Trashed employees are included so the
 * "view deleted" screens keep showing history.
 *
 * The filtering leans on Employee's own global scopes (CompanyScope + SiteScope)
 * applied inside `whereHas('employee')`, so it always matches the exact tenant
 * rules used everywhere else.
 */
trait BelongsToScopedSite
{
    public function scopeInCurrentSite(Builder $query): Builder
    {
        $companyId = CompanyScope::currentCompanyId();
        $siteId = SiteScope::currentSiteId();

        if ($companyId !== null || $siteId !== null) {
            $query->whereHas('employee', fn ($q) => $q->withTrashed());
        }

        return $query;
    }
}
