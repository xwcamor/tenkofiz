<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Scopes\SiteScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToCompany, SoftDeletes;

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
        'first_name', 'last_name', 'hire_date', 'contract_type', 'vacation_days_per_year', 'face_descriptor',
        'biometric_consent_at', 'is_active', 'delete_reason',
    ];

    /** Translatable label of the contract type */
    public function contractTypeLabel(): string
    {
        return __(self::CONTRACT_TYPES[$this->contract_type] ?? self::CONTRACT_TYPES['full_time']);
    }

    protected $casts = [
        'hire_date' => 'date',
        'biometric_consent_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
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
}
