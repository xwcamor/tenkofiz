<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use BelongsToCompany, HasHashid; // profiles are per company (each workspace has its own roles)

    /** Modules that can be granted to a profile (key => translatable label) */
    public const MODULES = [
        'employees' => 'Employees',
        'attendances' => 'Attendance management',
        'reports' => 'Reports',
        'vacations_manage' => 'Approve vacations',
        'justifications_manage' => 'Review justifications',
        'users' => 'Users',
        'profiles' => 'Profiles',
        'schedules' => 'Schedules',
        'holidays' => 'Holidays',
        'audit_logs' => 'Audit log',
        'settings' => 'System settings',
        'kiosk' => 'Marking kiosk',
    ];

    protected $fillable = ['name', 'description', 'permissions', 'is_active', 'is_system'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'permissions' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** The base role that must never lose access to the whole system */
    public function isAdministratorRole(): bool
    {
        return $this->is_system && $this->name === 'Administrator';
    }
}
