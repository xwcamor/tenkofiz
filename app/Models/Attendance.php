<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    public const STATUSES = ['ON_TIME', 'LATE', 'ABSENT', 'EXCUSED'];

    protected $fillable = [
        'employee_id', 'date', 'check_in', 'check_out',
        'status', 'method', 'similarity', 'note', 'ip', 'user_agent',
    ];

    protected $casts = ['date' => 'date'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** Non-working weekdays (0 = Sunday). Adjust to the company's reality. */
    public const NON_WORKING_DAYS = [0];

    /**
     * Marks ABSENT every active employee with a schedule who has no attendance
     * record on the given date. Skips holidays, non-working days, approved
     * vacations and already excused days. Returns how many records were created.
     */
    public static function markAbsences(string $date): int
    {
        $day = \Carbon\Carbon::parse($date);

        if (in_array($day->dayOfWeek, self::NON_WORKING_DAYS, true)) {
            return 0;
        }

        if (Holiday::onDate($date)) {
            return 0;
        }

        $created = 0;

        Employee::where('is_active', true)
            ->whereNotNull('schedule_id')
            ->whereDoesntHave('attendances', fn ($q) => $q->whereDate('date', $date))
            ->each(function (Employee $employee) use ($date, &$created) {
                if ($employee->onVacation($date)) {
                    return;
                }

                static::create([
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'status' => 'ABSENT',
                    'method' => 'MANUAL',
                    'note' => __('Absence generated automatically (no check-in)'),
                ]);
                $created++;
            });

        return $created;
    }
}
