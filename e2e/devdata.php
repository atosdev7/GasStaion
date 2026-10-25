<?php

// Fills the dev web app with a few days of business, by doing what the staff do on its pages (real logins, forms and buttons), so that the
// result can be looked at and tried by hand:
//
//   php e2e\devdata.php [base url]        default http://localhost/gasstation (the web app served by the Apache service)
//
// It refuses to run when the database already holds sales or customers (it would double the demo data). Everything goes through the web app;
// the database is only read, to find the ids of what was just made. Logins made: admin (the seeded one), and manager1, assistant1,
// accountant1, cashier1 with the password secret1.

require __DIR__ . '/WebClient.php';

$base = rtrim(isset($argv[1]) && strpos($argv[1], '--') !== 0 ? $argv[1] : 'http://localhost/gasstation', '/');
$basePath = (string) parse_url($base, PHP_URL_PATH);

$env = [];
foreach (file(__DIR__ . '/../web/.env') as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = trim($m[2], "\"'");
    }
}
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

function day($back)
{
    return date('Y-m-d', strtotime("-$back days"));
}

$failures = 0;
$done = 0;

// --stages=returns,confirm runs only those parts (to go on after a part failed); without it everything runs
$stages = null;
foreach ($argv as $a) {
    if (strpos($a, '--stages=') === 0) {
        $stages = explode(',', substr($a, 9));
    }
}
function stage($name)
{
    global $stages;

    return $name === 'summary' || $stages === null || in_array($name, $stages, true);
}

// one action on a page: the redirect must lead to $list (a form refused for a reason redirects back to the form)
function act($label, Web $web, $method, $path, array $fields, $list)
{
    global $basePath, $failures, $done, $currentStage;
    if (!stage($currentStage)) {
        return true; // a part passed over with --stages
    }
    $r = $web->send($method, $path, $fields);
    $where = $r['location'] === null ? null : strtok($r['location'], '?#');
    $good = $r['status'] === 302 && $where === $basePath . $list;
    if ($good) {
        ++$done;
        echo "  ok    $label\n";

        return true;
    }
    ++$failures;
    $text = '';
    if (!empty($r['page']['body'])) {
        preg_match_all('/(?:invalid-feedback|alert-danger|text-danger)[^>]*>\s*([^<]{3,200})</', $r['page']['body'], $m);
        $text = ' ' . trim(implode('; ', array_unique(array_map('trim', $m[1]))));
    }
    echo "  FAIL  $label (HTTP {$r['status']} to " . ($r['location'] ?: '-') . ")$text\n";

    return false;
}

// the stage a section belongs to: with --stages the others are passed over (their actions are not repeated, the lookups still run)
$currentStage = 'setup';

function section($title, $stage)
{
    global $currentStage;
    $currentStage = $stage;
    if (stage($stage)) {
        echo "\n== $title\n";
    }
}

