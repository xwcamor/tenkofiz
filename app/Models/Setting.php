<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_name', 'tax_id', 'address', 'phone', 'logo', 'timezone', 'cutoff_day', 'kiosk_token', 'kiosk_enroll_pin',
        'early_check_in_minutes', 'early_departure_minutes',
        'kiosk_device_hash', 'kiosk_pair_code', 'kiosk_pair_expires_at',
    ];

    protected $casts = [
        'early_check_in_minutes' => 'integer',
        'early_departure_minutes' => 'integer',
        'kiosk_pair_expires_at' => 'datetime',
    ];

    /** Returns the single settings row (creates it if missing) */
    public static function instance(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
