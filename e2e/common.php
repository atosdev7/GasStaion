<?php

// Shared by prepare.php and verify.php. It boots the web app against the SCRATCH database below and refuses to go on if the app ended up
// on any other database (the dev database `gasstation` is never touched by the end-to-end tests).

const E2E_DB = 'gasstation_e2e';

putenv('DB_DATABASE=' . E2E_DB);
$_ENV['DB_DATABASE'] = E2E_DB;
$_SERVER['DB_DATABASE'] = E2E_DB;

require __DIR__ . '/../web/vendor/autoload.php';

$app = require __DIR__ . '/../web/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// an uncaught error must fail the run (Laravel's handler would print it and leave the exit code at 0)
set_exception_handler(function ($e) {
    fwrite(STDERR, $e . "
");
    exit(2);
});

if (config('database.connections.mysql.database') !== E2E_DB) {
    fwrite(STDERR, "ABORT: the app is on database '" . config('database.connections.mysql.database') . "', not " . E2E_DB . "\n");
    exit(2);
}

function e2e_pdo()
{
    $c = config('database.connections.mysql');

    return new PDO('mysql:host=' . $c['host'] . ';port=' . $c['port'], $c['username'], $c['password']);
}

// The dev database, for the "untouched" check: row counts of a few tables.
function e2e_dev_fingerprint()
{
    $pdo = e2e_pdo();
    $out = [];
    foreach (['sales', 'supplements', 'tickets', 'ticket_loans', 'devices', 'sync_errors'] as $table) {
        try {
            $out[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `gasstation`.`' . $table . '`')->fetchColumn();
        } catch (Exception $e) {
            $out[$table] = -1;
        }
    }

    return $out;
}
