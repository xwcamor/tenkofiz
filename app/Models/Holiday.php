<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['date', 'name'];

    // 'date:Y-m-d' stores a pure date string. Without the explicit format the value
    // serializes as 'Y-m-d 00:00:00', which breaks firstOrCreate(['date' => ...]) on
    // SQLite (same fix as Attendance::date).
    protected $casts = ['date' => 'date:Y-m-d'];

    public static function onDate(string $date): ?self
    {
        return static::whereDate('date', $date)->first();
    }
}
