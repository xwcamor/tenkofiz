<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a model that has a `site_id` column to the site of the currently
 * authenticated user. A user with no site (site_id = NULL) is a company/system
 * administrator and sees every site. Guests (kiosk, console jobs) are never
 * scoped, so kiosk marking and scheduled tasks keep working across all sites.
 */
class SiteScope implements Scope
{
    public static function currentSiteId(): ?int
    {
        return auth()->check() ? auth()->user()->site_id : null;
    }

    public function apply(Builder $builder, Model $model): void
    {
        $siteId = static::currentSiteId();

        if ($siteId) {
            $builder->where($model->getTable().'.site_id', $siteId);
        }
    }
}
