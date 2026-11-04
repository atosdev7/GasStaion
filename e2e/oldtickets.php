<?php

// The old tickets of the station: the HISTORY of the blank tickets received (Ticket Management > Blank ticket inbound) and of the tickets published from
// them, all dated before go-live, over one year: about 50,000 tickets of 8 kinds (gasoline A and diesel D, 5, 10, 15 and 50 kg). Used to fill a fresh web
// app before the test plan (TEST_PLAN.md, part 1.3). It works like a person: it signs in as admin on the web app and fills in the Ticket Inbound, Ticket
// Publish and Go-live pages with the same forms, one after the other, in the order of the dates; the database is only read, to check the result.
//
//   php e2e\oldtickets.php [base url] [--user=admin] [--password=admin123] [--end=YYYY-MM-DD] [--golive=YYYY-MM-DD] [--dry-run]
//
//   base url   the web app, default http://dev.rrdgasstaion.com (this PC)
//   --end      the last day of the history (default: yesterday); the history covers the 365 days up to it
//   --golive   the go-live date to set when the history is in (default: today; "none" leaves it alone)
//   --dry-run  only print the plan (the batches), send nothing
//
// It refuses to run when the database already has ticket inbounds or tickets (a second run would number the serials after the first one and double the
// stock). The numbers are fixed (no random), so a second fresh install gets the same history.

require __DIR__ . '/WebClient.php';