function login($username, $password)
{
    global $base;
    $web = new Web($base);
    if (!$web->login($username, $password)) {
        echo "FAIL  cannot sign in as $username\n";
        exit(1);
    }

    return $web;
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
if (stage('setup') && (one('SELECT COUNT(*) FROM sales') > 0 || one('SELECT COUNT(*) FROM customers') > 1 || one('SELECT COUNT(*) FROM tanks') > 0)) {
    echo "The database " . $env['DB_DATABASE'] . " already holds sales, tanks or customers: nothing was done.\n";
    exit(2);
}
echo "Dev web app: $base (database " . $env['DB_DATABASE'] . ")\n";

section('setup by the administrator', 'setup');
$admin = login('admin', 'admin123');
foreach ([['Manager One', 'manager1', 'manager'], ['Accountant One', 'accountant1', 'accountant'], ['Assistant One', 'assistant1', 'assistant'], ['Cashier One', 'cashier1', 'cashier']] as $u) {
    act("user {$u[1]} ({$u[2]})", $admin, 'POST', '/users', ['name' => $u[0], 'username' => $u[1], 'role' => $u[2], 'password' => 'secret1'], '/users');
}
act('fuel type Gasoline: price 8.20, 0.7400 kg/L', $admin, 'POST', '/fuel', ['fuel_type' => 'A', 'price' => '8.20', 'density' => '0.7400'], '/fuel');
act('fuel type Diesel: price 7.50, 0.8300 kg/L', $admin, 'POST', '/fuel', ['fuel_type' => 'D', 'price' => '7.50', 'density' => '0.8300'], '/fuel');
act('tank T1 Gasoline 20000 kg', $admin, 'POST', '/tanks', ['name' => 'T1 Gasoline', 'fuel_type' => 'A', 'capacity_kg' => '20000'], '/tanks');
act('tank T2 Diesel 30000 kg', $admin, 'POST', '/tanks', ['name' => 'T2 Diesel', 'fuel_type' => 'D', 'capacity_kg' => '30000'], '/tanks');
act('Smart210 device POS1 (127.0.0.1)', $admin, 'POST', '/devices', ['name' => 'POS1', 'ip_address' => '127.0.0.1', 'purge_days' => '3', 'pin' => '0000'], '/devices');
$device = one("SELECT id FROM devices WHERE name = 'POS1'");
$t1 = one("SELECT id FROM tanks WHERE name = 'T1 Gasoline'");
$t2 = one("SELECT id FROM tanks WHERE name = 'T2 Diesel'");
// the nozzles are managed on the Smart210 (the web app only lists them): they are made here as its push makes them
foreach (stage($currentStage) ? [[1, 'A', $t1], [2, 'D', $t2]] : [] as $nozzle) {
    $pdo->prepare('INSERT INTO nozzles (device_id, number, tank_id, fuel_type, board_address, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())')
        ->execute([$device, $nozzle[0], $nozzle[2], $nozzle[1], $nozzle[0]]);
    $done++;
    echo "  ok    nozzle {$nozzle[0]} ({$nozzle[1]}) made as the Smart210 reports it\n";
}
foreach ([['ACME Trading', '555-0101'], ['Beta Ltd', '555-0102'], ['Cedar Logistics', '555-0103'], ['Delta Taxi', '555-0104']] as $c) {
    act("customer {$c[0]}", $admin, 'POST', '/customers', ['name' => $c[0], 'phone' => $c[1]], '/customers');
}
$acme = one("SELECT id FROM customers WHERE name = 'ACME Trading'");
$beta = one("SELECT id FROM customers WHERE name = 'Beta Ltd'");
$cedar = one("SELECT id FROM customers WHERE name = 'Cedar Logistics'");
$delta = one("SELECT id FROM customers WHERE name = 'Delta Taxi'");
$self = one("SELECT id FROM customers WHERE is_default = 1"); // "Individual"

section('opening balances by the accountant (what existed before the program)', 'opening');
$acct = login('accountant1', 'secret1');
act('opening stock T1 5000 kg, T2 8000 kg', $acct, 'POST', '/balances/tanks', ['tanks' => [$t1 => '5000', $t2 => '8000']], '/balances/tanks');
act('opening debt of Cedar Logistics 300.00 (bank)', $acct, 'POST', '/balances/debts', ['customer_id' => $cedar, 'amount' => '300', 'payment_method' => 'bank'], '/balances/debts');
act('opening ticket loan of Delta Taxi 20 kg Diesel', $acct, 'POST', '/balances/loans', ['customer_id' => $delta, 'fuel_type' => 'D', 'amount_kg' => '20'], '/balances/loans');

section('fuel arrives and blank tickets are received and published by the manager', 'stock');
$manager = login('manager1', 'secret1');
act(day(5) . ' Diesel 20000 kg into T2 at 6.10 (default cost) from Refinery No. 1', $manager, 'POST', '/inbound', ['tank_id' => $t2, 'inbound_date' => day(5), 'amount_kg' => '20000', 'cost' => '6.10', 'is_default' => '1', 'from_name' => 'Refinery No. 1'], '/inbound');
act(day(5) . ' Gasoline 10000 kg into T1 at 6.90 (default cost) from Depot North', $manager, 'POST', '/inbound', ['tank_id' => $t1, 'inbound_date' => day(5), 'amount_kg' => '10000', 'cost' => '6.90', 'is_default' => '1', 'from_name' => 'Depot North'], '/inbound');
act(day(2) . ' Diesel 10000 kg into T2 at 6.25 (new default cost) from Refinery No. 1', $manager, 'POST', '/inbound', ['tank_id' => $t2, 'inbound_date' => day(2), 'amount_kg' => '10000', 'cost' => '6.25', 'is_default' => '1', 'from_name' => 'Refinery No. 1', 'note' => 'second delivery'], '/inbound');
foreach ([['D', 0, 150], ['D', 1, 80], ['A', 0, 60]] as $t) {
    $kg = [0 => 5, 1 => 10][$t[1]];
    act(day(5) . " received {$t[2]} blank tickets {$kg} kg ({$t[0]})", $manager, 'POST', '/ticket-inbound', ['fuel_type' => $t[0], 'spec' => $t[1], 'quantity' => $t[2], 'inbound_date' => day(5), 'note' => 'printing press delivery'], '/ticket-inbound');
}
foreach ([['D:0', 150], ['D:1', 80], ['A:0', 60]] as $t) {
    act(day(5) . " published {$t[0]} serials 1 to {$t[1]}", $manager, 'POST', '/ticket-publish', ['stock_item' => $t[0], 'start_serial' => 1, 'end_serial' => $t[1], 'date' => day(5)], '/ticket-publish');
}

section('sales, five days, by the assistant', 'sales');
$assistant = login('assistant1', 'secret1');
$ticket = function ($label, $fuel, $spec, $qty, $customer, $price, $usage, $method, $paid, $back) use ($assistant, $manager) { // (a day further back than the accountant and the assistant may date a sale is the manager's)
    $f = ['sale_type' => 'ticket', 'fuel_type' => $fuel, 'lines' => [['spec' => $spec, 'quantity' => $qty]], 'customer_id' => $customer, 'price' => $price, 'usage' => $usage, 'sale_date' => day($back)];
    if ($usage === 'sale') {
        $f += ['payment_method' => $method, 'paid_amount' => $paid];
    }

    return act(day($back) . ' ' . $label, ($back > 3 ? $manager : $assistant), 'POST', '/ticket-sales', $f, '/ticket-sales');
};
$spot = function ($label, $fuel, $kg, $customer, $price, $paid, $back) use ($acct, $manager) { // (spot sales are the accountant's: the assistant works on ticket sales only)
    return act(day($back) . ' ' . $label, ($back > 3 ? $manager : $acct), 'POST', '/ticket-sales', ['sale_type' => 'spot', 'fuel_type' => $fuel, 'amount_kg' => $kg, 'customer_id' => $customer, 'price' => $price, 'usage' => 'sale', 'paid_amount' => $paid, 'sale_date' => day($back)], '/spot-sales');
};
$ticket('ACME buys 4 x 5 kg Diesel, cash', 'D', 0, 4, $acme, '7.5', 'sale', 'cash', '150', 4);
$ticket('Beta buys 3 x 10 kg Diesel on credit (bank, nothing paid)', 'D', 1, 3, $beta, '7.5', 'sale', 'bank', '0', 4);
$ticket('Cedar buys 3 x 5 kg Gasoline, cash', 'A', 0, 3, $cedar, '8.2', 'sale', 'cash', '123', 4);
$spot('Delta Taxi, spot sale 12.5 kg Diesel', 'D', '12.5', $delta, '7.5', '93.75', 4);
$ticket('ACME buys 2 x 5 kg Diesel, cash', 'D', 0, 2, $acme, '7.5', 'sale', 'cash', '75', 3);
$ticket('self use: 1 x 10 kg Diesel for the station', 'D', 1, 1, $self, '7.5', 'self', null, null, 3);
$spot('Delta Taxi, spot sale 8 kg Gasoline', 'A', '8', $delta, '8.2', '65.6', 3);
$ticket('Beta buys 4 x 5 kg Diesel on credit', 'D', 0, 4, $beta, '7.5', 'sale', 'bank', '0', 2);
$ticket('ACME buys 2 x 10 kg Diesel, cash', 'D', 1, 2, $acme, '7.5', 'sale', 'cash', '150', 2);
$ticket('Cedar buys 5 x 5 kg Diesel, bank, 100.00 paid of 187.50', 'D', 0, 5, $cedar, '7.5', 'sale', 'bank', '100', 1);
$ticket('given away: 1 x 5 kg Diesel to Delta Taxi', 'D', 0, 1, $delta, '7.5', 'other', null, null, 1);
$spot('ACME, spot sale 20 kg Diesel', 'D', '20', $acme, '7.5', '150', 1);
$ticket('ACME buys 3 x 5 kg Diesel, cash', 'D', 0, 3, $acme, '7.5', 'sale', 'cash', '112.5', 0);
$ticket('Beta buys 1 x 10 kg Diesel, cash', 'D', 1, 1, $beta, '7.5', 'sale', 'cash', '75', 0);
$spot('Cedar, spot sale 5 kg Gasoline', 'A', '5', $cedar, '8.2', '41', 0);
$ticket('Delta Taxi takes 2 x 5 kg on a ticket loan', 'D', 0, 2, $delta, '7.5', 'ticket_loan', null, null, 0);

section('a wrong spot sale is entered and deleted', 'wrong');
if ($spot('Delta Taxi, spot sale 3 kg Gasoline (wrong)', 'A', '3', $delta, '8.2', '24.6', 0)) {
    $wrong = one("SELECT id FROM sales WHERE sale_type = 'spot' AND amount_kg = 3 ORDER BY created_at DESC LIMIT 1");
    act('the wrong sale is deleted', $acct, 'DELETE', '/ticket-sales/' . $wrong, [], '/spot-sales');
}

section('payments by the accountant', 'payments');
$betaDebt = one("SELECT d.id FROM debts d JOIN sales s ON s.id = d.sale_id WHERE d.customer_id = ? AND s.sale_date = ? LIMIT 1", [$beta, day(4)]);
$cedarOpening = one("SELECT id FROM debts WHERE customer_id = ? AND sale_id IS NULL LIMIT 1", [$cedar]);
act(day(2) . ' Beta pays 100.00 (bank) on its debt of ' . day(4), $acct, 'POST', '/payments', ['customer_id' => $beta, 'debt_id' => $betaDebt, 'amount' => '100', 'payment_method' => 'bank', 'paid_date' => day(2), 'note' => 'transfer received'], '/payments');
act(day(1) . ' Cedar pays 150.00 (cash) on its opening debt', $acct, 'POST', '/payments', ['customer_id' => $cedar, 'debt_id' => $cedarOpening, 'amount' => '150', 'payment_method' => 'cash', 'paid_date' => day(1)], '/payments');
act(day(0) . ' Beta pays the other 125.00 (bank), the debt of ' . day(4) . ' is settled', $acct, 'POST', '/payments', ['customer_id' => $beta, 'debt_id' => $betaDebt, 'amount' => '125', 'payment_method' => 'bank', 'paid_date' => day(0)], '/payments');

section('supplies typed in on the web (the customers fill up)', 'supplies');
$noz1 = one("SELECT id FROM nozzles WHERE number = 1");
$noz2 = one("SELECT id FROM nozzles WHERE number = 2");
$barcodes = function ($customer, $back, $spec, $n) {
    return implode(' ', array_slice(array_column(rows("SELECT t.barcode FROM tickets t JOIN sales s ON s.id = t.sale_id WHERE s.customer_id = ? AND s.sale_date = ? AND s.sale_type = 'ticket' AND t.spec = ? AND t.status = 1 AND s.deleted_at IS NULL ORDER BY t.serial", [$customer, day($back), $spec]), 'barcode'), 0, $n));
};
act(day(3) . ' ACME fills 7.50 kg of its two 5 kg tickets (a remainder ticket of 2.50 kg is made)', $admin, 'POST', '/supplements', ['ticket_barcodes' => $barcodes($acme, 4, 0, 2), 'nozzle_id' => $noz2, 'out_amount_lt' => '9.04', 'supplied_at' => day(3) . ' 10:15:00', 'mode' => 'manual'], '/supplements');
act(day(2) . ' Beta fills a 10 kg ticket, exactly', $admin, 'POST', '/supplements', ['ticket_barcodes' => $barcodes($beta, 4, 1, 1), 'nozzle_id' => $noz2, 'out_amount_lt' => '12.05', 'supplied_at' => day(2) . ' 14:40:00', 'mode' => 'manual'], '/supplements');
$remainder = one("SELECT ticket_barcode FROM remainders ORDER BY created_at LIMIT 1");
act(day(1) . ' ACME uses the remainder ticket of 2.50 kg', $admin, 'POST', '/supplements', ['ticket_barcodes' => $remainder, 'nozzle_id' => $noz2, 'out_amount_lt' => '3.01', 'supplied_at' => day(1) . ' 09:20:00', 'mode' => 'manual'], '/supplements');
act(day(1) . ' Cedar fills Gasoline with a 5 kg ticket (4.59 kg: a remainder is made)', $admin, 'POST', '/supplements', ['ticket_barcodes' => $barcodes($cedar, 4, 0, 1), 'nozzle_id' => $noz1, 'out_amount_lt' => '6.20', 'supplied_at' => day(1) . ' 16:05:00', 'mode' => 'manual'], '/supplements');

section('a customer brings a ticket back (a return) and a ticket loan is settled', 'returns');
$returnSale = one("SELECT id FROM sales WHERE customer_id = ? AND sale_date = ? AND sale_type = 'ticket' AND `usage` = 'sale' AND reverses_sale_id IS NULL ORDER BY created_at LIMIT 1", [$acme, day(3)]);
$returnTicket = one("SELECT barcode FROM tickets WHERE sale_id = ? AND status = 1 LIMIT 1", [$returnSale]);
act('ACME brings back 1 of the tickets bought on ' . day(3), $assistant, 'POST', '/ticket-sales/' . $returnSale . '/return', ['ticket_barcodes' => $returnTicket, 'comment' => 'bought the wrong size'], '/ticket-sales');
$loan = one("SELECT id FROM ticket_loans WHERE sale_id IS NULL AND customer_id = ? LIMIT 1", [$delta]);
$settleTicket = one("SELECT t.barcode FROM tickets t JOIN sales s ON s.id = t.sale_id WHERE s.customer_id = ? AND t.status = 1 AND t.spec = 0 AND s.deleted_at IS NULL ORDER BY t.serial DESC LIMIT 1", [$beta]);
act('Delta Taxi settles 5 kg of its opening loan with a ticket', $acct, 'POST', '/loans', ['loan_id' => $loan, 'ticket_barcode' => $settleTicket], '/loans');

section('the accountant confirms the first days (the last two days stay open to try)', 'confirm');
foreach ([5, 4, 3, 2] as $back) {
    $d = day($back);
    $saleIds = array_column(rows('SELECT id FROM sales WHERE sale_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$d]), 'id');
    $supplyIds = array_column(rows('SELECT id FROM supplements WHERE supply_date = ? AND confirmed_at IS NULL AND deleted_at IS NULL', [$d]), 'id');
    if ($saleIds || $supplyIds) {
        act("$d: " . count($saleIds) . ' sales and ' . count($supplyIds) . ' supplements confirmed', $acct, 'POST', '/confirmations', ['date' => $d, 'sales' => $saleIds, 'supplements' => $supplyIds, 'from' => 'sales'], '/confirmations');
    }
}

section('what is in the database now', 'summary');
foreach (['users' => 'users', 'customers' => 'customers', 'tanks' => 'tanks', 'nozzles' => 'nozzles', 'fuel inbounds' => 'fuel_inbounds', 'ticket inbounds' => 'ticket_inbounds',
    'tickets' => 'tickets', 'sales (with returns)' => 'sales', 'debts' => 'debts', 'debt payments' => 'debt_payments', 'ticket loans' => 'ticket_loans',
    'loan settlements' => 'ticket_loan_settlements', 'supplements' => 'supplements', 'remainders' => 'remainders'] as $label => $table) {
    echo '  ' . str_pad($label, 24) . one("SELECT COUNT(*) FROM `$table`") . "\n";
}
echo "\n$done actions done, $failures failed.\n";
echo "Logins: admin / admin123; manager1, accountant1, assistant1, cashier1 / secret1\n";
exit($failures ? 1 : 0);
