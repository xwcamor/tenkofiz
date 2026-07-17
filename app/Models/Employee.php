<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    /** Supported identity documents (key => translatable label) */
    public const DOCUMENT_TYPES = [
        'DNI' => 'DNI (Peru)',
        'CE' => 'Foreigner ID card (CE)',
        'PASSPORT' => 'Passport',
    ];

    protected $fillable = [
        'user_id', 'schedule_id', 'area_id', 'position_id', 'document_type', 'document_number',
        'first_name', 'last_name', 'hire_date', 'face_descriptor',
        'biometric_consent_at', 'is_active',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'biometric_consent_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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
