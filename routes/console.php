<?php

use Illuminate\Support\Facades\Schedule;

// All times below run in the company timezone (the server itself is UTC).
// They require the Laravel scheduler: `php artisan schedule:work` (or a cron in production).

// Marks ABSENT everyone without a record at the end of the workday
Schedule::command('attendances:mark-absences')
    ->dailyAt('23:50')
    ->timezone(company_timezone());

// Daily backup of the database + uploads (keeps the last 14)
Schedule::command('system:backup')
    ->dailyAt('02:00')
    ->timezone(company_timezone());

// Weekly cleanup of old DNI-mark evidence photos (data minimization)
Schedule::command('kiosk:purge-evidence --days=90')
    ->weeklyOn(0, '03:00')
    ->timezone(company_timezone());
