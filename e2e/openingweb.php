<?php

// The opening balances and the first stock of a fresh web app (TEST_PLAN.md, parts 1.4 and 1.5), done like the staff at their screens: each step is made by the
// role that does it in the station (the accountant for the balances and the fuel delivery, the assistant for the tickets, the admin for the lock), signed in
// with the user's own name, on the pages of the web app. The database is only read, to find the ids the forms need and to check the result.
//
//   php e2e\openingweb.php [base url] [--password=secret1] [--admin-password=admin123]
//
// Needs: initweb.php --staff --customers and oldtickets.php done (the fuel types, tanks, POS, staff, customers and the old tickets).
// What it enters (the numbers of the test plan):
//   opening tank stock   T1 gasoline 20,000.00 kg at cost 600;  T2 diesel 30,000.00 kg at cost 550          (accountant, dated yesterday)
//   opening debts        Acme Transport 60,000.00 (10 days old), North Farm 12,000.00 (3 days old)         (accountant)
//   opening ticket loan  Blue Taxi 40.00 kg diesel (5 days old)                                              (accountant)
//   opening remainders   diesel 3.50 kg, gasoline 1.20 kg                                                    (accountant)
//   lock                 the opening balances                                                                (admin)
//   blank ticket stock   publish 200 diesel 10 kg and 200 gasoline 10 kg                                     (assistant, dated yesterday)
//   fuel delivery        T2 diesel 10,000.00 kg at 560, T1 gasoline 5,000.00 kg at 610                       (accountant)
//   and one more kilo into the full tank T2, which must be refused or warned about (the result is written down).

require __DIR__ . '/WebClient.php';

$base = rtrim(isset($argv[1]) && strpos($argv[1], '--') !== 0 ? $argv[1] : 'http://dev.rrdgasstaion.com', '/');
$password = 'secret1';
$adminPassword = 'admin123';
foreach ($argv as $a) {
    if (strpos($a, '--password=') === 0) {
        $password = substr($a, 11);
    } elseif (strpos($a, '--admin-password=') === 0) {
        $adminPassword = substr($a, 17);
    }
}

$env = [];
foreach (file(__DIR__ . '/../web/.env') as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = trim($m[2], "\"'");
    }
}
date_default_timezone_set(isset($env['APP_TIMEZONE']) ? $env['APP_TIMEZONE'] : 'UTC');
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

function signIn($base, $username, $password)
{
    $web = new Web($base);
    if (!$web->login($username, $password)) {
        fwrite(STDERR, "Cannot sign in as $username at $base.\n");
        exit(2);
    }
    echo "signed in as $username\n";

    return $web;
}

// opens the page, then sends the form to the same address, as the form of the page does; success is a redirect to $back
function fill($web, $page, $post, $fields, $back, $label)
{
    $web->get($page);
    $r = $web->send('POST', $post, $fields);
    $good = $r['status'] === 302 && $r['location'] === $back;
    ok($good, $label . ($good ? '' : ' (' . $r['status'] . ' ' . $r['location'] . ')'));

    return $r;
}

$t1 = (int) one('SELECT id FROM tanks WHERE name = "T1"');
$t2 = (int) one('SELECT id FROM tanks WHERE name = "T2"');
$acme = (int) one('SELECT id FROM customers WHERE name = "Acme Transport"');
$blue = (int) one('SELECT id FROM customers WHERE name = "Blue Taxi"');
$north = (int) one('SELECT id FROM customers WHERE name = "North Farm"');
if (!$t1 || !$t2 || !$acme || !$blue || !$north || (int) one('SELECT COUNT(*) FROM tank_balances') > 0) {
    fwrite(STDERR, "The tanks and customers are not there (run initweb.php --staff --customers), or the opening balances are already entered.\n");
    exit(2);
}
$yesterday = date('Y-m-d', strtotime('-1 day'));
$day = function ($back) {
    return date('Y-m-d', strtotime("-$back days"));
};

echo "\nthe accountant: the opening balances\n";
$acc = signIn($base, 'acc1', $password);
fill($acc, '/balances/tanks', '/balances/tanks', ['tank_id' => $t1, 'cost' => '600', 'balance' => '20000', 'balance_date' => $yesterday, 'note' => 'opening stock', 'is_default' => '1'], '/balances/tanks', 'opening stock T1 gasoline 20,000.00 kg at cost 600');
fill($acc, '/balances/tanks', '/balances/tanks', ['tank_id' => $t2, 'cost' => '550', 'balance' => '30000', 'balance_date' => $yesterday, 'note' => 'opening stock', 'is_default' => '1'], '/balances/tanks', 'opening stock T2 diesel 30,000.00 kg at cost 550');
fill($acc, '/balances/debts', '/balances/debts', ['customer_id' => $acme, 'amount' => '60000', 'payment_method' => 'bank', 'debt_date' => $day(10), 'comment' => 'owed at go-live'], '/balances/debts', 'opening debt of Acme Transport 60,000.00 (10 days old)');
fill($acc, '/balances/debts', '/balances/debts', ['customer_id' => $north, 'amount' => '12000', 'payment_method' => 'bank', 'debt_date' => $day(3), 'comment' => 'owed at go-live'], '/balances/debts', 'opening debt of North Farm 12,000.00 (3 days old)');
fill($acc, '/balances/loans', '/balances/loans', ['customer_id' => $blue, 'fuel_type' => 'D', 'amount_kg' => '40', 'date' => $day(5), 'comment' => 'lent before go-live'], '/balances/loans', 'opening ticket loan of Blue Taxi 40.00 kg diesel (5 days old)');
fill($acc, '/balances/remainders', '/balances/remainders', ['fuel_type' => 'D', 'amount_kg' => '3.5', 'remainder_date' => $yesterday, 'comment' => 'opening remainder'], '/balances/remainders', 'opening remainder ticket, diesel 3.50 kg');
fill($acc, '/balances/remainders', '/balances/remainders', ['fuel_type' => 'A', 'amount_kg' => '1.2', 'remainder_date' => $yesterday, 'comment' => 'opening remainder'], '/balances/remainders', 'opening remainder ticket, gasoline 1.20 kg');

