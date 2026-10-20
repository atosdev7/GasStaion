<?php

// 9.3: backup and restore rehearsal on a COPY of data.
//
//   php e2e\backup_rehearsal.php [source database]      default source: gasstation_e2e (what the end-to-end run leaves behind)
//
// The source is only read (mysqldump). Everything else happens in three scratch databases made new for the run:
//   gasstation_rehearsal_live     the copy of the source: the "live server"
//   gasstation_rehearsal_restore  where the dumps are restored to
//   gasstation_rehearsal_backup   the offline backup server (empty schema, backup:import merges the exports into it)
// What it rehearses: the daily dump and its restore (every table identical), the backup run (export, clear up to a date, tank stock unchanged,
// debts and loans kept), the import on the backup server (merge, again, refused on a live server), restoring the dump taken before the clearing, and
// the retention of old dumps. Dumps and exports go to web/storage/app/rehearsal, not to the real backup folders.

const LIVE = 'gasstation_rehearsal_live';
const RESTORE = 'gasstation_rehearsal_restore';
const BACKUP = 'gasstation_rehearsal_backup';
const PREFIX = 'gasstation_rehearsal_';

$source = $argv[1] ?? 'gasstation_e2e';

putenv('DB_DATABASE=' . LIVE);
$_ENV['DB_DATABASE'] = LIVE;
$_SERVER['DB_DATABASE'] = LIVE;
require __DIR__ . '/../web/vendor/autoload.php';
$app = require __DIR__ . '/../web/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Backup;
use App\Support\BackupRun;
use App\Support\TankLevels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$checks = 0;
$failures = 0;

function ok($condition, $what)
{
    global $checks, $failures;
    ++$checks;
    if (!$condition) {
        ++$failures;
    }
    echo ($condition ? '  ok    ' : '  FAIL  ') . $what . "\n";
}

function section($title)
{
    echo "\n== $title\n";
}

// The app works on one of the scratch databases (never on any other).
function useDb($name)
{
    if (strpos($name, PREFIX) !== 0) {
        fwrite(STDERR, "ABORT: $name is not a rehearsal database\n");
        exit(2);
    }
    config(['database.connections.mysql.database' => $name]);
    DB::purge('mysql');
    if (DB::connection()->getDatabaseName() !== $name) {
        fwrite(STDERR, "ABORT: wrong database\n");
        exit(2);
    }
}

function mysqlPath($tool)
{
    return dirname(config('backup.mysqldump')) . DIRECTORY_SEPARATOR . $tool . '.exe';
}

function run(array $command, $input = null)
{
    $db = config('database.connections.mysql');
    $p = new Process($command, null, ['MYSQL_PWD' => (string) $db['password']]);
    if ($input !== null) {
        $p->setInput($input);
    }
    $p->setTimeout(600)->run();
    if (!$p->isSuccessful()) {
        fwrite(STDERR, 'FAILED: ' . implode(' ', array_slice($command, 0, 3)) . ' ' . $p->getErrorOutput() . "\n");
        exit(2);
    }

    return $p->getOutput();
}

function conn()
{
    $c = config('database.connections.mysql');

    return ['--host=' . $c['host'], '--port=' . $c['port'], '--user=' . $c['username']];
}

function recreate($name)
{
    $c = config('database.connections.mysql');
    $pdo = new PDO('mysql:host=' . $c['host'] . ';port=' . $c['port'], $c['username'], $c['password']);
    if (strpos($name, PREFIX) !== 0) {
        exit(2);
    }
    $pdo->exec('DROP DATABASE IF EXISTS `' . $name . '`');
    $pdo->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}

// A dump (a file, possibly gzipped) loaded into a scratch database.
function load($file, $database)
{
    recreate($database);
    $sql = substr($file, -3) === '.gz' ? gzdecode(file_get_contents($file)) : file_get_contents($file);
    $tmp = tempnam(sys_get_temp_dir(), 'rh') . '.sql';
    file_put_contents($tmp, $sql);
    run(array_merge([mysqlPath('mysql')], conn(), ['--default-character-set=utf8mb4', $database, '-e', 'source ' . str_replace('\\', '/', $tmp)]));
    unlink($tmp);
}

// row count and CHECKSUM of every table: two databases with the same fingerprint hold the same data
function fingerprint($database)
{
    $pdo = DB::connection()->getPdo();
    $out = [];
    foreach ($pdo->query('SHOW TABLES FROM `' . $database . '`')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $database . '`.`' . $table . '`')->fetchColumn();
        $sum = $pdo->query('CHECKSUM TABLE `' . $database . '`.`' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        $out[$table] = $count . ':' . $sum;
    }
    ksort($out);

    return $out;
}

