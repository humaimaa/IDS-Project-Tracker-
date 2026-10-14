<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('tracker:import-sqlite {--source= : Absolute path to the existing SQLite file}')]
#[Description('Copy SQLite records into an empty, migrated MySQL database without deleting the source')]
class ImportSqliteToMysql extends Command
{
    public function handle(): int
    {
        $sourcePath = realpath($this->option('source') ?: database_path('database.sqlite'));
        if (! $sourcePath || ! is_file($sourcePath)) {
            $this->error('The source SQLite file does not exist.');

            return self::FAILURE;
        }
        if (! extension_loaded('pdo_mysql')) {
            $this->error('Enable pdo_mysql in PHP before importing.');

            return self::FAILURE;
        }
        $target = DB::connection('mysql');
        if ($target->getDriverName() !== 'mysql') {
            $this->error('The target must be the mysql connection.');

            return self::FAILURE;
        }
        $target->getPdo();
        $sourcePdo = new \PDO('sqlite:'.$sourcePath);
        $backupDirectory = storage_path('app/private/backups');
        if (! is_dir($backupDirectory)) {
            mkdir($backupDirectory, 0700, true);
        }
        $backup = $backupDirectory.'/before-mysql-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sqlite';
        $sourcePdo->exec('VACUUM INTO '.$sourcePdo->quote($backup));
        config(['database.connections.tracker_import' => ['driver' => 'sqlite', 'database' => $backup, 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('tracker_import');
        $source = DB::connection('tracker_import');
        if ($source->select('PRAGMA foreign_key_check') !== []) {
            $this->error('Source has broken references. No records were imported.');

            return self::FAILURE;
        }
        $tables = collect($source->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations'"))->pluck('name');
        foreach ($tables as $table) {
            if (! $target->getSchemaBuilder()->hasTable($table)) {
                $this->error('Run php artisan migrate --database=mysql before importing. Missing table: '.$table);

                return self::FAILURE;
            }
        }
        foreach ($target->getSchemaBuilder()->getTableListing() as $table) {
            if (basename(str_replace('.', '/', $table)) !== 'migrations' && $target->table($table)->exists()) {
                $this->error('Import refused: MySQL already contains data. Nothing was overwritten.');

                return self::FAILURE;
            }
        }
        try {
            $target->statement('SET FOREIGN_KEY_CHECKS=0');
            $target->transaction(function () use ($source, $target, $tables): void {
                foreach ($tables as $table) {
                    $column = $source->getSchemaBuilder()->getColumnListing($table)[0];
                    $source->table($table)->orderBy($column)->chunk(100, function ($rows) use ($target, $table): void {
                        $target->table($table)->insert($rows->map(fn ($row): array => (array) $row)->all());
                    });
                    if ($source->table($table)->count() !== $target->table($table)->count()) {
                        throw new RuntimeException('Row count mismatch for '.$table);
                    }
                }
            });
        } finally {
            $target->statement('SET FOREIGN_KEY_CHECKS=1');
            DB::disconnect('tracker_import');
        }
        $this->info('Copied and verified row counts for '.$tables->count().' tables. SQLite remains unchanged.');
        $this->line('Backup: '.$backup);
        $this->line('Set DB_CONNECTION=mysql, DB_DATABASE=ids_project_tracker, DB_USERNAME=root and an empty DB_PASSWORD, then run php artisan config:clear.');

        return self::SUCCESS;
    }
}