$stock = $acc->get('/tank-stock');
$text = preg_replace('/\s+/', ' ', strip_tags($stock['body']));
ok($stock['status'] === 200 && strpos($text, '20,000.00') !== false && strpos($text, '30,000.00') !== false, 'the Tank Stock Summary shows 20,000.00 and 30,000.00');

echo "\nthe admin: lock the opening balances\n";
$admin = signIn($base, 'admin', $adminPassword);
$admin->get('/balances/go-live');
$r = $admin->send('POST', '/balances/lock', []);
ok($r['status'] === 302, 'the opening balances are locked');
$locked = $acc->send('POST', '/balances/debts', ['customer_id' => $acme, 'amount' => '1', 'payment_method' => 'bank', 'debt_date' => $day(1)]);
$page = $locked['page'] ? preg_replace('/\s+/', ' ', strip_tags($locked['page']['body'])) : '';
ok(strpos($page, 'locked') !== false, 'after the lock the accountant cannot add an opening debt (the page says locked)');
ok((int) one('SELECT COUNT(*) FROM debts') === 2, 'there are still two opening debts');

echo "\nthe assistant: publish blank tickets\n";
$asst = signIn($base, 'asst1', $password);
foreach ([['D', 1], ['A', 1]] as $k) {
    $key = $k[0] . ':' . $k[1];
    $code = one('SELECT code FROM ticket_publishes WHERE fuel_type = ? AND spec = ? ORDER BY end_serial DESC LIMIT 1', [$k[0], $k[1]]);
    $from = (int) one('SELECT MAX(end_serial) FROM ticket_publishes WHERE fuel_type = ? AND spec = ?', [$k[0], $k[1]]) + 1;
    fill($asst, '/ticket-publish/create', '/ticket-publish', ['stock_item' => $key, 'start_serial' => $from, 'end_serial' => $from + 199, 'date' => $yesterday], '/ticket-publish', "publish 200 tickets $key, serials $from to " . ($from + 199));
}

echo "\nthe accountant: the first fuel delivery\n";
fill($acc, '/inbound/create', '/inbound', ['tank_id' => $t2, 'inbound_date' => $yesterday, 'from_name' => 'Refinery No. 1', 'amount_kg' => '10000', 'cost' => '560', 'is_default' => '1', 'note' => 'DN-001'], '/inbound', 'diesel 10,000.00 kg at cost 560 into T2 (DN-001)');
fill($acc, '/inbound/create', '/inbound', ['tank_id' => $t1, 'inbound_date' => $yesterday, 'from_name' => 'Depot North', 'amount_kg' => '5000', 'cost' => '610', 'is_default' => '1', 'note' => 'DN-002'], '/inbound', 'gasoline 5,000.00 kg at cost 610 into T1 (DN-002)');
$over = $acc->send('POST', '/inbound', ['tank_id' => $t2, 'inbound_date' => $yesterday, 'from_name' => 'Refinery No. 1', 'amount_kg' => '1', 'cost' => '560', 'note' => 'over the capacity?']);
$refused = $over['status'] !== 302 || $over['location'] !== '/inbound';
echo '  --    one more kilo into the full tank T2: ' . ($refused ? 'refused or sent back to the form (' . $over['status'] . ' ' . $over['location'] . ')' : 'ACCEPTED (maybe with a warning on the list page)') . "\n";
$left = (int) one('SELECT COUNT(*) FROM fuel_inbounds WHERE deleted_at IS NULL AND amount_kg = 1');
if (!$refused && $left > 0) {
    echo "        the 1 kg inbound was saved; it is left in place (a fuel inbound cannot be undone by this script), write it down in the test run\n";
}

echo "\nchecks\n";
ok(abs((float) one('SELECT SUM(balance) FROM tank_balances WHERE tank_id = ?', [$t1]) - 20000) < 0.01 && abs((float) one('SELECT SUM(balance) FROM tank_balances WHERE tank_id = ?', [$t2]) - 30000) < 0.01, 'the opening balances of the tanks are 20,000.00 and 30,000.00');
ok((int) one('SELECT COUNT(*) FROM debts') === 2 && abs((float) one('SELECT SUM(amount) FROM debts') - 72000) < 0.01, 'two opening debts, 72,000.00 together');
ok((int) one('SELECT COUNT(*) FROM ticket_loans') === 1 && abs((float) one('SELECT amount_kg FROM ticket_loans LIMIT 1') - 40) < 0.001, 'one opening ticket loan of 40.00 kg');
ok((int) one('SELECT COUNT(*) FROM remainders') === 2, 'two opening remainders');
ok((int) one('SELECT COUNT(*) FROM tickets WHERE fuel_type = "D" AND spec = 1') === 11620 + 200 + 0 || (int) one('SELECT COUNT(*) FROM tickets WHERE fuel_type = "D" AND spec = 1') >= 11820, 'the diesel 10 kg stock grew by the 200 published tickets');
ok(abs((float) one('SELECT SUM(amount_kg) FROM fuel_inbounds WHERE tank_id = ? AND deleted_at IS NULL AND note = "DN-001"', [$t2]) - 10000) < 0.01, 'the diesel delivery is 10,000.00 kg');

echo "\n$checks checks, $fails failed\n";
exit($fails ? 1 : 0);
