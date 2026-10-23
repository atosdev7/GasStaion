<?php

namespace App\Console\Commands;

use App\Support\Admin\Audit;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

// Daily safety copy: mysqldump of the whole database, gzipped, keeping the last N days.
class DumpDatabase extends Command
{
    // --note: why it was taken (the confirmed day), kept in the audit log.
    // --if-older-than=H: do nothing when a dump from the last H hours exists (the nightly safety net: the confirmation makes the real ones).
    protected $signature = 'backup:dump {--note=} {--if-older-than=}';

    protected $description = 'Write a gzipped mysqldump of the database and delete dumps older than backup.keep_days';

    public function handle()
    {
        $dir = storage_path('app/' . config('backup.dump_dir'));
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $hours = $this->option('if-older-than');
        if ($hours !== null && $hours !== '') {
            $newest = collect(glob($dir . DIRECTORY_SEPARATOR . '*.sql.gz') ?: [])->map(function ($f) { return filemtime($f); })->max();
            if ($newest && $newest > now()->subHours((float) $hours)->getTimestamp()) {
                $this->info('Skipped: a dump from the last ' . $hours . ' hours exists.');

                return 0;
            }
        }

        $db = config('database.connections.' . config('database.default'));
        $name = $db['database'] . '-' . now()->format('Y-m-d-His') . '.sql.gz';
        $sqlPath = $dir . DIRECTORY_SEPARATOR . $name . '.tmp';

        // The password stays off the command line; the whole environment goes along: from the built-in web server Symfony passes none of it, and without
        // SystemRoot mysqldump cannot open a socket on Windows. On Windows the server may fail to delete a temporary file of its own while a virus scanner
        // holds it ("Errcode: 13"): the dump is tried again a few times then.
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $process = new Process([
                config('backup.mysqldump'),
                '--host=' . $db['host'], '--port=' . $db['port'], '--user=' . $db['username'],
                '--single-transaction', '--routines', '--default-character-set=utf8mb4',
                '--result-file=' . $sqlPath,
                $db['database'],
            ], null, array_merge(getenv(), ['MYSQL_PWD' => (string) $db['password']]));
            $process->setTimeout(3600)->run();
            if ($process->isSuccessful() || !preg_match('/Errcode: 13|Permission denied/i', $process->getErrorOutput())) {
                break;
            }
            @unlink($sqlPath);
            sleep(1);
        }

        if (!$process->isSuccessful()) {
            @unlink($sqlPath);
            $this->error('mysqldump failed: ' . trim($process->getErrorOutput()));
            Audit::log('backup_dump_failed', trim($process->getErrorOutput()), 'system');

            return 1;
        }

        $gzPath = $dir . DIRECTORY_SEPARATOR . $name;
        $in = fopen($sqlPath, 'rb');
        $out = gzopen($gzPath, 'wb6');
        while (!feof($in)) {
            gzwrite($out, fread($in, 1024 * 1024));
        }
        fclose($in);
        gzclose($out);
        unlink($sqlPath);

        $deleted = $this->prune($dir);
        Audit::log('backup_dump', $name . ($this->option('note') ? ' (' . $this->option('note') . ')' : '') . ($deleted ? ', removed ' . $deleted . ' old' : ''), 'system');
        $this->info('Wrote ' . $name);

        return 0;
    }

    private function prune($dir)
    {
        $cutoff = now()->subDays(config('backup.keep_days'))->getTimestamp();
        $deleted = 0;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.sql.gz') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
