<?php

// The big working day on the DEV web app, the web part (the terminal part is `e2e.exe devrun`, see Dev.inc). Everything is done on the web app's own
// pages with real logins; the database is only read.
//
//   php e2e\devvolume.php [base url] --stages=stock,bulk,extra,confirm,debts,verify[,...]
//
//   stock    ticket inbound and publishing of 53000 tickets (manager)
//   bulk     big sales by the assistant, paid by bank (assistant)
//   extra    a few sales by the accountant and the manager, so that every role has made records
//   confirm  the daily confirmation: who may, who may not, per creator, taking one back (accountant, assistant, cashier, manager)
//   debts    debts, payments and ticket loans settled on the web (accountant)
//   verify   checks of the whole day: tickets, tanks, debts, loans, the terminal's facts file (--facts=path)
//
// Lines "ok" / "FAIL" / "ISSUE" as in webday.php (ISSUE = a known rule of REVIEW_2026-10-20.md that the code does not keep yet).

require __DIR__ . '/WebClient.php';

$base = rtrim(isset($argv[1]) && strpos($argv[1], '--') !== 0 ? $argv[1] : 'http://dev.rrdgasstaion.com', '/');
$basePath = (string) parse_url($base, PHP_URL_PATH);
$stages = null;
$factsFile = null;
foreach ($argv as $a) {
    if (strpos($a, '--stages=') === 0) {
        $stages = explode(',', substr($a, 9));
    }
    if (strpos($a, '--facts=') === 0) {
        $factsFile = substr($a, 8);
    }
}

function stage($name)
{
    global $stages;

    return $stages === null || in_array($name, $stages, true);
}

$env = [];
foreach (file(__DIR__ . '/../web/.env') as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = trim($m[2], "\"'");
    }
}
// dates are the web app's (APP_TIMEZONE of web/.env), not the default zone of the PHP command line
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
$issues = 0;
$currentStage = 'stock';

function section($title, $stageName)
{
    global $currentStage;
    $currentStage = $stageName;
    if (stage($stageName)) {
        echo "\n== $title\n";
    }
}

function ok($condition, $what)
{
    global $checks, $fails, $currentStage;
    if (!stage($currentStage)) {
        return $condition;
    }
    ++$checks;
    if (!$condition) {
        ++$fails;
    }
    echo ($condition ? '  ok    ' : '  FAIL  ') . $what . "\n";

    return $condition;
}

function expect($condition, $what)
{
    global $issues, $currentStage;
    if (!stage($currentStage)) {
        return;
    }
    if ($condition) {
        echo '  ok    ' . $what . "\n";
    } else {
        ++$issues;
        echo '  ISSUE ' . $what . "\n";
    }
}

// one action on a page: the redirect must lead to $list
function act($label, Web $web, $method, $path, array $fields, $list)
{
    global $basePath, $currentStage;
    if (!stage($currentStage)) {
        return true;
    }
    $t = microtime(true);
    $r = $web->send($method, $path, $fields);
    $where = $r['location'] === null ? null : strtok($r['location'], '?#');
    $seconds = number_format(microtime(true) - $t, 1);
    if ($r['status'] === 302 && $where === $basePath . $list) {
        ok(true, "$label ({$seconds} s)");

        return true;
    }
    $text = '';
    if (!empty($r['page']['body'])) {
        preg_match_all('/(?:invalid-feedback|alert-danger|text-danger)[^>]*>\s*([^<]{3,200})</', $r['page']['body'], $m);
        $text = ' ' . trim(implode('; ', array_unique(array_map('trim', $m[1]))));
    }
    ok(false, "$label (HTTP {$r['status']} to " . ($r['location'] ?: '-') . ")$text");

    return false;
}

function login($username, $password = 'secret1')
{
    global $base;
    $web = new Web($base);
    if (!$web->login($username, $password)) {
        echo "FAIL  cannot sign in as $username\n";
        exit(1);
    }

    return $web;
}

function money($v)
{
    return round((float) $v, 2);
}

