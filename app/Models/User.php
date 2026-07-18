<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'profile_id', 'is_active',
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
        ];
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /** Whether the user's profile grants access to the given module */
    public function hasModule(string $module): bool
    {
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
        return $this->hasAnyModule('employees', 'attendances', 'reports', 'vacations_manage', 'justifications_manage');
    }

    /** Timezone used to display dates for this user (falls back to the company timezone) */
    public function displayTimezone(): string
    {
        return $this->timezone ?: company_timezone();
    }
}