$base = rtrim(isset($argv[1]) && strpos($argv[1], '--') !== 0 ? $argv[1] : 'http://dev.rrdgasstaion.com', '/');
$user = 'admin';
$password = 'admin123';
$endDate = null;
$goLive = 'today';
$dry = false;
foreach ($argv as $a) {
    if (strpos($a, '--user=') === 0) {
        $user = substr($a, 7);
    } elseif (strpos($a, '--password=') === 0) {
        $password = substr($a, 11);
    } elseif (strpos($a, '--end=') === 0) {
        $endDate = substr($a, 6);
    } elseif (strpos($a, '--golive=') === 0) {
        $goLive = substr($a, 9);
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

$end = new DateTime($endDate ?: 'yesterday');
$start = (clone $end)->modify('-364 days');

// kind => [fuel, spec, total received, number of deliveries, printing cost of one ticket]
$kinds = [
    ['D', 0, 8000, 6, '0.05'], ['D', 1, 12000, 8, '0.06'], ['D', 2, 4000, 4, '0.07'], ['D', 3, 1000, 2, '0.12'],
    ['A', 0, 6000, 6, '0.05'], ['A', 1, 14000, 8, '0.06'], ['A', 2, 4000, 4, '0.07'], ['A', 3, 1000, 2, '0.12'],
];
$houses = ['Red Print House', 'City Press', 'Northern Print'];

// 1. the deliveries: the batches of all kinds in one row, the kinds mixed by how far each is through its own batches, and the row spread evenly over the year
$batches = [];
foreach ($kinds as $t => $k) {
    list($fuel, $spec, $total, $count, $cost) = $k;
    $each = (int) (floor($total / $count / 100) * 100);
    $left = $total;
    for ($i = 0; $i < $count; ++$i) {
        $qty = $i === $count - 1 ? $left : $each;
        $left -= $qty;
        $batches[] = ['key' => ($i + 0.5) / $count + $t / 1000, 'fuel' => $fuel, 'spec' => $spec, 'qty' => $qty, 'cost' => $cost, 'last' => $i === $count - 1, 't' => $t, 'i' => $i];
    }
}
usort($batches, function ($a, $b) {
    return $a['key'] <=> $b['key'];
});

// 2. what happens on which day: the delivery, then its publishes (one to three, a few days apart); the newest delivery of each kind is only 75% published
$events = [];
$n = count($batches);
foreach ($batches as $idx => $b) {
    $day = (int) floor(($idx + 0.5) * 355 / $n) + 3;
    $received = (clone $start)->modify("+$day days");
    $note = $houses[($b['t'] + $b['i']) % 3] . ', order PO-' . $received->format('Y') . '-' . sprintf('%03d', $idx + 1);
    $events[] = ['date' => $received->format('Y-m-d'), 'order' => 0, 'kind' => 'inbound', 'fuel' => $b['fuel'], 'spec' => $b['spec'], 'qty' => $b['qty'], 'cost' => $b['cost'], 'note' => $note];

    $publishTotal = $b['last'] ? (int) (floor($b['qty'] * 0.75 / 10) * 10) : $b['qty'];
    $parts = $b['qty'] >= 1500 ? [0.40, 0.35, 0.25] : ($b['qty'] >= 1000 ? [0.60, 0.40] : [1.0]);
    $offsets = [1, 9, 21];
    $given = 0;
    foreach ($parts as $p => $share) {
        $q = $p === count($parts) - 1 ? $publishTotal - $given : (int) (floor($publishTotal * $share / 10) * 10);
        $given += $q;
        if ($q <= 0) {
            continue;
        }
        $when = (clone $received)->modify('+' . $offsets[$p] . ' days');
        if ($when > $end) {
            $when = clone $end;
        }
        $events[] = ['date' => $when->format('Y-m-d'), 'order' => 1, 'kind' => 'publish', 'fuel' => $b['fuel'], 'spec' => $b['spec'], 'qty' => $q];
    }
}
usort($events, function ($a, $b) {
    return strcmp($a['date'], $b['date']) ?: $a['order'] - $b['order'] ?: strcmp($a['fuel'] . $a['spec'], $b['fuel'] . $b['spec']);
});

$received = 0;
$published = 0;
$inbounds = 0;
$publishes = 0;
foreach ($events as $e) {
    if ($e['kind'] === 'inbound') {
        $received += $e['qty'];
        ++$inbounds;
    } else {
        $published += $e['qty'];
        ++$publishes;
    }
}
echo 'history ' . $start->format('Y-m-d') . ' to ' . $end->format('Y-m-d') . ": $inbounds inbounds ($received blank tickets), $publishes publishes ($published tickets), " . ($received - $published) . " left blank\n";
if ($dry) {
    foreach ($events as $n => $e) {
        printf("  %3d  %s  %-7s %s:%d  %5d%s\n", $n + 1, $e['date'], $e['kind'], $e['fuel'], $e['spec'], $e['qty'], isset($e['note']) ? '  (' . $e['note'] . ')' : '');
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
echo "signed in as $user at $base\n";

$nextSerial = [];
foreach ($events as $n => $e) {
    $key = $e['fuel'] . ':' . $e['spec'];
    if ($e['kind'] === 'inbound') {
        // the page is opened first, as a person would, then its form is sent
        $web->get('/ticket-inbound/create');
        $r = $web->send('POST', '/ticket-inbound', ['fuel_type' => $e['fuel'], 'spec' => $e['spec'], 'quantity' => $e['qty'], 'inbound_date' => $e['date'], 'cost' => $e['cost'], 'note' => $e['note']]);
        ok($r['status'] === 302 && $r['location'] === '/ticket-inbound', sprintf('%s inbound  %s %5d blank tickets (%s)', $e['date'], $key, $e['qty'], $e['note']));
    } else {
        $web->get('/ticket-publish/create');
        $from = isset($nextSerial[$key]) ? $nextSerial[$key] : 1;
        $to = $from + $e['qty'] - 1;
        $r = $web->send('POST', '/ticket-publish', ['stock_item' => $key, 'start_serial' => $from, 'end_serial' => $to, 'date' => $e['date']]);
        $good = $r['status'] === 302 && $r['location'] === '/ticket-publish';
        ok($good, sprintf('%s publish  %s serials %d to %d', $e['date'], $key, $from, $to));
        if ($good) {
            $nextSerial[$key] = $to + 1;
        }
    }
}

// the go-live date, on its own page
if ($goLive !== 'none') {
    $liveDate = $goLive === 'today' ? date('Y-m-d') : $goLive;
    $web->get('/balances/go-live');
    $r = $web->send('POST', '/balances/go-live', ['go_live_date' => $liveDate]);
    ok($r['status'] === 302, "go-live date set to $liveDate");
    $page = $web->get('/balances/go-live');
    $text = preg_replace('/\s+/', ' ', strip_tags($page['body']));
    ok(strpos($page['body'], $liveDate) !== false, 'the Go-live page shows the date');
    ok(preg_match('/' . $publishes . ' publishes are dated before go-live/', $text) === 1, "the Go-live page counts $publishes publishes before go-live");
}

echo "\nchecks\n";
ok((int) one('SELECT COALESCE(SUM(quantity), 0) FROM ticket_inbounds WHERE deleted_at IS NULL') === $received, "the inbounds add up to $received blank tickets");
ok((int) one('SELECT COUNT(*) FROM ticket_inbounds WHERE deleted_at IS NULL') === $inbounds, "$inbounds inbound records");
ok((int) one('SELECT COUNT(*) FROM ticket_publishes WHERE deleted_at IS NULL') === $publishes, "$publishes publish records");
ok((int) one('SELECT COALESCE(SUM(`count`), 0) FROM ticket_publishes WHERE deleted_at IS NULL') === $published, "the publishes add up to $published tickets");
ok((int) one('SELECT COUNT(*) FROM tickets') === $published, "$published ticket rows exist");
ok((int) one('SELECT COUNT(*) FROM tickets WHERE status <> 0') === 0, 'all of them are in the stock (nothing is sold or used yet)');
ok(one('SELECT MIN(inbound_date) FROM ticket_inbounds') >= $start->format('Y-m-d'), 'the first inbound is inside the year of the history');
ok(one('SELECT MAX(date) FROM ticket_publishes') <= $end->format('Y-m-d'), 'the last publish is not after ' . $end->format('Y-m-d'));
ok((int) one('SELECT COUNT(DISTINCT CONCAT(fuel_type, ":", spec)) FROM tickets') === 8, '8 kinds of tickets exist (fuel x denomination)');
$months = (int) one('SELECT COUNT(DISTINCT DATE_FORMAT(inbound_date, "%Y-%m")) FROM ticket_inbounds');
ok($months >= 12, "the deliveries are spread over $months calendar months");
$monthsPub = (int) one('SELECT COUNT(DISTINCT DATE_FORMAT(`date`, "%Y-%m")) FROM ticket_publishes');
ok($monthsPub >= 12, "the publishes are spread over $monthsPub calendar months");

echo "\n$checks checks, $fails failed\n";
exit($fails ? 1 : 0);