$today = date('Y-m-d');
$uid = function ($name) {
    return (int) one('SELECT id FROM users WHERE username = ?', [$name]);
};
$cust = function ($name) {
    return (int) one('SELECT id FROM customers WHERE name = ?', [$name]);
};
$acme = $cust('ACME Trading');
$beta = $cust('Beta Ltd');
$cedar = $cust('Cedar Logistics');
$delta = $cust('Delta Taxi');
$self = $cust('Self');
echo "Dev web app: $base (database " . $env['DB_DATABASE'] . "), today is $today\n";

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('1. ticket inbound and publishing: 20000 + 20000, 2000 x 4, 5000', 'stock');
$manager = login('manager1');
$inbound = [['D', 2, 20000], ['A', 2, 20000], ['A', 0, 2000], ['A', 1, 2000], ['D', 0, 2000], ['D', 1, 2000], ['D', 3, 5000]];
foreach ($inbound as $i) {
    act("received {$i[2]} blank tickets of {$i[0]} {$i[1]} (" . [0 => 5, 1 => 10, 2 => 15, 3 => 50][$i[1]] . ' kg)', $manager, 'POST', '/ticket-inbound',
        ['fuel_type' => $i[0], 'spec' => $i[1], 'quantity' => $i[2], 'inbound_date' => $today, 'note' => 'volume test'], '/ticket-inbound');
}
foreach ($inbound as $i) {
    $start = (int) one('SELECT COALESCE(MAX(serial), 0) FROM tickets WHERE fuel_type = ? AND spec = ?', [$i[0], $i[1]]) + 1;
    if (stage('stock')) {
        $before = (int) one('SELECT COUNT(*) FROM tickets WHERE fuel_type = ? AND spec = ?', [$i[0], $i[1]]);
    }
    act("published {$i[0]}:{$i[1]} serials $start to " . ($start + $i[2] - 1), $manager, 'POST', '/ticket-publish',
        ['stock_item' => $i[0] . ':' . $i[1], 'start_serial' => $start, 'end_serial' => $start + $i[2] - 1, 'date' => $today], '/ticket-publish');
    if (stage('stock')) {
        ok((int) one('SELECT COUNT(*) FROM tickets WHERE fuel_type = ? AND spec = ?', [$i[0], $i[1]]) - $before === $i[2], "{$i[2]} tickets of {$i[0]}:{$i[1]} exist after publishing");
    }
}
if (stage('stock')) {
    $page = $manager->get('/ticket-summary');
    ok($page['status'] === 200, 'the Ticket Summary page opens with the stock');
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('2. big ticket sales by the assistant, paid by bank', 'bulk');
$assistant = login('assistant1');
$bulk = [
    ['Beta Ltd buys 1500 x 15 kg Diesel (22500 kg), bank, paid in full', 'D', 2, 1500, $beta, '7.5', 168750],
    ['Cedar Logistics buys 1000 x 15 kg Gasoline (15000 kg), bank, half paid', 'A', 2, 1000, $cedar, '8.2', 61500],
    ['ACME buys 100 x 50 kg Diesel (5000 kg), bank, nothing paid', 'D', 3, 100, $acme, '7.5', 0],
    ['Delta Taxi buys 500 x 10 kg Diesel (5000 kg), bank, paid in full', 'D', 1, 500, $delta, '7.5', 37500],
    ['Delta Taxi buys 300 x 5 kg Gasoline (1500 kg), bank, part paid', 'A', 0, 300, $delta, '8.2', 6000],
    ['Beta Ltd buys 200 x 10 kg Gasoline (2000 kg), bank, nothing paid', 'A', 1, 200, $beta, '8.2', 0],
];
foreach ($bulk as $b) {
    $before = (int) one("SELECT COUNT(*) FROM tickets WHERE status = 1 AND fuel_type = ? AND spec = ?", [$b[1], $b[2]]);
    $ok = act($b[0], $assistant, 'POST', '/ticket-sales', ['sale_type' => 'ticket', 'fuel_type' => $b[1], 'lines' => [['spec' => $b[2], 'quantity' => $b[3]]], 'customer_id' => $b[4],
        'price' => $b[5], 'usage' => 'sale', 'payment_method' => 'bank', 'paid_amount' => $b[6], 'sale_date' => $today], '/ticket-sales');
    if ($ok && stage('bulk')) {
        ok((int) one("SELECT COUNT(*) FROM tickets WHERE status = 1 AND fuel_type = ? AND spec = ?", [$b[1], $b[2]]) - $before === $b[3], "{$b[3]} tickets of {$b[1]}:{$b[2]} are sold");
    }
}
if (stage('bulk')) {
    $sale = rows("SELECT * FROM sales WHERE customer_id = ? AND amount_kg = 22500 ORDER BY created_at DESC LIMIT 1", [$beta]);
    ok($sale && money($sale[0]['total_amount']) === 168750.0 && money($sale[0]['unpaid_amount']) === 0.0, 'the big Diesel sale totals 168750.00, nothing unpaid');
    $sale = rows("SELECT * FROM sales WHERE customer_id = ? AND amount_kg = 15000 ORDER BY created_at DESC LIMIT 1", [$cedar]);
    ok($sale && money($sale[0]['total_amount']) === 123000.0 && money($sale[0]['unpaid_amount']) === 61500.0, 'the Gasoline sale totals 123000.00 with 61500.00 unpaid');
    ok((int) one("SELECT COUNT(*) FROM debts d JOIN sales s ON s.id = d.sale_id WHERE s.user_id = ? AND s.sale_date = ?", [$uid('assistant1'), $today]) === 4, 'four debts were made for the sales that are not paid in full');
    ok(money(one("SELECT SUM(d.amount) FROM debts d JOIN sales s ON s.id = d.sale_id WHERE s.user_id = ? AND s.sale_date = ?", [$uid('assistant1'), $today])) === 61500.0 + 37500.0 + 6300.0 + 16400.0, 'the debts add up to 121700.00');
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('2b. a few sales by the accountant and the manager (so every role has made records)', 'extra');
$accountant = login('accountant1');
act('the accountant sells 10 x 5 kg Diesel to ACME, cash', $accountant, 'POST', '/ticket-sales', ['sale_type' => 'ticket', 'fuel_type' => 'D', 'lines' => [['spec' => 0, 'quantity' => 10]],
    'customer_id' => $acme, 'price' => '7.5', 'usage' => 'sale', 'payment_method' => 'cash', 'paid_amount' => '375', 'sale_date' => $today], '/ticket-sales');
act('the manager sells 10 x 5 kg Gasoline to Cedar, bank, nothing paid', $manager, 'POST', '/ticket-sales', ['sale_type' => 'ticket', 'fuel_type' => 'A', 'lines' => [['spec' => 0, 'quantity' => 10]],
    'customer_id' => $cedar, 'price' => '8.2', 'usage' => 'sale', 'payment_method' => 'bank', 'paid_amount' => '0', 'sale_date' => $today], '/ticket-sales');
act('the accountant enters a spot sale of 12 kg Diesel for Delta Taxi', $accountant, 'POST', '/ticket-sales', ['sale_type' => 'spot', 'fuel_type' => 'D', 'amount_kg' => '12', 'customer_id' => $delta,
    'price' => '7.5', 'usage' => 'sale', 'paid_amount' => '90', 'sale_date' => $today], '/spot-sales');

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('6. the daily confirmation: who may, who may not', 'confirm');
$cashierWeb = login('cashier1');
$assistantWeb = login('assistant1');
$accountantWeb = login('accountant1');
$managerWeb = login('manager1');
if (stage('confirm')) {
    $c = $cashierWeb->get('/confirmations');
    ok($c['status'] === 403, 'the cashier has no access to the daily confirmation (HTTP ' . $c['status'] . ')');
    $c = $assistantWeb->get('/confirmations');
    ok($c['status'] === 403, 'the assistant has no access to the daily confirmation (HTTP ' . $c['status'] . ')');
    foreach ([['accountant', $accountantWeb], ['manager', $managerWeb]] as $who) {
        $c = $who[1]->get('/confirmations?date=' . $today);
        ok($c['status'] === 200, 'the ' . $who[0] . ' sees the daily confirmation');
    }
    $byCreator = rows("SELECT u.username, COUNT(*) AS n FROM sales s JOIN users u ON u.id = s.user_id WHERE s.confirmed_at IS NULL AND s.deleted_at IS NULL GROUP BY u.username ORDER BY u.username");
    $line = [];
    foreach ($byCreator as $r) {
        $line[] = $r['username'] . ' ' . $r['n'];
    }
    echo '  waiting for confirmation, by who made the record: ' . implode(', ', $line) . "\n";
    ok(count($byCreator) >= 4, 'records of at least four people wait (cashier1 at the terminal, the assistant, the accountant and the manager on the web)');

    // a record that the accountant confirms first, to try the rules on
    $dates = array_column(rows("SELECT DISTINCT sale_date AS d FROM sales WHERE confirmed_at IS NULL AND deleted_at IS NULL ORDER BY sale_date"), 'd');
    echo '  dates with open records: ' . implode(', ', $dates) . "\n";
    foreach ($dates as $d) {
        $d = substr($d, 0, 10);
        $saleIds = array_column(rows('SELECT id FROM sales WHERE sale_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$d]), 'id');
        $supplyIds = array_column(rows('SELECT id FROM supplements WHERE supply_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$d]), 'id');
        if (count($saleIds) > 1900 || count($supplyIds) > 1900) {
            echo "  $d has more than 1900 records: confirmed in parts\n";
        }
        // the assistant and the cashier cannot confirm
        $r = $assistantWeb->request('POST', '/confirmations', ['date' => $d, 'sales' => array_slice($saleIds, 0, 3), 'from' => 'sales']);
        ok($r['status'] === 403, "$d: the assistant cannot confirm (HTTP " . $r['status'] . ')');
        $r = $cashierWeb->request('POST', '/confirmations', ['date' => $d, 'sales' => array_slice($saleIds, 0, 3), 'from' => 'sales']);
        ok($r['status'] === 403, "$d: the cashier cannot confirm (HTTP " . $r['status'] . ')');
        foreach (array_chunk($saleIds, 1900) ?: [[]] as $part) {
            if ($part) {
                act("$d: the accountant confirms " . count($part) . ' sales', $accountantWeb, 'POST', '/confirmations', ['date' => $d, 'sales' => $part, 'from' => 'sales'], '/confirmations');
            }
        }
        foreach (array_chunk($supplyIds, 1900) as $part) {
            act("$d: the accountant confirms " . count($part) . ' supplements', $accountantWeb, 'POST', '/confirmations', ['date' => $d, 'supplements' => $part, 'from' => 'supplements'], '/confirmations');
        }
    }
    ok((int) one('SELECT COUNT(*) FROM sales WHERE confirmed_at IS NULL AND deleted_at IS NULL') === 0 && (int) one('SELECT COUNT(*) FROM supplements WHERE confirmed_at IS NULL AND deleted_at IS NULL') === 0, 'nothing is left to confirm');
    ok((int) one('SELECT COUNT(DISTINCT confirmed_by) FROM sales WHERE confirmed_at IS NOT NULL') >= 1 && (int) one('SELECT COUNT(*) FROM sales WHERE confirmed_at IS NOT NULL AND confirmed_by IS NULL') === 0, 'every confirmation names who made it');
    // per creator: a confirmed record of each kind of user is final
    foreach (['cashier1', 'assistant1', 'accountant1', 'manager1'] as $name) {
        $sale = rows("SELECT s.id, s.sale_type FROM sales s WHERE s.user_id = ? AND s.confirmed_at IS NOT NULL AND s.reverses_sale_id IS NULL AND s.deleted_at IS NULL ORDER BY s.created_at DESC LIMIT 1", [$uid($name)]);
        if (!ok($sale ? true : false, "$name has a confirmed record")) {
            continue;
        }
        $id = $sale[0]['id'];
        $r = $assistantWeb->send('PUT', '/ticket-sales/' . $id, ['sale_date' => $today, 'comment' => 'changed after confirmation']);
        ok(one('SELECT comment FROM sales WHERE id = ?', [$id]) !== 'changed after confirmation', "the record of $name is final: its comment cannot be changed");
        $r = $assistantWeb->send('DELETE', '/ticket-sales/' . $id);
        ok(one('SELECT COUNT(*) FROM sales WHERE id = ? AND deleted_at IS NULL', [$id]) == 1, "the record of $name cannot be deleted once confirmed");
    }
    // taking a confirmation back: the manager only
    $victim = rows("SELECT id FROM sales WHERE user_id = ? AND confirmed_at IS NOT NULL AND reverses_sale_id IS NULL ORDER BY created_at DESC LIMIT 1", [$uid('cashier1')]);
    if ($victim) {
        $id = $victim[0]['id'];
        $r = $accountantWeb->request('POST', '/ticket-sales/' . $id . '/unconfirm');
        ok($r['status'] === 403 && one('SELECT confirmed_at FROM sales WHERE id = ?', [$id]) !== null, 'the accountant cannot take a confirmation back (HTTP ' . $r['status'] . ')');
        $r = $assistantWeb->request('POST', '/ticket-sales/' . $id . '/unconfirm');
        ok($r['status'] === 403 && one('SELECT confirmed_at FROM sales WHERE id = ?', [$id]) !== null, 'the assistant cannot take a confirmation back (HTTP ' . $r['status'] . ')');
        $managerWeb->send('POST', '/ticket-sales/' . $id . '/unconfirm');
        ok(one('SELECT confirmed_at FROM sales WHERE id = ?', [$id]) === null, 'the manager takes the confirmation of a cashier record back');
        $assistantWeb->send('PUT', '/ticket-sales/' . $id, ['sale_date' => substr((string) one('SELECT sale_date FROM sales WHERE id = ?', [$id]), 0, 10), 'comment' => 'checked by the assistant']);
        ok(one('SELECT comment FROM sales WHERE id = ?', [$id]) === 'checked by the assistant', 'the assistant can now add a comment to it');
        $d = substr((string) one('SELECT sale_date FROM sales WHERE id = ?', [$id]), 0, 10);
        act("$d: the accountant confirms it again", $accountantWeb, 'POST', '/confirmations', ['date' => $d, 'sales' => [$id], 'from' => 'sales'], '/confirmations');
        ok(one('SELECT confirmed_at FROM sales WHERE id = ?', [$id]) !== null, 'it is confirmed again');
    }
    foreach (['/confirmations/supplements?date=' . $today, '/confirmations/summary?date=' . $today] as $path) {
        $p = $accountantWeb->get($path);
        ok($p['status'] === 200 && stripos($p['body'], 'Whoops') === false, "$path opens");
    }
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('7. debts, payments and ticket loans', 'debts');
if (stage('debts')) {
    $open = function () {
        return rows("SELECT d.id, d.customer_id, d.amount, d.payment_method, COALESCE((SELECT SUM(p.amount) FROM debt_payments p WHERE p.debt_id = d.id), 0) AS paid FROM debts d WHERE d.deleted_at IS NULL ORDER BY d.amount DESC");
    };
    $debts = $open();
    echo '  ' . count($debts) . ' debts, ' . number_format(array_sum(array_column($debts, 'amount')), 2) . ' owed, ' . number_format(array_sum(array_column($debts, 'paid')), 2) . " paid\n";
    ok(count($debts) >= 8, 'debts exist for the credit sales of the web, the opening balance and the sales of the manager');
    // pay the biggest in two parts, the second in the other payment method, one small debt fully, and a general payment of a customer
    $big = $debts[0];
    $half = round(($big['amount'] - $big['paid']) / 2, 2);
    act('part payment of ' . number_format($half, 2) . ' (bank) on the biggest debt', $accountantWeb, 'POST', '/payments', ['customer_id' => $big['customer_id'], 'debt_id' => $big['id'], 'amount' => $half, 'payment_method' => 'bank', 'paid_date' => $today], '/payments');
    $rest = round($big['amount'] - $big['paid'] - $half, 2);
    act('the rest ' . number_format($rest, 2) . ' (cash) of the same debt', $accountantWeb, 'POST', '/payments', ['customer_id' => $big['customer_id'], 'debt_id' => $big['id'], 'amount' => $rest, 'payment_method' => 'cash', 'paid_date' => $today], '/payments');
    ok(abs(money(one('SELECT amount FROM debts WHERE id = ?', [$big['id']])) - money(one('SELECT SUM(amount) FROM debt_payments WHERE debt_id = ?', [$big['id']]))) < 0.005, 'the biggest debt is settled: payments equal the debt');
    $small = end($debts);
    act('the smallest debt ' . number_format($small['amount'] - $small['paid'], 2) . ' paid in full (cash)', $accountantWeb, 'POST', '/payments', ['customer_id' => $small['customer_id'], 'debt_id' => $small['id'], 'amount' => round($small['amount'] - $small['paid'], 2), 'payment_method' => 'cash', 'paid_date' => $today], '/payments');
    act('a general payment of 500.00 (bank) of Delta Taxi, not tied to a debt', $accountantWeb, 'POST', '/payments', ['customer_id' => $delta, 'amount' => '500', 'payment_method' => 'bank', 'paid_date' => $today, 'note' => 'general'], '/payments');
    // the same checks the page makes: per customer what is owed
    $now = $open();
    foreach ([$acme, $beta, $cedar, $delta] as $cid) {
        $owed = money(array_sum(array_map(function ($r) use ($cid) { return $r['customer_id'] == $cid ? $r['amount'] - $r['paid'] : 0; }, $now)));
        $general = money(one('SELECT COALESCE(SUM(amount), 0) FROM debt_payments WHERE customer_id = ? AND debt_id IS NULL', [$cid]));
        echo '  customer ' . $cid . ': owes ' . number_format($owed, 2) . ' on its debts, general payments ' . number_format($general, 2) . "\n";
    }
    $p = $accountantWeb->get('/payments');
    ok($p['status'] === 200 && stripos($p['body'], 'Whoops') === false, 'the Payments page opens with this many debts');
    $p = $accountantWeb->get('/debt-summary?from=' . date('Y-m-01') . '&to=' . $today);
    ok($p['status'] === 200 && stripos($p['body'], 'Whoops') === false, 'the Debt Summary page opens');

    // ticket loans
    $loans = rows("SELECT l.id, l.customer_id, l.fuel_type, l.amount_kg, l.settled_amount_kg, l.sale_id FROM ticket_loans l WHERE l.deleted_at IS NULL ORDER BY l.amount_kg DESC");
    echo '  ' . count($loans) . ' ticket loans, ' . number_format(array_sum(array_column($loans, 'amount_kg')) - array_sum(array_column($loans, 'settled_amount_kg')), 2) . " kg open\n";
    ok(count($loans) >= 6, 'ticket loans exist: the opening one, one made on the web and the ones made at the terminal');
    $loan = null;
    foreach ($loans as $l) {
        if ($l['fuel_type'] === 'D' && $l['amount_kg'] - $l['settled_amount_kg'] > 0.005) {
            $loan = $l;
            break;
        }
    }
    if (ok($loan ? true : false, 'an open Diesel loan to settle on the web')) {
        $balance = money($loan['amount_kg'] - $loan['settled_amount_kg']);
        $tickets = array_column(rows("SELECT t.barcode FROM tickets t WHERE t.status = 1 AND t.fuel_type = 'D' AND t.spec = 0 AND t.sale_id IS NOT NULL ORDER BY t.serial LIMIT 12"), 'barcode');
        $surrendered = 0;
        foreach ($tickets as $barcode) {
            if ($balance - $surrendered <= 0.005) {
                break;
            }
            if (act("the accountant settles loan $balance kg: ticket $barcode (5 kg)", $accountantWeb, 'POST', '/loans', ['loan_id' => $loan['id'], 'ticket_barcode' => $barcode], '/loans')) {
                $surrendered += 5;
            }
        }
        $after = rows('SELECT settled_amount_kg FROM ticket_loans WHERE id = ?', [$loan['id']]);
        $ledger = money(one('SELECT COALESCE(SUM(amount_kg), 0) FROM ticket_loan_settlements WHERE loan_id = ? AND deleted_at IS NULL', [$loan['id']]));
        ok($after && abs(money($after[0]['settled_amount_kg']) - $ledger) < 0.005, 'the settled kg of the loan equals the sum of its settlements (' . number_format($ledger, 2) . ')');
        $used = $tickets ? $tickets[0] : null;
        if ($used) {
            $before = (int) one('SELECT COUNT(*) FROM ticket_loan_settlements');
            $accountantWeb->send('POST', '/loans', ['loan_id' => $loan['id'], 'ticket_barcode' => $used]);
            ok((int) one('SELECT COUNT(*) FROM ticket_loan_settlements') === $before, 'a ticket that settled a loan cannot settle again');
        }
    }
    $p = $accountantWeb->get('/loans');
    ok($p['status'] === 200 && stripos($p['body'], 'Whoops') === false, 'the Ticket Loans page opens');
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('verification of the whole day', 'verify');
if (stage('verify')) {
    // tickets: every ticket published is in exactly one state, and what was received is what exists
    foreach ([['D', 2], ['A', 2], ['A', 0], ['A', 1], ['D', 0], ['D', 1], ['D', 3]] as $k) {
        $received = (int) one('SELECT COALESCE(SUM(quantity), 0) FROM ticket_inbounds WHERE fuel_type = ? AND spec = ? AND deleted_at IS NULL', [$k[0], $k[1]]);
        $exist = (int) one('SELECT COUNT(*) FROM tickets WHERE fuel_type = ? AND spec = ?', [$k[0], $k[1]]);
        $states = rows('SELECT status, COUNT(*) AS n FROM tickets WHERE fuel_type = ? AND spec = ? GROUP BY status', [$k[0], $k[1]]);
        $line = [];
        foreach ($states as $s) {
            $line[] = ['stock', 'sold', 'supplied', 'restricted'][$s['status']] . ' ' . $s['n'];
        }
        ok($exist === $received, "{$k[0]}:{$k[1]} received $received = tickets that exist $exist (" . implode(', ', $line) . ')');
    }
    ok((int) one('SELECT COUNT(*) FROM tickets WHERE status = 1 AND sale_id IS NULL AND code NOT IN (25)') === 0, 'every sold ticket belongs to a sale (the remainder tickets have none)');
    ok((int) one('SELECT COUNT(*) FROM tickets t WHERE t.status = 2 AND t.supplement_id IS NULL AND t.barcode NOT IN (SELECT ticket_barcode FROM ticket_loan_settlements)') === 0, 'every supplied ticket belongs to a supplement or a loan settlement');
    ok((int) one('SELECT COUNT(*) FROM (SELECT ticket_barcode FROM ticket_loan_settlements GROUP BY ticket_barcode HAVING COUNT(*) > 1) x') === 0, 'no ticket settled two loans');
    // money
    $sold = money(one('SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE deleted_at IS NULL'));
    $paid = money(one('SELECT COALESCE(SUM(paid_amount), 0) FROM sales WHERE deleted_at IS NULL'));
    $unpaid = money(one('SELECT COALESCE(SUM(unpaid_amount), 0) FROM sales WHERE deleted_at IS NULL'));
    ok(abs($sold - $paid - $unpaid) < 0.01, 'sold ' . number_format($sold, 2) . ' = paid ' . number_format($paid, 2) . ' + unpaid ' . number_format($unpaid, 2));
    ok((int) one("SELECT COUNT(*) FROM sales WHERE ABS(total_amount - ROUND(amount_kg * price, 2)) > 0.011 AND `usage` = 'sale' AND reverses_sale_id IS NULL AND deleted_at IS NULL") === 0, 'every sale of the day is weight x price');
    $badDebt = (int) one("SELECT COUNT(*) FROM debts d JOIN sales s ON s.id = d.sale_id WHERE d.deleted_at IS NULL AND d.amount > s.unpaid_amount + 0.005 AND s.reverses_sale_id IS NULL");
    ok($badDebt === 0, 'no debt is bigger than the unpaid amount of its sale');
    // tanks
    foreach (rows('SELECT id, name FROM tanks') as $tank) {
        $opening = money(one('SELECT COALESCE(amount_kg, 0) FROM initial_tank_balances WHERE tank_id = ?', [$tank['id']]));
        $in = money(one('SELECT COALESCE(SUM(amount_kg), 0) FROM fuel_inbounds WHERE tank_id = ? AND deleted_at IS NULL', [$tank['id']]));
        $out = money(one('SELECT COALESCE(SUM(s.out_amount_kg), 0) FROM supplements s JOIN nozzles n ON n.id = s.nozzle_id WHERE n.tank_id = ? AND s.deleted_at IS NULL', [$tank['id']]));
        echo '  ' . $tank['name'] . ': ' . number_format($opening, 2) . ' + ' . number_format($in, 2) . ' - ' . number_format($out, 2) . ' = ' . number_format($opening + $in - $out, 2) . " kg\n";
    }
    $p = login('accountant1')->get('/reports/tanks?from=' . date('Y-m-01') . '&to=' . $today);
    ok($p['status'] === 200, 'the tank report opens');
    ok((int) one('SELECT COUNT(*) FROM sync_errors WHERE resolved_at IS NULL') === 0, 'the server logged no unresolved sync error (' . (int) one('SELECT COUNT(*) FROM sync_errors') . ' in all)');
    // the terminal's facts
    if ($factsFile && is_file($factsFile)) {
        $f = json_decode(file_get_contents($factsFile), true);
        $cashier = $uid('cashier1');
        $mine = rows("SELECT sale_type, COUNT(*) AS n, ROUND(SUM(amount_kg), 2) AS kg, ROUND(SUM(total_amount), 2) AS total FROM sales WHERE user_id = ? AND deleted_at IS NULL GROUP BY sale_type", [$cashier]);
        $by = [];
        foreach ($mine as $r) {
            $by[$r['sale_type']] = $r;
        }
        $terminalSpot = isset($by['spot']) ? (int) $by['spot']['n'] : 0;
        ok($terminalSpot >= $f['spotSales'], "the terminal's $f[spotSales] spot sales are on the server ($terminalSpot by cashier1 in all)");
        $serverKg = money(rows("SELECT SUM(amount_kg) AS v FROM sales WHERE user_id = ? AND sale_type = 'spot' AND deleted_at IS NULL", [$cashier])[0]['v'] ?: 0);
        ok($serverKg >= $f['spotKgX100'] / 100 - 0.01 && $serverKg - $f['spotKgX100'] / 100 < 100, 'the kg of the spot sales on the server (' . number_format($serverKg, 2) . ') are the terminal\'s ' . number_format($f['spotKgX100'] / 100, 2) . ' plus the earlier sale made in the app');
        ok((int) one('SELECT COUNT(*) FROM supplements') >= $f['supplies'], 'the terminal\'s ' . $f['supplies'] . ' supplies are on the server (' . (int) one('SELECT COUNT(*) FROM supplements') . ' in all)');
        ok(abs(money(one('SELECT COALESCE(SUM(out_amount_kg), 0) FROM supplements WHERE card_id IS NULL')) - money(one('SELECT COALESCE(SUM(out_amount_kg), 0) FROM supplements'))) < 0.01, 'the supplements are consistent');
        echo '  the terminal part took ' . $f['seconds'] . " s\n";
    }
}

echo "\n$checks checks, " . ($checks - $fails) . " ok, $fails failed, $issues known issue(s)\n";
exit($fails > 0 ? 1 : 0);
