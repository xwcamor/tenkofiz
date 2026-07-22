<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasHashid;
use App\Models\Scopes\SiteScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToCompany, HasHashid, SoftDeletes;

    protected static function booted(): void
    {
        // A site-bound user only ever sees their own site's employees. Guests
        // (kiosk) and console jobs are not scoped. See App\Models\Scopes\SiteScope.
        static::addGlobalScope(new SiteScope());

        // When a site-bound user creates an employee, default it to their site.
        static::creating(function (Employee $employee) {
            if (empty($employee->site_id) && ($siteId = SiteScope::currentSiteId())) {
                $employee->site_id = $siteId;
            }
        });
    }

    /** Supported identity documents (key => translatable label) */
    public const DOCUMENT_TYPES = [
        'DNI' => 'DNI (Peru)',
        'CE' => 'Foreigner ID card (CE)',
        'PASSPORT' => 'Passport',
    ];

    /** HR contract type (informative; does not affect attendance timing) */
    public const CONTRACT_TYPES = [
        'full_time' => 'Full-time',
        'part_time' => 'Part-time',
    ];

    protected $fillable = [
        'company_id', 'user_id', 'schedule_id', 'area_id', 'position_id', 'site_id', 'document_type', 'document_number',
        'first_name', 'last_name', 'hire_date', 'termination_date', 'contract_type', 'vacation_days_per_year', 'face_descriptor',
        'biometric_consent_at', 'is_active', 'delete_reason',
    ];

    /** Translatable label of the contract type */
    public function contractTypeLabel(): string
    {
        return __(self::CONTRACT_TYPES[$this->contract_type] ?? self::CONTRACT_TYPES['full_time']);
    }

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'biometric_consent_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** A new employee is active by default (matches the DB default, even before reload). */
    protected $attributes = [
        'is_active' => true,
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    /** Dated schedule history ("vigencias"): schedule X from/to, schedule Y from/to… */
    public function scheduleAssignments()
    {
        return $this->hasMany(EmployeeSchedule::class)->orderBy('effective_from');
    }

    /**
     * The schedule in force on a given date. Looks through the dated assignments
     * (vigencias) and picks the one whose range covers the date — most recent start
     * wins on overlap. Falls back to the base schedule_id when no assignment applies,
     * so employees without any history behave exactly as before.
     */
    public function scheduleOn($date): ?Schedule
    {
        $key = $date instanceof \Carbon\CarbonInterface ? $date->toDateString() : \Carbon\Carbon::parse($date)->toDateString();

        $assignments = $this->relationLoaded('scheduleAssignments')
            ? $this->scheduleAssignments
            : $this->scheduleAssignments()->with('schedule.days')->get();

        $match = $assignments
            ->filter(fn ($a) => $a->effective_from->toDateString() <= $key
                && ($a->effective_to === null || $a->effective_to->toDateString() >= $key))
            ->sortByDesc(fn ($a) => $a->effective_from->toDateString())
            ->first();

        return $match?->schedule ?? $this->schedule;
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function vacations()
    {
        return $this->hasMany(Vacation::class);
    }

    public function justifications()
    {
        return $this->hasMany(Justification::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->last_name}, {$this->first_name}";
    }

    public function hasFace(): bool
    {
        return !empty($this->face_descriptor);
    }

    public function hasBiometricConsent(): bool
    {
        return $this->biometric_consent_at !== null;
    }

    /** Approved vacation days already taken in the given calendar year */
    public function usedVacationDays(?int $year = null): int
    {
        $year ??= (int) company_now()->year;

        return (int) $this->vacations()
            ->where('status', 'APPROVED')
            ->whereYear('start_date', $year)
            ->sum('days');
    }

    /** Days still available this calendar year (allowance minus approved days) */
    public function remainingVacationDays(?int $year = null): int
    {
        return max(0, ($this->vacation_days_per_year ?? 30) - $this->usedVacationDays($year));
    }

    /** Whether an approved vacation covers the given date */
    public function onVacation(string $date): bool
    {
        return $this->vacations()
            ->where('status', 'APPROVED')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Day-by-day attendance for a period, DERIVING the days that have no record so
     * reports are always complete without depending on the nightly absence job.
     * Each returned day is one of:
     *   - a real Attendance (an actual mark, or an EXCUSED/ABSENT row already saved),
     *   - a virtual 'VACATION' day (covered by an approved vacation), or
     *   - a virtual 'ABSENT' day (a scheduled working day with nothing on it).
     * Days the person is NOT expected are skipped entirely: non-working weekday,
     * holiday, before hire, after termination, and anything in the future. When an
     * inactive employee has no termination date, the window closes at their last
     * record so a former worker never accrues endless faltas.
     *
     * @param  \Illuminate\Support\Collection|null  $attendances  Pre-loaded rows (avoids a query)
     * @return \Illuminate\Support\Collection<int, array{date:\Carbon\Carbon, attendance:?Attendance, status:string, virtual:bool}>
     */
    public function periodBreakdown($from, $to, $attendances = null): \Illuminate\Support\Collection
    {
        $from = $from instanceof \Carbon\CarbonInterface ? $from->copy()->startOfDay() : \Carbon\Carbon::parse($from)->startOfDay();
        $to = $to instanceof \Carbon\CarbonInterface ? $to->copy()->startOfDay() : \Carbon\Carbon::parse($to)->startOfDay();

        $rows = ($attendances ?? $this->attendances()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get())
            ->keyBy(fn ($a) => $a->date->toDateString());

        // Clamp to the employment window and never into the future.
        $start = $this->hire_date ? $from->max($this->hire_date->copy()->startOfDay()) : $from->copy();
        $end = $to->copy()->min(company_now()->startOfDay());
        if ($this->termination_date) {
            $end = $end->min($this->termination_date->copy()->startOfDay());
        } elseif (!$this->is_active && $rows->isNotEmpty()) {
            // Left, but no date recorded: close at their last known record.
            $end = $end->min(\Carbon\Carbon::parse($rows->keys()->max())->startOfDay());
        }

        // Preload the schedule history once so scheduleOn() resolves in memory.
        if (!$this->relationLoaded('scheduleAssignments')) {
            $this->load('scheduleAssignments.schedule.days');
        }

        // Preload holidays and approved vacations ONCE for the range (not per day), so
        // a multi-employee report doesn't fire thousands of queries.
        $holidays = $start->lte($end)
            ? Holiday::whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->pluck('date')
                ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->toDateString() : \Carbon\Carbon::parse($d)->toDateString())
                ->flip()
            : collect();

        $vacations = $this->relationLoaded('vacations')
            ? $this->vacations->where('status', 'APPROVED')
            : $this->vacations()->where('status', 'APPROVED')->get();
        $onVacation = fn (string $key) => $vacations->contains(fn ($v) => $key >= $v->start_date->toDateString() && $key <= $v->end_date->toDateString());

        $days = collect();

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();

            if ($rows->has($key)) {
                $att = $rows->get($key);
                $days->push(['date' => $d->copy(), 'attendance' => $att, 'status' => $att->status, 'virtual' => false]);
                continue;
            }

            // No record: only scheduled working days that aren't holidays can be a "falta".
            // The schedule effective on THIS date decides (schedules can change over time).
            if (!$this->scheduleOn($d)?->worksOn($d->dayOfWeek) || $holidays->has($key)) {
                continue;
            }
            if ($onVacation($key)) {
                $days->push(['date' => $d->copy(), 'attendance' => null, 'status' => 'VACATION', 'virtual' => true]);
                continue;
            }

            $days->push(['date' => $d->copy(), 'attendance' => null, 'status' => 'ABSENT', 'virtual' => true]);
        }

        return $days;
    }
}
