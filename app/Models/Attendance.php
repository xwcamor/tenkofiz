<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScopedSite;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use BelongsToScopedSite, HasHashid, SoftDeletes;

    public const STATUSES = ['ON_TIME', 'LATE', 'ABSENT', 'EXCUSED'];

    protected $fillable = [
        'employee_id', 'date', 'check_in', 'check_out', 'break_out', 'break_in',
        'status', 'expected_minutes', 'shift_start', 'shift_end', 'method', 'similarity', 'note', 'ip', 'user_agent', 'evidence_photo', 'delete_reason',
    ];

    // 'date:Y-m-d' stores a pure date string (no time). Without the explicit
    // format the value is serialized as 'Y-m-d 00:00:00', which breaks
    // firstOrNew(['date' => 'Y-m-d']) lookups on SQLite (MySQL coerces it).
    protected $casts = ['date' => 'date:Y-m-d'];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /** Raw punch log for this day (ZKTeco-style), in chronological order */
    public function marks()
    {
        return $this->hasMany(AttendanceMark::class)->orderBy('marked_at');
    }

    /**
     * Minutes present this day. When a $shift is passed, the window is CLAMPED to
     * the schedule — max(check-in, shift start) → min(check-out, shift end) — so
     * marking early or leaving late never inflates the total. Without a $shift it
     * is the raw check-out − check-in. Overnight shifts and marks (end before
     * start) roll the end to the next day.
     *
     * The break is NOT subtracted here (business rule): the break is an
     * internal detail — you can see when it happened in the break-analysis report —
     * but it does not reduce the worked/complied hours.
     */
    public function workedMinutes(?ScheduleDay $shift = null): int
    {
        if (!$this->check_in || !$this->check_out) {
            return 0;
        }

        $date = $this->date->toDateString();
        $start = \Carbon\Carbon::parse($date.' '.$this->check_in);
        $end = \Carbon\Carbon::parse($date.' '.$this->check_out);
        if ($end->lessThan($start)) {
            $end->addDay(); // overnight mark
        }

        if ($shift) {
            $schedStart = \Carbon\Carbon::parse($date.' '.$shift->start_time);
            $schedEnd = \Carbon\Carbon::parse($date.' '.$shift->end_time);
            if ($schedEnd->lessThanOrEqualTo($schedStart)) {
                $schedEnd->addDay(); // overnight shift
            }
            if ($start->lessThan($schedStart)) {
                $start = $schedStart;
            }
            if ($end->greaterThan($schedEnd)) {
                $end = $schedEnd;
            }
            if ($end->lessThanOrEqualTo($start)) {
                return 0;
            }
        }

        return max(0, (int) $start->diffInMinutes($end));
    }

    /**
     * Hours that COUNT toward the day's quota: the worked minutes capped at what
     * was due that day. Overtime is never credited (staying late does not earn
     * hours — that is handled internally/manually), so this is min(worked, due).
     * $shift clamps fixed schedules; $expected is the day's quota (shift length or
     * the flexible daily target).
     */
    public function compliedMinutes(int $expected, ?ScheduleDay $shift = null): int
    {
        return min($this->workedMinutes($shift), max(0, $expected));
    }

    /**
     * The shift to clamp worked hours against: the bounds FROZEN at check-in when
     * present (immune to later schedule changes), otherwise the current schedule
     * as a fallback for legacy rows. null = no clamp (flexible / no shift).
     */
    public function clampShift(?Schedule $currentSchedule): ?ScheduleDay
    {
        if ($this->shift_start && $this->shift_end) {
            return new ScheduleDay(['start_time' => $this->shift_start, 'end_time' => $this->shift_end]);
        }

        return $currentSchedule?->isFixed() ? $currentSchedule->worksOn($this->date->dayOfWeek) : null;
    }

    /** Minutes spent on break this day (0 when there was none) */
    public function breakMinutes(): int
    {
        if (!$this->break_out || !$this->break_in) {
            return 0;
        }

        $date = $this->date->toDateString();
        $out = \Carbon\Carbon::parse($date.' '.$this->break_out);
        $in = \Carbon\Carbon::parse($date.' '.$this->break_in);
        if ($in->lessThan($out)) {
            $in->addDay();
        }

        return (int) $out->diffInMinutes($in);
    }

    /** How many minutes the break went over the company limit (0 if within) */
    public function breakExceededMinutes(int $limit): int
    {
        return $limit > 0 ? max(0, $this->breakMinutes() - $limit) : 0;
    }

    /** Checked in but never checked out — an abandoned/unclosed day (for review) */
    public function isOpen(): bool
    {
        return $this->check_in && !$this->check_out;
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
