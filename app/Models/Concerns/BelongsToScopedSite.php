<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SiteScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * For models that don't own a `site_id` column but belong to an Employee that
 * does (attendances, vacations, justifications). `inCurrentSite()` filters them
 * to the authenticated user's site; it is a no-op for company/system admins
 * (no site) and for guests/console. Trashed employees are included so the
 * "view deleted" screens keep showing history.
 */
trait BelongsToScopedSite
{
    public function scopeInCurrentSite(Builder $query): Builder
    {
        $siteId = SiteScope::currentSiteId();

        if ($siteId) {
            $query->whereHas('employee', fn ($q) => $q->withTrashed()->where('site_id', $siteId));
        }

        return $query;
    }
}
