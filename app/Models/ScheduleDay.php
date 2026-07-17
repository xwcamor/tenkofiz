<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleDay extends Model
{
    public $timestamps = false;

    protected $fillable = ['schedule_id', 'weekday', 'start_time', 'end_time'];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    /** True when the shift ends past midnight (e.g. 22:00 → 06:00) */
    public function crossesMidnight(): bool
    {
        return $this->end_time < $this->start_time;
    }
}
