<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_id',
        'company_name', 'tax_id', 'address', 'phone', 'logo', 'timezone', 'country', 'cutoff_day', 'kiosk_token', 'kiosk_enroll_pin',
        'early_check_in_minutes', 'early_departure_minutes',
        'kiosk_device_hash', 'kiosk_pair_code', 'kiosk_pair_expires_at',
        'kiosk_fast_mode', 'kiosk_liveness', 'kiosk_require_face', 'kiosk_face_threshold',
    ];

    protected $casts = [
        'early_check_in_minutes' => 'integer',
        'early_departure_minutes' => 'integer',
        'kiosk_pair_expires_at' => 'datetime',
        'kiosk_fast_mode' => 'boolean',
        'kiosk_liveness' => 'boolean',
        'kiosk_require_face' => 'boolean',
        'kiosk_face_threshold' => 'float',
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
