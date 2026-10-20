<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Console\Command;

// Run on the live server right after a dump was restored (and after any data that only the web app held has been entered again: users and
// customers get their ids from the database, so enter them before this). It tells the Smart210s that the server's data is only valid up to the
// time of the dump: at its next download a terminal marks everything it uploaded after that time as not sent, and the next Upload data sends it
// again (the web app stores a record by its key, so what is already there stays as it is).
class BackupRestored extends Command
{
    protected $signature = 'backup:restored {--since= : when the restored dump was taken, "Y-m-d H:i:s" in the station time (default: the newest dump file\'s time)}';

    protected $description = 'Tell the Smart210s that the database was restored, so that they send again what they uploaded after the dump';

    public function handle()
    {
        $since = $this->option('since');
        if ($since === null || $since === '') {
            $dir = storage_path('app/' . config('backup.dump_dir'));
            $newest = collect(glob($dir . DIRECTORY_SEPARATOR . '*.sql.gz') ?: [])->map(function ($f) {
                return filemtime($f);
            })->max();
            if (!$newest) {
                $this->error('No dump found in ' . $dir . ': give the time of the restored dump with --since="Y-m-d H:i:s".');

                return 1;
            }
            $since = date('Y-m-d H:i:s', $newest);
        }

        $time = \DateTime::createFromFormat('Y-m-d H:i:s', $since);
        if (!$time || $time->format('Y-m-d H:i:s') !== $since) {
            $this->error('The time must look like 2026-10-10 08:30:00.');

            return 1;
        }
        if ($time > now()) {
            $this->error('The time of the dump is in the future.');

            return 1;
        }

        Setting::updateOrCreate(['key' => 'restored_since'], ['value' => $since]);
        Setting::updateOrCreate(['key' => 'restored_at'], ['value' => now()->format('Y-m-d H:i:s')]);
        Audit::log('backup_restored', 'data valid up to ' . $since, 'system');

        $this->info('Done. At their next download the Smart210s mark what they uploaded after ' . $since . ' as not sent; the cashiers then press Upload data.');

        return 0;
    }
}