function differences($a, $b, $ignore = [])
{
    $diff = [];
    foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $t) {
        if (!in_array($t, $ignore, true) && ($a[$t] ?? null) !== ($b[$t] ?? null)) {
            $diff[] = $t;
        }
    }

    return $diff;
}

$dumpDir = 'rehearsal/daily';
$exportDir = 'rehearsal/exports';
config(['backup.dump_dir' => $dumpDir, 'backup.export_dir' => $exportDir, 'backup.is_backup_server' => false, 'backup.dump_before_clear' => true]);
foreach ([$dumpDir, $exportDir] as $d) {
    foreach (glob(storage_path('app/' . $d) . '/*') ?: [] as $f) {
        unlink($f);
    }
}

// ------------------------------------------------------------------------------------------------------------------------------------------
section("the copy of data ($source, read only)");
recreate(LIVE);
$tmp = tempnam(sys_get_temp_dir(), 'src') . '.sql';
run(array_merge([config('backup.mysqldump')], conn(), ['--single-transaction', '--routines', '--default-character-set=utf8mb4', '--result-file=' . $tmp, $source]));
load($tmp, LIVE);
unlink($tmp);
useDb(LIVE);
$tables = DB::select('SHOW TABLES');
ok(count($tables) > 20, 'the copy has ' . count($tables) . ' tables');
$salesBefore = DB::table('sales')->count();
ok($salesBefore > 0, "the copy holds data ($salesBefore sales, " . DB::table('supplements')->count() . ' supplements, ' . DB::table('tickets')->count() . ' tickets)');
$fingerLive = fingerprint(LIVE);

// ------------------------------------------------------------------------------------------------------------------------------------------
section('the daily dump and its restore');
ok(Artisan::call('backup:dump') === 0, 'backup:dump succeeds');
$dumps = glob(storage_path('app/' . $dumpDir) . '/*.sql.gz');
ok(count($dumps) === 1 && filesize($dumps[0]) > 1000, 'one gzipped dump was written (' . (count($dumps) ? round(filesize($dumps[0]) / 1024) . ' KB' : '-') . ')');
load($dumps[0], RESTORE);
useDb(RESTORE);
$fingerRestore = fingerprint(RESTORE);
useDb(LIVE);
ok(differences($fingerLive, $fingerRestore) === [], 'the restored database is identical to the live one, table by table (' . count($fingerRestore) . ' tables, rows and checksums)' . (differences($fingerLive, $fingerRestore) ? ' differ: ' . implode(', ', differences($fingerLive, $fingerRestore)) : ''));

// ------------------------------------------------------------------------------------------------------------------------------------------
section('retention of old dumps');
$old = storage_path('app/' . $dumpDir) . '/' . LIVE . '-2020-01-01-000000.sql.gz';
file_put_contents($old, gzencode('-- old'));
touch($old, time() - 10 * 86400);
sleep(1);
Artisan::call('backup:dump');
ok(!file_exists($old), 'a dump older than keep_days (' . config('backup.keep_days') . ') is removed');
ok(count(glob(storage_path('app/' . $dumpDir) . '/*.sql.gz')) === 2, 'the recent dumps are kept');

// ------------------------------------------------------------------------------------------------------------------------------------------
section('the backup run: export, then clear up to yesterday');
// up to the day before the newest day of work, so that the newest day stays (as it would: today's records may still change)
$upTo = date('Y-m-d', strtotime(DB::table('sales')->max('sale_date') . ' -1 day'));
$preview = BackupRun::preview($upTo);
$tanksBefore = TankLevels::all()->mapWithKeys(function ($r) { return [$r->tank->id => round($r->current, 2)]; })->all();
$kept = ['ticket_loans', 'ticket_loan_settlements', 'debts', 'debt_payments', 'customers', 'users', 'devices', 'nozzles', 'tanks'];
$fingerBeforeClear = fingerprint(LIVE);
$salesNotCleared = DB::table('sales')->where(function ($q) use ($upTo) { $q->whereNull('sale_date')->orWhere('sale_date', '>', $upTo); })->count();
echo '  (to be cleared: ' . json_encode($preview) . ")\n";
ok(array_sum($preview) > 0, 'there is something to clear');

sleep(1); // dump files are named by the second
$backup = BackupRun::run($upTo, 1);
ok($backup && is_file(BackupRun::path($backup->file)), 'the export zip exists: ' . $backup->file . ' (' . round(filesize(BackupRun::path($backup->file)) / 1024) . ' KB)');
ok($backup->cleared === $preview || json_decode(json_encode($backup->cleared), true) === $preview, 'what was cleared is what the preview said');
ok(DB::table('sales')->where('sale_date', '<=', $upTo)->count() === 0 && DB::table('supplements')->where('supply_date', '<=', $upTo)->count() === 0
    && DB::table('remainders')->where('remainder_date', '<=', $upTo)->count() === 0, 'no sale, supplement or remainder up to ' . $upTo . ' is left');
