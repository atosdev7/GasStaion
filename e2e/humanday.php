<?php

// The web staff's part of a business day on the DEV web app (http://dev.rrdgasstaion.com), done like people do it: real logins, the pages' own forms.
// The database is only read, to check what the pages did. The terminal's part is smart210/tests/guiday.cpp.
//
//   php e2e\humanday.php [base url] --part=day|morning [--date=YYYY-MM-DD] [--day=N]
//
//   day      the assistant sells tickets on credit to Acme Transport (a part payment at the sale), looks a ticket up and takes one back; the accountant records
//            a payment of that debt and a fuel outbound; the pages of the day open
//   morning  the next morning: the accountant confirms every record of the date (sales, supplements, payments, outbounds ...), the confirmation pages and
//            the summary open; the assistant and the cashier cannot confirm. --date defaults to the business day before the current one
//   --day    a number of the test week; it only varies the amounts a little (default 1)
//
// Lines "ok" / "FAIL" as in oldtickets.php; the exit code is 1 when a check failed.

require __DIR__ . '/WebClient.php';

$base = 'http://dev.rrdgasstaion.com';
$part = 'day';
$date = null;
$day = 1;
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--part=') === 0) {
        $part = substr($a, 7);
    } elseif (strpos($a, '--date=') === 0) {
        $date = substr($a, 7);
    } elseif (strpos($a, '--day=') === 0) {
        $day = max(1, (int) substr($a, 6));
    } elseif (strpos($a, '--') !== 0) {
        $base = rtrim($a, '/');
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

function rows($sql, array $args = [])
{
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($args);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function one($sql, array $args = [])
{
    $r = rows($sql, $args);

    return $r ? array_values($r[0])[0] : null;
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

    return $cond;
}

function section($t)
{
    echo "\n== $t\n";
}

function signIn($user)
{
    global $base;
    $w = new Web($base);
    ok($w->login($user), "$user signs in");

    return $w;
}

// the business day starts at 09:00 (backup.business_day_start_hour): before it "today" is still the day before
$businessToday = date('Y-m-d', time() - 9 * 3600);

function openPages(Web $w, array $paths)
{
    $bad = [];
    foreach ($paths as $p) {
        $r = $w->get($p);
        if ($r['status'] !== 200 || stripos($r['body'], 'Whoops') !== false) {
            $bad[] = $p . ' (' . $r['status'] . ')';
        }
    }

    return $bad;
}

if ($part === 'day') {
    $acme = (int) one("SELECT id FROM customers WHERE name = 'Acme Transport'");
    $tank = (int) one("SELECT id FROM tanks WHERE fuel_type = 'A' AND deleted_at IS NULL ORDER BY id LIMIT 1");
    $price = (float) one("SELECT price FROM fuel_types WHERE fuel_type = 'D'");

    section('the assistant sells tickets on credit to Acme Transport');
    $assistant = signIn('asst1');
    $qty = 8 + $day;                                                         // 10 kg diesel tickets
    $stock = (int) one("SELECT COUNT(*) FROM tickets WHERE fuel_type = 'D' AND spec = 1 AND status = 0 AND sale_id IS NULL");
    ok($stock >= $qty, "diesel 10 kg tickets in stock: $stock (need $qty)");
    $total = $qty * 10 * $price;
    $paid = round($total * 0.4, 2);
    $salesBefore = (int) one("SELECT COUNT(*) FROM sales WHERE customer_id = ? AND sale_type = 'ticket'", [$acme]);
    $r = $assistant->send('POST', '/ticket-sales', [
        'sale_type' => 'ticket', 'fuel_type' => 'D', 'lines' => [['spec' => 1, 'quantity' => $qty]], 'customer_id' => $acme, 'price' => $price,
        'usage' => 'sale', 'payment_method' => 'bank', 'paid_amount' => $paid, 'sale_date' => $businessToday,
    ]);
    ok($r['status'] === 302 && $r['location'] === '/ticket-sales', 'the sale is saved (redirect to the list)');
    $sale = rows("SELECT * FROM sales WHERE customer_id = ? AND sale_type = 'ticket' ORDER BY created_at DESC LIMIT 1", [$acme]);
    $sale = $sale ? $sale[0] : null;
    ok($sale && (int) one("SELECT COUNT(*) FROM sales WHERE customer_id = ? AND sale_type = 'ticket'", [$acme]) === $salesBefore + 1, 'one new ticket sale for Acme Transport');
    ok($sale && abs($sale['total_amount'] - $total) < 0.01 && abs($sale['unpaid_amount'] - ($total - $paid)) < 0.01,
        "$qty x 10 kg x " . number_format($price, 2) . ' = ' . number_format($total, 2) . ', unpaid ' . number_format($total - $paid, 2));
    $debt = $sale ? rows('SELECT * FROM debts WHERE sale_id = ?', [$sale['id']]) : [];
    ok($debt && abs($debt[0]['amount'] - ($total - $paid)) < 0.01, 'a debt of the unpaid part was made');
    $barcodes = $sale ? array_column(rows('SELECT barcode FROM tickets WHERE sale_id = ? ORDER BY serial', [$sale['id']]), 'barcode') : [];
    ok(count($barcodes) === $qty, "$qty tickets are sold to the sale");

    section('the assistant looks a ticket up and takes one back');
    if ($barcodes) {
        $l = $assistant->get('/tickets/lookup?barcode=' . $barcodes[0]);
        ok($l['status'] === 200 && strpos($l['body'], $barcodes[0]) !== false, 'the ticket lookup shows ' . $barcodes[0]);
        $totalBefore = (float) $sale['total_amount'];
        $r = $assistant->send('POST', '/ticket-sales/' . $sale['id'] . '/return', ['ticket_barcodes' => $barcodes[$qty - 1], 'comment' => 'one ticket too many']);
        ok($r['status'] === 302, 'the return is saved');
        $ret = rows('SELECT * FROM sales WHERE reverses_sale_id = ?', [$sale['id']]);
        ok($ret && abs($ret[0]['amount_kg'] + 10) < 0.001 && abs($ret[0]['total_amount'] + 10 * $price) < 0.01, 'a counter entry of -10 kg / -' . number_format(10 * $price, 2) . ' was made');
        ok((int) one('SELECT status FROM tickets WHERE barcode = ?', [$barcodes[$qty - 1]]) === 0 && one('SELECT sale_id FROM tickets WHERE barcode = ?', [$barcodes[$qty - 1]]) === null, 'the ticket is back in stock');
        ok(abs((float) one('SELECT total_amount FROM sales WHERE id = ?', [$sale['id']]) - $totalBefore) < 0.01, 'the original sale keeps its figures');
    }
    $cs = $assistant->get('/confirmations');
    ok($cs['status'] === 403, 'the assistant has no access to the daily confirmation (HTTP ' . $cs['status'] . ')');

    section('the accountant records a payment of the debt and a fuel outbound');
    $acct = signIn('acc1');
    if ($debt) {
        $pay = round(($total - $paid) / 3, 2);
        $payBefore = (int) one('SELECT COUNT(*) FROM debt_payments WHERE debt_id = ?', [$debt[0]['id']]);
        $r = $acct->send('POST', '/payments', ['customer_id' => $acme, 'debt_id' => $debt[0]['id'], 'amount' => $pay, 'payment_method' => 'cash', 'paid_date' => $businessToday]);
        ok($r['status'] === 302 && (int) one('SELECT COUNT(*) FROM debt_payments WHERE debt_id = ?', [$debt[0]['id']]) === $payBefore + 1, 'a payment of ' . number_format($pay, 2) . ' (cash) is saved');
    }
    $kg = 100 + 10 * $day;
    $outBefore = (int) one('SELECT COUNT(*) FROM fuel_outbounds WHERE deleted_at IS NULL');
    $r = $acct->send('POST', '/outbound', ['tank_id' => $tank, 'outbound_date' => $businessToday, 'amount_kg' => $kg, 'to_name' => 'Generator', 'note' => 'test day ' . $day]);
    ok($r['status'] === 302 && (int) one('SELECT COUNT(*) FROM fuel_outbounds WHERE deleted_at IS NULL') === $outBefore + 1, "an outbound of $kg kg of gasoline is saved");

    section('the pages of the day open');
    $bad = openPages($acct, ['/', '/ticket-sales', '/spot-sales', '/card-sales', '/supplements', '/remainders', '/payments', '/debt-summary', '/loans', '/cards',
        '/ticket-summary', '/outbound', '/tank-stock', '/confirmations', '/confirmations/supplements', '/confirmations/summary']);
    ok(!$bad, 'the accountant opens the pages without an error' . ($bad ? ': ' . implode(', ', $bad) : ''));
} elseif ($part === 'morning') {
    $date = $date ?: date('Y-m-d', strtotime($businessToday . ' -1 day'));
    section("the accountant confirms the records of $date");
    $assistant = signIn('asst1');
    $cashier = signIn('cash1');
    $acct = signIn('acc1');

    $sets = [
        'sales' => rows('SELECT id FROM sales WHERE sale_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$date]),
        'supplements' => rows('SELECT id FROM supplements WHERE supply_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$date]),
        'debt_payments' => rows('SELECT id FROM debt_payments WHERE paid_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$date]),
        'fuel_outbounds' => rows('SELECT id FROM fuel_outbounds WHERE outbound_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$date]),
        'fuel_inbounds' => rows('SELECT id FROM fuel_inbounds WHERE inbound_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$date]),
    ];
    foreach ($sets as $k => $v) {
        $sets[$k] = array_column($v, 'id');
        echo '  ' . count($sets[$k]) . " $k wait for confirmation\n";
    }
    ok(count($sets['sales']) > 0, "sales of $date wait for confirmation");

    $c = $assistant->get('/confirmations?date=' . $date);
    ok($c['status'] === 403, 'the assistant has no access to the daily confirmation');
    $r = $assistant->request('POST', '/confirmations', ['date' => $date, 'sales' => array_slice($sets['sales'], 0, 3), 'from' => 'sales']);
    ok($r['status'] === 403, 'the assistant cannot confirm (HTTP ' . $r['status'] . ')');
    $r = $cashier->request('POST', '/confirmations', ['date' => $date, 'sales' => array_slice($sets['sales'], 0, 3), 'from' => 'sales']);
    ok($r['status'] === 403, 'the cashier cannot confirm (HTTP ' . $r['status'] . ')');

    $page = $acct->get('/confirmations?date=' . $date);
    ok($page['status'] === 200 && stripos($page['body'], 'Whoops') === false, 'the accountant opens the Sales confirmation of the date');
    foreach (array_chunk($sets['sales'], 1900) as $part1) {
        $r = $acct->send('POST', '/confirmations', ['date' => $date, 'sales' => $part1, 'from' => 'sales']);
        ok($r['status'] === 302, count($part1) . ' sales confirmed');
    }
    foreach (array_chunk($sets['supplements'], 1900) as $part1) {
        $r = $acct->send('POST', '/confirmations', ['date' => $date, 'supplements' => $part1, 'from' => 'supplements']);
        ok($r['status'] === 302, count($part1) . ' supplements confirmed');
    }
    foreach (['debt_payments' => 'debts', 'fuel_outbounds' => 'fuel', 'fuel_inbounds' => 'fuel'] as $field => $from) {
        if ($sets[$field]) {
            $r = $acct->send('POST', '/confirmations', ['date' => $date, $field => $sets[$field], 'from' => $from]);
            ok($r['status'] === 302, count($sets[$field]) . " $field confirmed");
        }
    }
    ok((int) one('SELECT COUNT(*) FROM sales WHERE sale_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$date]) === 0, "no sale of $date is left to confirm");
    ok((int) one('SELECT COUNT(*) FROM supplements WHERE supply_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$date]) === 0, "no supplement of $date is left to confirm");
    ok((int) one('SELECT COUNT(*) FROM sales WHERE sale_date = ? AND confirmed_at IS NOT NULL AND confirmed_by IS NULL', [$date]) === 0, 'every confirmation names who made it');

    $bad = openPages($acct, ['/confirmations?date=' . $date, '/confirmations/supplements?date=' . $date, '/confirmations/summary?date=' . $date]);
    ok(!$bad, 'the confirmation pages of the date open' . ($bad ? ': ' . implode(', ', $bad) : ''));
} else {
    echo "unknown --part=$part (day or morning)\n";
    exit(2);
}

echo "\n$checks checks, $fails failed\n";
exit($fails ? 1 : 0);
