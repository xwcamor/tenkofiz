<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use BelongsToCompany, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'profile_id', 'company_id', 'site_id', 'is_super_admin', 'is_active',
        'must_change_password', 'timezone', 'locale', 'photo', 'delete_reason',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    /** The super-admin owns every workspace and bypasses per-module permissions */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    /** The site this user is bound to (NULL = company-wide access) */
    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /** True when this account is limited to a single site */
    public function isSiteBound(): bool
    {
        return $this->site_id !== null;
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /** Whether the user's profile grants access to the given module */
    public function hasModule(string $module): bool
    {
        if ($this->isSuperAdmin()) {
            return true; // super-admin sees every module of the workspace they entered
        }

        return $this->profile !== null
            && $this->profile->is_active
            && in_array($module, $this->profile->permissions ?? [], true);
    }

    public function hasAnyModule(string ...$modules): bool
    {
        foreach ($modules as $module) {
            if ($this->hasModule($module)) {
                return true;
            }
        }

        return false;
    }

    /** Managers see company-wide data (dashboard, everyone's requests, calendars) */
    public function isManager(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAnyModule('employees', 'attendances', 'reports', 'vacations_manage', 'justifications_manage');
    }

    /** Timezone used to display dates for this user (falls back to the company timezone) */
    public function displayTimezone(): string
    {
        return $this->timezone ?: company_timezone();
    }
}
