<?php

// A step of the "restore" scenario, called by the terminal program while the web app keeps running on the scratch database gasstation_e2e:
//   php restore_step.php dump      writes a dump (as backup:dump does) and prints its time
//   php restore_step.php restore   replaces the database by that dump (the server "dies" and comes back from its backup), then runs
//                                  backup:restored for the dump's time, as the admin does; prints what the restored database holds
// Never touches any other database (common.php).

require __DIR__ . '/common.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$action = $argv[1] ?? '';
config(['backup.dump_dir' => 'e2e/restore', 'backup.is_backup_server' => false]);
$dir = storage_path('app/e2e/restore');

if ($action === 'dump') {
    foreach (glob($dir . '/*') ?: [] as $f) {
        unlink($f);
    }
    if (Artisan::call('backup:dump', ['--note' => 'e2e restore']) !== 0) {
        fwrite(STDERR, Artisan::output());
        exit(1);
    }
    $files = glob($dir . '/*.sql.gz');
    echo 'dump_time=' . date('Y-m-d H:i:s', filemtime($files[0])) . "\n";
    echo 'sales=' . DB::table('sales')->count() . "\n";
    exit(0);
}

if ($action === 'restore') {
    $files = glob($dir . '/*.sql.gz');
    if (!$files) {
        fwrite(STDERR, "no dump\n");
        exit(1);
    }
    $since = date('Y-m-d H:i:s', filemtime($files[0]));
    $c = config('database.connections.mysql');

    $pdo = new PDO('mysql:host=' . $c['host'] . ';port=' . $c['port'], $c['username'], $c['password']);
    $pdo->exec('DROP DATABASE IF EXISTS `' . E2E_DB . '`');
    $pdo->exec('CREATE DATABASE `' . E2E_DB . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $tmp = tempnam(sys_get_temp_dir(), 'rs') . '.sql';
    file_put_contents($tmp, gzdecode(file_get_contents($files[0])));
    $mysql = dirname(config('backup.mysqldump')) . DIRECTORY_SEPARATOR . 'mysql.exe';
    $p = new Process([$mysql, '--host=' . $c['host'], '--port=' . $c['port'], '--user=' . $c['username'], '--default-character-set=utf8mb4', E2E_DB,
        '-e', 'source ' . str_replace('\\', '/', $tmp)], null, ['MYSQL_PWD' => (string) $c['password']]);
    $p->setTimeout(600)->run();
    unlink($tmp);
    if (!$p->isSuccessful()) {
        fwrite(STDERR, $p->getErrorOutput());
        exit(1);
    }

    DB::purge('mysql');
    if (Artisan::call('backup:restored', ['--since' => $since]) !== 0) {
        fwrite(STDERR, Artisan::output());
        exit(1);
    }
    echo 'restored_since=' . $since . "\n";
    echo 'sales=' . DB::table('sales')->count() . "\n";
    exit(0);
}

fwrite(STDERR, "usage: restore_step.php dump|restore\n");
exit(2);
