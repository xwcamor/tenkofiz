<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'start_time', 'end_time', 'tolerance_minutes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function days()
    {
        return $this->hasMany(ScheduleDay::class)->orderBy('weekday');
    }

    /** Working hours for the given weekday (0=Sunday..6=Saturday), null = day off */
    public function worksOn(int $weekday): ?ScheduleDay
    {
        return $this->days->firstWhere('weekday', $weekday);
    }

    /** Short summary such as "Mon–Sat 08:00–17:00" or "Custom" when days differ */
    public function daysSummary(): string
    {
        if ($this->days->isEmpty()) {
            return __('No working days');
        }

        $uniformTimes = $this->days->map(fn ($d) => substr($d->start_time, 0, 5).'–'.substr($d->end_time, 0, 5))->unique();
        $dayNames = $this->days->map(fn ($d) => __(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$d->weekday]))->implode(', ');

        return $uniformTimes->count() === 1
            ? $dayNames.' · '.$uniformTimes->first()
            : $dayNames.' · '.__('varies per day');
    }
}
