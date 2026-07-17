<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class SystemBackup extends Command
{
    protected $signature = 'system:backup {--keep=14 : How many backups to keep}';

    protected $description = 'Backs up the database and public/uploads into storage/app/backups (zip with retention)';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stamp = company_now()->format('Y-m-d_His');
        $zipPath = "{$dir}/backup_{$stamp}.zip";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->error("Could not create {$zipPath}");
            return self::FAILURE;
        }

        // ---- Database ----
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $database = DB::connection()->getDatabaseName();
            if (is_file($database)) {
                $zip->addFile($database, 'database.sqlite');
            }
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $dump = $this->mysqlDump();
            if ($dump !== null) {
                $zip->addFromString('database.sql', $dump);
            } else {
                $this->warn('mysqldump failed or is not installed: the backup contains uploads only.');
                $zip->addFromString('DATABASE_NOT_INCLUDED.txt', 'mysqldump was not available when this backup ran.');
            }
        }

        // ---- Uploads (logo, justification documents, kiosk evidence, avatars) ----
        $uploads = public_path('uploads');
        if (is_dir($uploads)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $zip->addFile($file->getPathname(), 'uploads/'.substr($file->getPathname(), strlen($uploads) + 1));
                }
            }
        }

        $zip->close();
        $this->info('Backup created: '.$zipPath.' ('.round(filesize($zipPath) / 1024 / 1024, 1).' MB)');

        // ---- Retention ----
        $backups = glob("{$dir}/backup_*.zip");
        sort($backups);
        $keep = max(1, (int) $this->option('keep'));
        foreach (array_slice($backups, 0, max(0, count($backups) - $keep)) as $old) {
            unlink($old);
            $this->line('Removed old backup: '.basename($old));
        }

        return self::SUCCESS;
    }

    private function mysqlDump(): ?string
    {
        $config = DB::connection()->getConfig();
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines %s 2>/dev/null',
            escapeshellarg($config['host'] ?? '127.0.0.1'),
            escapeshellarg((string) ($config['port'] ?? 3306)),
            escapeshellarg($config['username'] ?? ''),
            escapeshellarg($config['password'] ?? ''),
            escapeshellarg($config['database'] ?? '')
        );

        $output = shell_exec($command);

        return (is_string($output) && strlen($output) > 100) ? $output : null;
    }
}
