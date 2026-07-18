<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_name', 'tax_id', 'address', 'phone', 'logo', 'timezone', 'cutoff_day', 'kiosk_token', 'kiosk_enroll_pin',
        'early_check_in_minutes', 'early_departure_minutes',
    ];

    protected $casts = [
        'early_check_in_minutes' => 'integer',
        'early_departure_minutes' => 'integer',
    ];

    /** Returns the single settings row (creates it if missing) */
    public static function instance(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
