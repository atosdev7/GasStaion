<?php

namespace App\Console\Commands;

use App\Support\Admin\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

// Backup server only: merge an export zip from the live server. Every row is inserted, or updated
// if its primary key already exists, so importing each export in turn builds up the full history —
// rows cleared on the live server stay here. A generic merge of every table, so raw SQL.
class ImportBackup extends Command
{
    protected $signature = 'backup:import {file : Path to a backup-upto-*.zip export}';

    protected $description = 'Merge a backup export zip into this (backup server) database';

    const BATCH = 500;

    // table => a column that older exports still hold and the table does not have any more
    const RETIRED = ['supplements' => ['user_id', 'manual_reading']];

    public function handle()
    {
        if (!config('backup.is_backup_server')) {
            $this->error('Refused: this is not the backup server (set BACKUP_IS_BACKUP_SERVER=true in .env on the backup server only).');

            return 1;
        }

        $zip = new ZipArchive;
        if ($zip->open($this->argument('file')) !== true) {
            $this->error('Cannot open ' . $this->argument('file'));

            return 1;
        }
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        if (!is_array($manifest) || !isset($manifest['rows'])) {
            $this->error('Not a backup export: manifest.json is missing.');

            return 1;
        }

        $local = collect(DB::select('SHOW TABLES'))->map(function ($r) {
            return array_values((array) $r)[0];
        })->all();

        DB::transaction(function () use ($zip, $manifest, $local) {
            foreach (array_keys($manifest['rows']) as $table) {
                if (!in_array($table, $local, true)) {
                    $this->warn("Skipped $table: no such table here.");
                    continue;
                }
                $n = $this->mergeTable($table, $zip->getStream($table . '.jsonl'));
                $this->line("$table: $n rows");
            }
        });
        $zip->close();

        Audit::log('backup_import', basename($this->argument('file')) . ' (up to ' . $manifest['up_to_date'] . ')', 'system');
        $this->info('Imported.');

        return 0;
    }

    private function mergeTable($table, $stream)
    {
        $count = 0;
        $batch = [];
        // An export made before checkout_date was renamed to date: read the old name as the new one.
        $renamed = !Schema::hasColumn($table, 'checkout_date') && Schema::hasColumn($table, 'date');
        while (($line = fgets($stream)) !== false) {
            if (trim($line) === '') {
                continue;
            }
            $row = json_decode($line, true);
            if ($renamed && is_array($row) && array_key_exists('checkout_date', $row)) {
                $row['date'] = $row['checkout_date'];
                unset($row['checkout_date']);
            }
            // An export made before a supply's date was called supply_date.
            if ($table === 'supplements' && is_array($row) && array_key_exists('date', $row) && !Schema::hasColumn('supplements', 'date')) {
                $row['supply_date'] = $row['date'];
                unset($row['date']);
            }
            // An export made before the amounts of a supply were called out_amount_kg / out_amount_lt.
            if ($table === 'supplements' && is_array($row) && !Schema::hasColumn('supplements', 'amount_kg')) {
                foreach (['amount_kg' => 'out_amount_kg', 'liters_dispensed' => 'out_amount_lt'] as $old => $new) {
                    if (array_key_exists($old, $row)) {
                        $row[$new] = $row[$old];
                        unset($row[$old]);
                    }
                }
            }
            // An export made before a remainder's moment was created_at and its day remainder_date.
            if ($table === 'remainders' && is_array($row) && !Schema::hasColumn('remainders', 'remainder_at')) {
                foreach (['remainder_at' => 'created_at', 'date' => 'remainder_date'] as $old => $new) {
                    if (array_key_exists($old, $row)) {
                        $row[$new] = $row[$old];
                        unset($row[$old]);
                    }
                }
            }
            // Columns a table has lost since the export was made are left out (a supply keeps no user any more).
            if (is_array($row) && isset(self::RETIRED[$table])) {
                foreach (self::RETIRED[$table] as $retired) {
                    if (!Schema::hasColumn($table, $retired)) {
                        unset($row[$retired]);
                    }
                }
            }
            // An export made before a sale kept only its date: the time stamp sold_at is gone and its date (once checkout_date, then date) is
            // sale_date. A sale that had no date yet takes the day of its time stamp.
            if ($table === 'sales' && is_array($row) && array_key_exists('sold_at', $row) && !Schema::hasColumn('sales', 'sold_at')) {
                $row['sale_date'] = isset($row['date']) ? $row['date'] : (isset($row['checkout_date']) ? $row['checkout_date'] : substr((string) $row['sold_at'], 0, 10));
                unset($row['sold_at'], $row['date'], $row['checkout_date']);
            }
            $batch[] = $row;
            if (count($batch) === self::BATCH) {
                $this->upsert($table, $batch);
                $count += count($batch);
                $batch = [];
            }
        }
        if ($batch) {
            $this->upsert($table, $batch);
            $count += count($batch);
        }
        fclose($stream);

        return $count;
    }

    private function upsert($table, array $rows)
    {
        $columns = array_keys($rows[0]);
        $quoted = implode(', ', array_map(function ($c) {
            return '`' . str_replace('`', '``', $c) . '`';
        }, $columns));
        $row = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $updates = implode(', ', array_map(function ($c) {
            $c = '`' . str_replace('`', '``', $c) . '`';

            return "$c = VALUES($c)";
        }, $columns));

        $bindings = [];
        foreach ($rows as $r) {
            foreach ($columns as $c) {
                $bindings[] = $r[$c] ?? null;
            }
        }

        DB::statement(
            "INSERT INTO `$table` ($quoted) VALUES " . implode(', ', array_fill(0, count($rows), $row)) . " ON DUPLICATE KEY UPDATE $updates",
            $bindings
        );
    }
}
