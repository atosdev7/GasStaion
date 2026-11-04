<?php

// The old tickets of the station: the HISTORY of the blank tickets received (Ticket Management > Blank ticket inbound) and of the tickets published from
// them, all dated before go-live, about 50,000 tickets of 8 kinds (gasoline A and diesel D, 5, 10, 15 and 50 kg). Used to fill a fresh web app before the
// test plan (TEST_PLAN.md, part 1.4). Everything goes through the web app's own pages with a real login (the rules, the audit log, the ticket numbers), the
// database is only read.
//
//   php e2e\oldtickets.php [base url] [--user=admin] [--password=admin123] [--end=2026-10-31] [--dry-run]
//
//   base url   the running web app, default http://127.0.0.1:8123 (the gasstation-web launch configuration)
//   --end      the last day of the history (default: 5 days ago); the history covers the 12 months before it. Set the go-live date AFTER it.
//   --dry-run  only print the plan (the batches), send nothing
//
// It refuses to run when the database already has ticket inbounds or tickets (a second run would number the serials after the first one and double the
// stock). The numbers are fixed (no random), so a second fresh install gets the same history.

require __DIR__ . '/WebClient.php';

$base = rtrim(isset($argv[1]) && strpos($argv[1], '--') !== 0 ? $argv[1] : 'http://127.0.0.1:8123', '/');
$user = 'admin';
$password = 'admin123';
$endDate = null;
$dry = false;
foreach ($argv as $a) {
    if (strpos($a, '--user=') === 0) {
        $user = substr($a, 7);
    } elseif (strpos($a, '--password=') === 0) {
        $password = substr($a, 11);
    } elseif (strpos($a, '--end=') === 0) {
        $endDate = substr($a, 6);
    } elseif ($a === '--dry-run') {
        $dry = true;
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

$end = new DateTime($endDate ?: 'today -5 days');
$start = (clone $end)->modify('-364 days');

// kind => [fuel, spec, total received, number of batches, printing cost of one ticket]
$kinds = [
    ['D', 0, 8000, 6, '0.05'], ['D', 1, 12000, 8, '0.06'], ['D', 2, 4000, 4, '0.07'], ['D', 3, 1000, 2, '0.12'],
    ['A', 0, 6000, 6, '0.05'], ['A', 1, 14000, 8, '0.06'], ['A', 2, 4000, 4, '0.07'], ['A', 3, 1000, 2, '0.12'],
];
$houses = ['Red Print House', 'City Press', 'Northern Print'];

// the plan: the batches of each kind, spread over the year, the kinds shifted by a day each so that no two kinds are received on the same day
$plan = [];
foreach ($kinds as $t => $k) {
    list($fuel, $spec, $total, $count, $cost) = $k;
    $each = (int) (floor($total / $count / 100) * 100);
    $left = $total;
    for ($i = 0; $i < $count; ++$i) {
        $qty = $i === $count - 1 ? $left : $each;
        $left -= $qty;
        $day = (int) floor((($i + 0.5) / $count) * 355) + $t;
        $received = (clone $start)->modify("+$day days");
        $latest = (clone $end)->modify('-3 days');
        if ($received > $latest) {
            $received = $latest;
        }
        $isLast = $i === $count - 1;
        $publishQty = $isLast ? (int) (floor($qty * 0.75 / 10) * 10) : $qty; // the newest batch of each kind is only partly published: the rest is blank stock
        $plan[] = [
            'fuel' => $fuel, 'spec' => $spec, 'qty' => $qty, 'received' => $received->format('Y-m-d'), 'cost' => $cost,
            'note' => $houses[($t + $i) % 3] . ', order PO-' . $received->format('Y') . '-' . sprintf('%03d', count($plan) + 1),
            'publish_qty' => $publishQty, 'published' => (clone $received)->modify('+2 days')->format('Y-m-d'),
        ];
    }
}
usort($plan, function ($a, $b) {
    return strcmp($a['received'], $b['received']) ?: strcmp($a['fuel'] . $a['spec'], $b['fuel'] . $b['spec']);
});

$received = 0;
$published = 0;
foreach ($plan as $p) {
    $received += $p['qty'];
    $published += $p['publish_qty'];
}
echo 'history ' . $start->format('Y-m-d') . ' to ' . $end->format('Y-m-d') . ': ' . count($plan) . " inbounds, $received blank tickets received, $published published, " . ($received - $published) . " left blank\n";
if ($dry) {
    foreach ($plan as $n => $p) {
        printf("  %2d  %s  %s:%d  %5d received, %5d published on %s  (%s)\n", $n + 1, $p['received'], $p['fuel'], $p['spec'], $p['qty'], $p['publish_qty'], $p['published'], $p['note']);
    }
    exit(0);
}

if ((int) one('SELECT COUNT(*) FROM ticket_inbounds') > 0 || (int) one('SELECT COUNT(*) FROM tickets') > 0) {
    fwrite(STDERR, "The database already has ticket inbounds or tickets: nothing was done (start from a fresh install).\n");
    exit(2);
}

$web = new Web($base);
if (!$web->login($user, $password)) {
    fwrite(STDERR, "Cannot sign in as $user at $base.\n");
    exit(2);
}

$nextSerial = [];
foreach ($plan as $n => $p) {
    $key = $p['fuel'] . ':' . $p['spec'];
    $r = $web->send('POST', '/ticket-inbound', ['fuel_type' => $p['fuel'], 'spec' => $p['spec'], 'quantity' => $p['qty'], 'inbound_date' => $p['received'], 'cost' => $p['cost'], 'note' => $p['note']]);
    ok($r['status'] === 302 && $r['location'] === '/ticket-inbound', sprintf('inbound %2d: %s %s %d blank tickets of %s', $n + 1, $p['received'], $key, $p['qty'], $p['note']));

    if ($p['publish_qty'] > 0) {
        $fromSerial = isset($nextSerial[$key]) ? $nextSerial[$key] : 1;
        $toSerial = $fromSerial + $p['publish_qty'] - 1;
        $r = $web->send('POST', '/ticket-publish', ['stock_item' => $key, 'start_serial' => $fromSerial, 'end_serial' => $toSerial, 'date' => $p['published']]);
        $good = $r['status'] === 302 && $r['location'] === '/ticket-publish';
        ok($good, sprintf('publish %2d: %s %s serials %d to %d', $n + 1, $p['published'], $key, $fromSerial, $toSerial));
        if ($good) {
            $nextSerial[$key] = $toSerial + 1;
        }
    }
}

echo "\nchecks\n";
ok((int) one('SELECT COALESCE(SUM(quantity), 0) FROM ticket_inbounds WHERE deleted_at IS NULL') === $received, "the inbounds add up to $received blank tickets");
ok((int) one('SELECT COUNT(*) FROM ticket_inbounds WHERE deleted_at IS NULL') === count($plan), count($plan) . ' inbound records');
ok((int) one('SELECT COALESCE(SUM(`count`), 0) FROM ticket_publishes WHERE deleted_at IS NULL') === $published, "the publishes add up to $published tickets");
ok((int) one('SELECT COUNT(*) FROM tickets') === $published, "$published ticket rows exist");
ok((int) one('SELECT COUNT(*) FROM tickets WHERE status <> 0') === 0, 'all of them are in the stock (nothing is sold or used yet)');
ok(one('SELECT MIN(inbound_date) FROM ticket_inbounds') >= $start->format('Y-m-d'), 'the first inbound is inside the year of the history');
ok(one('SELECT MAX(date) FROM ticket_publishes') <= $end->format('Y-m-d'), 'the last publish is not after ' . $end->format('Y-m-d'));
$kindsFound = (int) one('SELECT COUNT(DISTINCT CONCAT(fuel_type, ":", spec)) FROM tickets');
ok($kindsFound === 8, "$kindsFound kinds of tickets exist (fuel x denomination)");

echo "\n$checks checks, $fails failed\n";
exit($fails ? 1 : 0);
