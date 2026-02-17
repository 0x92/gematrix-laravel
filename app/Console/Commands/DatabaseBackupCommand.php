<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Create a PostgreSQL backup in storage/backups';

    public function handle(): int
    {
        if (config('database.default') !== 'pgsql') {
            $this->warn('Backup command is configured for PostgreSQL only.');

            return self::SUCCESS;
        }

        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $filename = $backupDir.'/backup_'.now()->format('Ymd_His').'.sql';

        $host = env('DB_HOST', 'db');
        $port = env('DB_PORT', '5432');
        $database = env('DB_DATABASE', 'gematrix');
        $user = env('DB_USERNAME', 'gematrix');
        $password = env('DB_PASSWORD', 'gematrix');

        $cmd = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s > %s',
            escapeshellarg($password),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($database),
            escapeshellarg($filename)
        );

        exec($cmd, $output, $code);

        if ($code !== 0) {
            $this->error('Database backup failed.');

            return self::FAILURE;
        }

        $this->info('Backup created: '.$filename);

        return self::SUCCESS;
    }
}
