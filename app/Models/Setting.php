<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_name', 'tax_id', 'address', 'phone', 'logo', 'timezone', 'kiosk_token', 'kiosk_enroll_pin',
    ];

    /** Returns the single settings row (creates it if missing) */
    public static function instance(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
