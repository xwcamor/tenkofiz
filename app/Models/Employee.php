<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    /** Supported identity documents (key => translatable label) */
    public const DOCUMENT_TYPES = [
        'DNI' => 'DNI (Peru)',
        'CE' => 'Foreigner ID card (CE)',
        'PASSPORT' => 'Passport',
    ];

    protected $fillable = [
        'user_id', 'schedule_id', 'area_id', 'position_id', 'site_id', 'document_type', 'document_number',
        'first_name', 'last_name', 'hire_date', 'vacation_days_per_year', 'face_descriptor',
        'biometric_consent_at', 'is_active', 'delete_reason',
    ];

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
