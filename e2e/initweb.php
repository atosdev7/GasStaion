<?php

// The first configuration of a fresh web app (TEST_PLAN.md, part 1.2), done like a person at the screens: it signs in as admin and fills in the Fuel Types,
// Tanks and POS Devices pages (and, with the flags, the Users and Customers pages) with the same forms, one after the other. The database is only read, to
// check the result and to skip what is already there.
//
//   php e2e\initweb.php [base url] [--user=admin] [--password=admin123] [--staff] [--customers]
//
//   base url    the web app, default http://dev.rrdgasstaion.com (this PC)
//   --staff     also the five staff users (manager1, acc1, asst1, cash1, cash2; password secret1)
//   --customers also the credit customers (Acme Transport, Blue Taxi, North Farm)
//
// What it enters (the numbers of the test plan):
//   fuel types   A Gasoline price 820, density 0.7400;  D Diesel price 750, density 0.8300
//   tanks        T1 Gasoline, capacity 30,000 kg;  T2 Diesel, capacity 40,000 kg
//   POS          POS1 at 127.0.0.1 (the address the terminal on this PC calls from), PIN 4821, purge days 30
// Nozzles are not entered on the web: the terminal's Machine Config adds them and uploads them.

require __DIR__ . '/WebClient.php';

$base = rtrim(isset($argv[1]) && strpos($argv[1], '--') !== 0 ? $argv[1] : 'http://dev.rrdgasstaion.com', '/');
$user = 'admin';
$password = 'admin123';
$staff = false;
$customers = false;
foreach ($argv as $a) {
    if (strpos($a, '--user=') === 0) {
        $user = substr($a, 7);
    } elseif (strpos($a, '--password=') === 0) {
        $password = substr($a, 11);
    } elseif ($a === '--staff') {
        $staff = true;
    } elseif ($a === '--customers') {
        $customers = true;
    }
}

$env = [];
foreach (file(__DIR__ . '/../web/.env') as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = trim($m[2], "\"'");
    }
}
$pdo = new PDO('mysql:host=' . $env['DB_HOST'] . ';port=' . $env['DB_PORT'] . ';dbname=' . $env['DB_DATABASE'], $env['DB_USERNAME'], isset($env['DB_PASSWORD']) ? $env['DB_PASSWORD'] : '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function one($sql, array $args = [])
{
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $r = $st->fetch(PDO::FETCH_NUM);

    return $r ? $r[0] : null;
}

$checks = 0;
$fails = 0;
function ok($cond, $text)
{
    global $checks, $fails;
    ++$checks;
    if (!$cond) {
        ++$fails;
    }
    echo ($cond ? '  ok    ' : '  FAIL  ') . $text . "\n";
}

$web = new Web($base);
if (!$web->login($user, $password)) {
    fwrite(STDERR, "Cannot sign in as $user at $base.\n");
    exit(2);
}
echo "signed in as $user at $base\n";

// opens the list page, then the form, then sends it; a page that was not found or a form that was refused is a failure
function enter($web, $list, $fields, $label, $existsSql, $existsArgs)
{
    if ((int) one($existsSql, $existsArgs) > 0) {
        echo "  --    $label is already there\n";

        return;
    }
    $web->get($list);
    $web->get($list . '/create');
    $r = $web->send('POST', $list, $fields);
    ok($r['status'] === 302 && $r['location'] === $list, $label . ($r['status'] === 302 && $r['location'] === $list ? '' : ' (' . $r['status'] . ' ' . $r['location'] . ')'));
}

echo "\nfuel types\n";
enter($web, '/fuel', ['fuel_type' => 'A', 'price' => '820', 'density' => '0.7400'], 'Gasoline (A): price 820, density 0.7400', 'SELECT COUNT(*) FROM fuel_types WHERE fuel_type = ?', ['A']);
enter($web, '/fuel', ['fuel_type' => 'D', 'price' => '750', 'density' => '0.8300'], 'Diesel (D): price 750, density 0.8300', 'SELECT COUNT(*) FROM fuel_types WHERE fuel_type = ?', ['D']);

echo "\ntanks\n";
enter($web, '/tanks', ['name' => 'T1', 'fuel_type' => 'A', 'capacity_kg' => '30000'], 'T1 Gasoline, capacity 30,000 kg', 'SELECT COUNT(*) FROM tanks WHERE name = ? AND deleted_at IS NULL', ['T1']);
enter($web, '/tanks', ['name' => 'T2', 'fuel_type' => 'D', 'capacity_kg' => '40000'], 'T2 Diesel, capacity 40,000 kg', 'SELECT COUNT(*) FROM tanks WHERE name = ? AND deleted_at IS NULL', ['T2']);

echo "\nPOS devices\n";
enter($web, '/devices', ['name' => 'POS1', 'ip_address' => '127.0.0.1', 'purge_days' => '30', 'pin' => '4821'], 'POS1 at 127.0.0.1, PIN 4821, purge days 30', 'SELECT COUNT(*) FROM devices WHERE ip_address = ? AND deleted_at IS NULL', ['127.0.0.1']);

if ($staff) {
    echo "\nstaff\n";
    $people = [
        ['Manager One', 'manager1', 'manager'], ['Accountant One', 'acc1', 'accountant'], ['Assistant One', 'asst1', 'assistant'],
        ['Cashier One', 'cash1', 'cashier'], ['Cashier Two', 'cash2', 'cashier'],
    ];
    foreach ($people as $p) {
        enter($web, '/users', ['name' => $p[0], 'username' => $p[1], 'role' => $p[2], 'password' => 'secret1'], "$p[1] ($p[2])", 'SELECT COUNT(*) FROM users WHERE username = ?', [$p[1]]);
    }
}
if ($customers) {
    echo "\ncustomers\n";
    foreach ([['Acme Transport', '0100000001'], ['Blue Taxi', '0100000002'], ['North Farm', '0100000003']] as $c) {
        enter($web, '/customers', ['name' => $c[0], 'phone' => $c[1]], $c[0], 'SELECT COUNT(*) FROM customers WHERE name = ?', [$c[0]]);
    }
}

echo "\nchecks\n";
ok((int) one('SELECT COUNT(*) FROM fuel_types') === 2, 'two fuel types');
ok(abs((float) one('SELECT price FROM fuel_types WHERE fuel_type = "A"') - 820) < 0.001 && abs((float) one('SELECT density FROM fuel_types WHERE fuel_type = "D"') - 0.83) < 0.00001, 'the prices and densities are what was typed');
ok((int) one('SELECT COUNT(*) FROM tanks WHERE deleted_at IS NULL') === 2, 'two tanks');
ok((float) one('SELECT capacity_kg FROM tanks WHERE name = "T2"') == 40000, 'T2 holds 40,000 kg');
ok((int) one('SELECT COUNT(*) FROM devices WHERE deleted_at IS NULL') === 1, 'one POS device');
$ping = @file_get_contents($base . '/api/ping');
$data = $ping ? json_decode($ping, true) : null;
ok(is_array($data) && !empty($data['registered']) && $data['device_name'] === 'POS1', 'a call from this PC to /api/ping is now a registered device (POS1)');
$config = @file_get_contents($base . '/api/pull/config');
$cfg = $config ? json_decode($config, true) : null;
ok(is_array($cfg) && isset($cfg['fuel_types']) && count($cfg['fuel_types']) === 2 && isset($cfg['tanks']) && count($cfg['tanks']) === 2, 'the config the terminal downloads has the two fuel types and the two tanks');

echo "\n$checks checks, $fails failed\n";
exit($fails ? 1 : 0);
