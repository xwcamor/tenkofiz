<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a model as tenant-owned: it is globally scoped to the current company and
 * new rows inherit the current company automatically. See CompanyScope.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model) {
            if (!empty($model->company_id)) {
                return;
            }
            // Prefer the request/console company; otherwise fall back to the first
            // company (single-tenant default) so a row is never left orphaned.
            $companyId = CompanyScope::currentCompanyId();
            if ($companyId === null && Schema::hasTable('companies')) {
                $companyId = Company::query()->min('id');
            }
            $model->company_id = $companyId;
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
