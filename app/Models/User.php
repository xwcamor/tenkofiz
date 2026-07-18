<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The User model is deliberately NOT globally company-scoped: the auth guard
     * queries users while resolving the current user, and a scope that reads
     * auth() would recurse. Instead, user listings scope explicitly with
     * scopeInCompany(), and new users inherit the current company on create.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->company_id) && !$user->is_super_admin && ($companyId = CompanyScope::currentCompanyId())) {
                $user->company_id = $companyId;
            }
        });
    }

    /** Restrict a user query to the current workspace (no-op for super-admin/console) */
    public function scopeInCompany($query)
    {
        if ($companyId = CompanyScope::currentCompanyId()) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    protected $fillable = [
        'name', 'email', 'password', 'profile_id', 'company_id', 'site_id', 'is_super_admin', 'is_active',
        'must_change_password', 'timezone', 'locale', 'photo', 'delete_reason',
        'terms_accepted_at', 'terms_version', 'terms_ip',
    ];

    /** Current version of the terms; bumping it forces everyone to re-accept */
    public const TERMS_VERSION = '1.0';

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'is_super_admin' => 'boolean',
            'terms_accepted_at' => 'datetime',
        ];
    }

    /** Whether this user accepted the CURRENT version of the terms and conditions */
    public function hasAcceptedTerms(): bool
    {
        return $this->terms_accepted_at !== null && $this->terms_version === self::TERMS_VERSION;
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

    /** The workspace (company) this user belongs to */
    public function company()
    {
        return $this->belongsTo(Company::class);
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

    /**
     * Effective module access = the workspace's PLAN includes the module (what the
     * super-admin sold them) AND the user's profile grants it (how the company
     * admin distributed it). The super-admin bypasses profiles but still sees only
     * the plan modules of the workspace they entered.
     */
    public function hasModule(string $module): bool
    {
        if ($this->isSuperAdmin()) {
            return $this->actingCompany()?->hasModule($module) ?? true;
        }

        if ($this->company_id && !($this->company?->hasModule($module) ?? true)) {
            return false; // not contracted in the workspace's plan
        }

        return $this->profile !== null
            && $this->profile->is_active
            && in_array($module, $this->profile->permissions ?? [], true);
    }

    /** The workspace a super-admin is currently administering (null = console) */
    public function actingCompany(): ?Company
    {
        $id = session('acting_company_id');
        if (!$id) {
            return null;
        }

        // Memoized per request: the sidebar asks for a dozen modules per render
        $key = 'acting.company.'.$id;
        if (!app()->bound($key)) {
            app()->instance($key, Company::find($id));
        }

        return app($key);
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
