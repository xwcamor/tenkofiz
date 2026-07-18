<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;

    public const STATUSES = ['ON_TIME', 'LATE', 'ABSENT', 'EXCUSED'];

    protected $fillable = [
        'employee_id', 'date', 'check_in', 'check_out',
        'status', 'method', 'similarity', 'note', 'ip', 'user_agent', 'evidence_photo', 'delete_reason',
    ];

    // 'date:Y-m-d' stores a pure date string (no time). Without the explicit
    // format the value is serialized as 'Y-m-d 00:00:00', which breaks
    // firstOrNew(['date' => 'Y-m-d']) lookups on SQLite (MySQL coerces it).
    protected $casts = ['date' => 'date:Y-m-d'];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * Marks ABSENT every active employee whose schedule works on that weekday
     * and who has no attendance record on the given date. Skips holidays,
     * days off per schedule, approved vacations and already excused days.
     * Returns how many records were created.
     */
    public static function markAbsences(string $date): int
    {
        $day = \Carbon\Carbon::parse($date);

        if (Holiday::onDate($date)) {
            return 0;
        }

        $created = 0;

        Employee::where('is_active', true)
            ->whereNotNull('schedule_id')
            // Only employees whose schedule has working hours on that weekday
            ->whereHas('schedule.days', fn ($q) => $q->where('weekday', $day->dayOfWeek))
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
