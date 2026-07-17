<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;

class MarkAbsences extends Command
{
    protected $signature = 'attendances:mark-absences {date? : Date to process (defaults to today in the company timezone)}';

    protected $description = 'Marks ABSENT every employee without an attendance record (skips holidays, non-working days and vacations)';

    public function handle(): int
    {
        $date = $this->argument('date') ?? company_now()->toDateString();
        $created = Attendance::markAbsences($date);
        $this->info("Date {$date}: {$created} absence(s) created.");

        return self::SUCCESS;
    }
}