ok(DB::table('sales')->count() === $salesNotCleared, 'sales without a date or after it are all still there (' . $salesNotCleared . ')');
$fingerAfter = fingerprint(LIVE);
ok(differences($fingerBeforeClear, $fingerAfter, ['sales', 'supplements', 'remainders', 'tickets', 'fuel_inbounds', 'backups']) === [], 'every other table is untouched'
    . (differences($fingerBeforeClear, $fingerAfter, ['sales', 'supplements', 'remainders', 'tickets', 'fuel_inbounds', 'backups']) ? ' (changed: ' . implode(', ', differences($fingerBeforeClear, $fingerAfter, ['sales', 'supplements', 'remainders', 'tickets', 'fuel_inbounds', 'backups'])) . ')' : ''));
foreach ($kept as $t) {
    if (isset($fingerBeforeClear[$t])) {
        $same = $fingerBeforeClear[$t] === $fingerAfter[$t];
        if (!$same) {
            ok(false, "$t was changed by the backup");
        }
    }
}
$tanksAfter = TankLevels::all()->mapWithKeys(function ($r) { return [$r->tank->id => round($r->current, 2)]; })->all();
ok($tanksBefore === $tanksAfter, 'the tank stock is the same before and after (' . json_encode($tanksAfter) . ')');
ok(Backup::count() === 1 && substr((string) Backup::first()->up_to_date, 0, 10) === $upTo, 'the backup is recorded with its date');

// the dump taken inside the run, before anything was cleared, brings the live server back as it was
$dumps = glob(storage_path('app/' . $dumpDir) . '/*.sql.gz');
usort($dumps, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
ok(count($dumps) === 3, 'the run took its safety dump first (' . count($dumps) . ' dumps now)');
load($dumps[0], RESTORE);
useDb(RESTORE);
$fingerSafety = fingerprint(RESTORE);
useDb(LIVE);
ok(differences($fingerBeforeClear, $fingerSafety) === [], 'restoring the safety dump gives back the live server as it was before the clearing');

// ------------------------------------------------------------------------------------------------------------------------------------------
section('the offline backup server');
ok(Artisan::call('backup:import', ['file' => BackupRun::path($backup->file)]) === 1, 'a live server refuses to import (it would bring cleared rows back)');
recreate(BACKUP);
useDb(BACKUP);
Artisan::call('migrate', ['--force' => true]);
config(['backup.is_backup_server' => true]);
ok(Artisan::call('backup:import', ['file' => BackupRun::path($backup->file)]) === 0, 'the backup server imports the export');
$zip = new ZipArchive;
$zip->open(BackupRun::path($backup->file));
$manifest = json_decode($zip->getFromName('manifest.json'), true);
$zip->close();
$mismatch = [];
foreach ($manifest['rows'] as $table => $n) {
    if ((int) DB::table($table)->count() !== (int) $n) {
        $mismatch[] = "$table (" . DB::table($table)->count() . " vs $n)";
    }
}
ok($mismatch === [], 'every table of the backup server holds the rows of the export (' . count($manifest['rows']) . ' tables)' . ($mismatch ? ' differ: ' . implode(', ', $mismatch) : ''));
$expectedSales = DB::connection()->getPdo()->query('SELECT COUNT(*) FROM `' . RESTORE . '`.sales WHERE deleted_at IS NULL')->fetchColumn();
ok((int) DB::table('sales')->count() === (int) $expectedSales, "the backup server holds all $expectedSales sales that the live server held, cleared ones included");
$sumBackup = (float) DB::table('sales')->sum('total_amount');
$sumLive = (float) DB::connection()->getPdo()->query('SELECT COALESCE(SUM(total_amount),0) FROM `' . RESTORE . '`.sales WHERE deleted_at IS NULL')->fetchColumn();
ok(abs($sumBackup - $sumLive) < 0.005, "and their amounts add up to the same ($sumBackup)");
$before = fingerprint(BACKUP);
Artisan::call('backup:import', ['file' => BackupRun::path($backup->file)]);
ok(differences($before, fingerprint(BACKUP)) === [], 'importing the same export again changes nothing');

// a second day: the live server clears more, the backup server keeps adding up
useDb(LIVE);
config(['backup.is_backup_server' => false]);
$second = DB::table('sales')->whereNotNull('sale_date')->min('sale_date');
if ($second) {
    $backup2 = BackupRun::run($upTo, 1);
    ok($backup2 !== null, 'a second backup run on the same date works (nothing new to clear)');
}

echo "\n" . ($checks - $failures) . " of $checks checks passed\n";
foreach ([LIVE, RESTORE, BACKUP] as $name) {
    // the scratch databases are kept for a look; the next run makes them new
}
exit($failures > 0 ? 1 : 0);
