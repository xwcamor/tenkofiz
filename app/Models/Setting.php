<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_id',
        'company_name', 'tax_id', 'address', 'phone', 'logo', 'timezone', 'country', 'locale', 'cutoff_day', 'kiosk_token',
        'early_check_in_minutes', 'clamp_worked_hours',
        'kiosk_breaks_enabled', 'async_hours_enabled', 'break_required', 'break_limit_minutes', 'kiosk_geolocation', 'kiosk_geolocation_required',
        'allow_holiday_marking',
        'kiosk_pair_code', 'kiosk_pair_expires_at',
        'kiosk_fast_mode', 'kiosk_liveness', 'kiosk_face_threshold', 'kiosk_verify_seconds', 'kiosk_match_seconds',
    ];

    protected $casts = [
        'early_check_in_minutes' => 'integer',
        'clamp_worked_hours' => 'boolean',
        'kiosk_breaks_enabled' => 'boolean',
        'async_hours_enabled' => 'boolean',
        'break_required' => 'boolean',
        'break_limit_minutes' => 'integer',
        'kiosk_geolocation' => 'boolean',
        'kiosk_geolocation_required' => 'boolean',
        'allow_holiday_marking' => 'boolean',
        'kiosk_pair_expires_at' => 'datetime',
        'kiosk_fast_mode' => 'boolean',
        'kiosk_liveness' => 'boolean',
        'kiosk_face_threshold' => 'float',
        'kiosk_verify_seconds' => 'integer',
        'kiosk_match_seconds' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** Current company's settings row (kept for backward compatibility) */
    public static function instance(): self
    {
        return static::forCompany(current_company_id());
    }

    /** Settings row for a company (or the legacy single row when company is null) */
    public static function forCompany(?int $companyId): self
    {
        if ($companyId) {
            return static::firstOrCreate(['company_id' => $companyId]);
        }

        return static::query()->orderBy('id')->first() ?? static::create([]);
    }
}
