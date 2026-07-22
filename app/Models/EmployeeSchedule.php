<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry in an employee's schedule history: "schedule X applies from
 * effective_from to effective_to (null = still in force)". Always reached through
 * the owning Employee (which is company/site scoped), so it needs no scope of its own.
 */
class EmployeeSchedule extends Model
{
    protected $fillable = ['employee_id', 'schedule_id', 'effective_from', 'effective_to'];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
