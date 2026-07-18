<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A workspace/tenant. Every business row belongs to a company. A super-admin owns
 * all companies; normal users belong to exactly one.
 *
 * Commercial controls (managed by the super-admin):
 *  - is_active = false  → suspended (e.g. non-payment): its users cannot sign in,
 *    open sessions are cut, and its kiosks stop marking. Data is kept intact.
 *  - soft delete        → workspace retired with a reason; recoverable via restore.
 *  - modules            → the modules the workspace contracted (null = all).
 *  - max_employees/max_sites → plan limits (null = unlimited).
 */
class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'tax_id', 'is_active', 'suspended_reason',
        'modules', 'max_employees', 'max_sites', 'delete_reason',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'modules' => 'array',
        'max_employees' => 'integer',
        'max_sites' => 'integer',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    /** This company's settings row (created on demand) */
    public function settings()
    {
        return $this->hasOne(Setting::class);
    }

    /** Usable = not suspended and not deleted */
    public function isOperational(): bool
    {
        return $this->is_active && $this->deleted_at === null;
    }

    /** Whether the workspace's plan includes a module (null modules = all of them) */
    public function hasModule(string $module): bool
    {
        return $this->modules === null || in_array($module, $this->modules, true);
    }

    /** Plan headroom checks (null limit = unlimited). Counts bypass tenant scope. */
    public function canAddEmployee(): bool
    {
        return $this->max_employees === null
            || Employee::withoutGlobalScopes()->where('company_id', $this->id)->whereNull('deleted_at')->count() < $this->max_employees;
    }

    public function canAddSite(): bool
    {
        return $this->max_sites === null
            || Site::withoutGlobalScopes()->where('company_id', $this->id)->count() < $this->max_sites;
    }
}
