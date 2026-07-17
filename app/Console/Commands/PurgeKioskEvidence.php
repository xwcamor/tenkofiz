<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;

class PurgeKioskEvidence extends Command
{
    protected $signature = 'kiosk:purge-evidence {--days=90 : Delete evidence photos older than this many days}';

    protected $description = 'Deletes old DNI-mark evidence photos (data minimization); the attendance records are kept';

    public function handle(): int
    {
        $cutoff = company_now()->subDays(max(1, (int) $this->option('days')))->toDateString();
        $removed = 0;

        Attendance::whereNotNull('evidence_photo')
            ->whereDate('date', '<', $cutoff)
            ->each(function (Attendance $attendance) use (&$removed) {
                $path = public_path($attendance->evidence_photo);
                if (is_file($path)) {
                    @unlink($path);
                }
                $attendance->update(['evidence_photo' => null]);
                $removed++;
            });

        $this->info("Evidence photos removed: {$removed} (older than {$cutoff}).");

        return self::SUCCESS;
    }
}
