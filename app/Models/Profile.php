<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
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

    protected $fillable = ['name', 'description', 'permissions', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'permissions' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
