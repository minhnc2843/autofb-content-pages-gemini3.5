<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupDbCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup the database (handles SQLite database copies)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database backup...');

        $defaultDb = config('database.default');

        if ($defaultDb !== 'sqlite') {
            $this->warn("Native backup is only implemented for SQLite connection. Current connection is: {$defaultDb}");
            $this->info('To backup MySQL/PostgreSQL, please configure standard cron mysqldump/pg_dump tasks.');
            return self::FAILURE;
        }

        $dbPath = config('database.connections.sqlite.database');

        if (!File::exists($dbPath)) {
            $this->error("SQLite database file not found at: {$dbPath}");
            return self::FAILURE;
        }

        $backupDir = storage_path('backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = "{$backupDir}/backup-{$timestamp}.sqlite";

        try {
            File::copy($dbPath, $backupPath);
            $this->info("Database backup created successfully: {$backupPath}");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create database backup: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
