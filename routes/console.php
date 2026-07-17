<?php

use Illuminate\Support\Facades\Schedule;

// Generates the day's absences automatically at the end of the workday.
// Requires the scheduler running: `php artisan schedule:work` (or a cron in production).
// The time is evaluated in the company timezone (the server itself runs in UTC).
Schedule::command('attendances:mark-absences')
    ->dailyAt('23:50')
    ->timezone(company_timezone());
