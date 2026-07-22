<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use BelongsToCompany, HasHashid;

    public const TYPE_FIXED = 'fixed';       // start time + tolerance judge punctuality
    public const TYPE_FLEXIBLE = 'flexible'; // no fixed start; complete a daily hour target
    public const TYPE_FREE = 'free';         // no schedule rules: every punch is just logged (health/field/irregular)

    protected $fillable = ['company_id', 'name', 'is_shared', 'type', 'start_time', 'end_time', 'tolerance_minutes', 'target_minutes', 'async_minutes_per_day', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'is_shared' => 'boolean', 'target_minutes' => 'integer', 'async_minutes_per_day' => 'integer'];

    /** Flexible = count hours against a daily target, no tardiness */
    public function isFlexible(): bool
    {
        return $this->type === self::TYPE_FLEXIBLE;
    }

    /**
     * Free = no schedule enforcement at all. Every mark is just a logged capture
     * (with its anti-fraud evidence); the system does not judge tardiness, absence
     * or hours, and allows any number of marks per day. A human reviews the record.
     */
    public function isFree(): bool
    {
        return $this->type === self::TYPE_FREE;
    }

    /** Only the reusable catalog templates (hides per-person personalized schedules) */
    public function scopeShared($q)
    {
        return $q->where('is_shared', true);
    }

    /** Fixed = classic start/end with tolerance (the default) */
    public function isFixed(): bool
    {
        return $this->type === self::TYPE_FIXED;
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function days()
    {
        return $this->hasMany(ScheduleDay::class)->orderBy('weekday');
    }

    /** Working hours for the given weekday (0=Sunday..6=Saturday), null = day off */
    public function worksOn(int $weekday): ?ScheduleDay
    {
        return $this->days->firstWhere('weekday', $weekday);
    }

    /**
     * Minutes the person is EXPECTED to work on a given weekday. For a flexible
     * schedule that is the daily hour target; for a fixed one it is the shift
     * length (overnight-aware). 0 when they do not work that day. This is the
     * "jornada laboral" the report compares actual worked minutes against.
     */
    public function expectedMinutesFor(int $weekday): int
    {
        // Free mode never sets an expectation — nothing is judged as a deficit.
        if ($this->isFree()) {
            return 0;
        }
        if ($this->isFlexible()) {
            return (int) ($this->target_minutes ?? 0);
        }

        $shift = $this->worksOn($weekday);
        if (!$shift) {
            return 0;
        }

        $start = \Carbon\Carbon::parse('2000-01-01 '.$shift->start_time);
        $end = \Carbon\Carbon::parse('2000-01-01 '.$shift->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay(); // overnight shift
        }

        return (int) $start->diffInMinutes($end);
    }

    /** Short summary such as "Mon–Sat 08:00–17:00" or "Custom" when days differ */
    public function daysSummary(): string
    {
        // Free mode has no schedule — just free punches, reviewed by a person.
        if ($this->isFree()) {
            return __('Free marking (no schedule)');
        }

        if ($this->days->isEmpty()) {
            return __('No working days');
        }

        $dayNames = $this->days->map(fn ($d) => __(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$d->weekday]))->implode(', ');

        // Flexible schedules have no meaningful start/end — show the hour target
        if ($this->isFlexible()) {
            $hours = $this->target_minutes ? round($this->target_minutes / 60, 1) : null;

            return $dayNames.' · '.($hours ? __(':h h/day target', ['h' => $hours]) : __('by hours'));
        }

        $uniformTimes = $this->days->map(fn ($d) => substr($d->start_time, 0, 5).'–'.substr($d->end_time, 0, 5))->unique();

        return $uniformTimes->count() === 1
            ? $dayNames.' · '.$uniformTimes->first()
            : $dayNames.' · '.__('varies per day');
    }
}
