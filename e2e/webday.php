<?php

// The web staff's half of a business day, done through the web app's own pages (login, forms, buttons) on the scratch database
// gasstation_e2e, after the terminal has uploaded its shift (the "day" scenario of e2e.cpp calls this):
//
//   php webday.php <host> <port>
//
// Output: "  ok    ..." / "  FAIL  ..." lines (a FAIL fails the run), "  ISSUE ..." lines (a known problem of REVIEW_2026-10-20.md that still
// shows: it does not fail the run) and "@key=value" lines that the terminal program reads back.

require __DIR__ . '/common.php';

use App\Models\Admin\User;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\TankBalance;
use App\Models\Fuel\Nozzle;
use App\Models\Sales\Customer;
use App\Models\Sales\Sale;
use App\Models\Supplements\Remainder;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Support\Dashboard\TankLevels;
use Illuminate\Support\Facades\DB;

$base = 'http://' . ($argv[1] ?? '127.0.0.1') . ':' . ($argv[2] ?? '8124');

$checks = 0;
$fails = 0;
$issues = 0;

function ok($condition, $what)
{
    global $checks, $fails;
    ++$checks;
    if (!$condition) {
        ++$fails;
    }
    echo ($condition ? '  ok    ' : '  FAIL  ') . $what . "\n";
}

// A rule of the review that the code does not keep yet: shown as ISSUE, never as a failure.
function expect($condition, $what)
{
    global $issues;
    if ($condition) {
        echo '  ok    ' . $what . "\n";
    } else {
        ++$issues;
        echo '  ISSUE ' . $what . "\n";
    }
}

function section($title)
{
    echo "\n== $title\n";
}

function note($key, $value)
{
    echo '@' . $key . '=' . $value . "\n";
}

require __DIR__ . '/WebClient.php';

function webUser($username, $role)
{
    return User::where('username', $username)->first() ?: User::forceCreate([
        'name' => ucfirst($username), 'username' => $username, 'password' => bcrypt('secret1'), 'role' => $role, 'active' => true,
    ]);
}

function money($v)
{
    return round((float) $v, 2);
}

webUser('wmgr', 'manager');
webUser('wacc', 'accountant');
webUser('wadm', 'admin');
webUser('wast', 'assistant');
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$acme = Customer::where('name', 'ACME')->value('id');
$beta = Customer::where('name', 'Beta Ltd')->value('id');
$nozzle = Nozzle::where('fuel_type', 'D')->orderBy('id')->first();

$stock0 = Ticket::where('fuel_type', 'D')->where('spec', 0)->where('status', config('ticket_statuses.publish'))->count();
ok($stock0 >= 6, "the shift left $stock0 five-kg tickets in stock");
$terminalSales = Sale::count();
ok($terminalSales > 0, "the terminal's shift is on the server ($terminalSales sales)");

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('the assistant sells tickets on credit to ACME (the web ticket sale form)');
$assistant = new Web($base);
ok($assistant->login('wast'), 'the assistant signs in');
$r = $assistant->send('POST', '/ticket-sales', [
    'sale_type' => 'ticket', 'fuel_type' => 'D', 'lines' => [['spec' => 0, 'quantity' => 2]], 'customer_id' => $acme, 'price' => '7.5',
    'usage' => 'sale', 'payment_method' => 'bank', 'paid_amount' => '0', 'sale_date' => $today,
]);
ok($r['status'] === 302 && $r['location'] === '/ticket-sales', 'the sale is saved (redirect to the list)');
$credit = Sale::where('customer_id', $acme)->where('sale_type', 'ticket')->where('usage', 'sale')->orderByDesc('created_at')->first();
ok($credit && money($credit->total_amount) === 75.0 && money($credit->unpaid_amount) === 75.0, '2 x 5 kg x 7.50 = 75.00, all of it unpaid');
$debt = $credit ? Debt::where('sale_id', $credit->id)->first() : null;
ok($debt && money($debt->amount) === 75.0, 'a debt of 75.00 was made for ACME');
$creditTickets = Ticket::where('sale_id', $credit->id)->orderBy('serial')->pluck('barcode')->all();
ok(count($creditTickets) === 2 && Ticket::where('sale_id', $credit->id)->where('status', config('ticket_statuses.sale'))->count() === 2, 'two tickets are sold to the sale');
ok(Ticket::where('sale_id', $credit->id)->where('synced', false)->count() === 2, 'they wait for the terminal (not synced)');

section('the accountant records a part payment of the debt');
$acct = new Web($base);
ok($acct->login('wacc'), 'the accountant signs in');
$r = $acct->send('POST', '/payments', ['customer_id' => $acme, 'debt_id' => $debt->id, 'amount' => '30', 'payment_method' => 'bank', 'paid_date' => $today]);
ok($r['status'] === 302 && $r['location'] === '/payments', 'the payment of 30.00 is saved');
ok(money(DebtPayment::where('debt_id', $debt->id)->sum('amount')) === 30.0, '30.00 are paid against the debt');
$page = $acct->get('/payments');
ok($page['status'] === 200 && strpos($page['body'], '45.00') !== false, 'the payments page shows the balance 45.00');

section('Beta Ltd buys one ticket on credit');
$r = $assistant->send('POST', '/ticket-sales', [
    'sale_type' => 'ticket', 'fuel_type' => 'D', 'lines' => [['spec' => 0, 'quantity' => 1]], 'customer_id' => $beta, 'price' => '7.5',
    'usage' => 'sale', 'payment_method' => 'bank', 'paid_amount' => '0', 'sale_date' => $today,
]);
$betaSale = Sale::where('customer_id', $beta)->where('sale_type', 'ticket')->orderByDesc('created_at')->first();
$betaDebt = Debt::where('sale_id', $betaSale->id)->first();
ok($betaDebt && money($betaDebt->amount) === 37.5, 'Beta owes 37.50');

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('the customer brings one ticket back: a return');
$back = $creditTickets[0];
$debtBefore = money($debt->amount);
$r = $assistant->send('POST', '/ticket-sales/' . $credit->id . '/return', ['ticket_barcodes' => $back, 'comment' => 'wrong spec']);
ok($r['status'] === 302, 'the return is saved');
$return = Sale::where('reverses_sale_id', $credit->id)->first();
ok($return && money($return->amount_kg) === -5.0 && money($return->total_amount) === -37.5, 'a counter entry of -5 kg / -37.50 was made');
ok(Ticket::where('barcode', $back)->value('status') == config('ticket_statuses.publish') && Ticket::where('barcode', $back)->value('sale_id') === null, 'the ticket is back in stock');
ok(money(Sale::find($credit->id)->total_amount) === 75.0, 'the original sale keeps its 75.00');
$debtNow = money(Debt::with('adjustments')->find($debt->id)->net_amount);   // the debt keeps its amount, the return is an adjustment
ok(money($debtNow - DebtPayment::where('debt_id', $debt->id)->sum('amount')) === 7.5, 'the customer still owes 7.50 (75 - 37.50 returned - 30 paid)');
$debtOwn = money(Debt::find($debt->id)->amount);
ok($debtOwn === $debtBefore && money(DB::table('debt_adjustments')->where('debt_id', $debt->id)->sum('amount')) === -37.5, 'the debt of the sale is still 75.00 as of its own day; the return is a separate entry of -37.50 (REVIEW A2)');
note('returned', $back);

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('a wrong spot sale is entered and deleted (by the accountant: the assistant has no spot sales)');
$r = $acct->send('POST', '/ticket-sales', [
    'sale_type' => 'spot', 'fuel_type' => 'D', 'amount_kg' => '3', 'customer_id' => $beta, 'price' => '7.5', 'usage' => 'sale', 'paid_amount' => '22.5', 'sale_date' => $today,
]);
ok($r['status'] === 302, 'the spot sale is saved');
$wrong = Sale::where('customer_id', $beta)->where('sale_type', 'spot')->orderByDesc('created_at')->first();
ok($wrong && money($wrong->total_amount) === 22.5, '3 kg x 7.50 = 22.50');
$wrongTicket = Ticket::where('sale_id', $wrong->id)->value('barcode');
$r = $acct->send('DELETE', '/ticket-sales/' . $wrong->id);
ok(Sale::onlyTrashed()->where('id', $wrong->id)->exists(), 'the sale is deleted (kept for review)');
ok(Ticket::where('barcode', $wrongTicket)->value('status') == config('ticket_statuses.restrict'), 'its ticket can no longer be used (restricted)');
note('restricted', $wrongTicket);

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('the customer comes with the second ticket: a supply typed in on the web (only the admin does that: the supplies are made on the Smart210)');
$used = $creditTickets[1];
$admin = isset($admin) ? $admin : new Web($base);
if (!isset($adminIn)) {
    $adminIn = $admin->login('wadm');
}
$r = $admin->send('POST', '/supplements', [
    'ticket_barcodes' => $used, 'nozzle_id' => $nozzle->id, 'out_amount_lt' => '5.99', 'supplied_at' => date('Y-m-d H:i:s'), 'mode' => 'manual',
]);
ok($r['status'] === 302, 'the supply is saved');
$supply = Supplement::where('mode', 'manual')->orderByDesc('created_at')->first();
ok($supply && money($supply->out_amount_kg) === 4.97 && money($supply->preset_kg) === 5.0, '5.99 L x 0.83 = 4.97 kg of the 5 kg ticket');
ok(Ticket::where('barcode', $used)->value('status') == config('ticket_statuses.supply'), 'the ticket is used');
$tiny = Remainder::where('supplement_id', $supply->id)->first();
ok($tiny && money($tiny->amount_kg) === 0.03, 'a fill 0.03 kg short of its ticket leaves a remainder ticket of 0.03 kg (there is no tolerance)');
ok(substr((string) Ticket::where('barcode', $used)->value('supply_date'), 0, 10) === substr((string) $supply->supply_date, 0, 10), 'the ticket and the supply have the same day');
note('used', $used);

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('payments: a date ahead is refused, a payment over the debt is kept with a credit, a wrong payment is reversed (REVIEW A3)');
$r = $acct->send('POST', '/payments', ['customer_id' => $beta, 'amount' => '1', 'payment_method' => 'cash', 'paid_date' => date('Y-m-d', strtotime('+30 days'))]);
ok(DebtPayment::where('customer_id', $beta)->where('paid_date', '>', $today)->count() === 0, 'a payment dated 30 days ahead is refused');
$before = DebtPayment::where('debt_id', $betaDebt->id)->count();
$r = $acct->send('POST', '/payments', ['customer_id' => $beta, 'debt_id' => $betaDebt->id, 'amount' => '100', 'payment_method' => 'bank', 'paid_date' => $today]);
$over = DebtPayment::where('debt_id', $betaDebt->id)->orderByDesc('id')->first();
ok(DebtPayment::where('debt_id', $betaDebt->id)->count() === $before + 1 && $over && money($over->amount) === 100.0, 'a payment of 100.00 on a debt of 37.50 is kept: the customer may pay ahead');
$net = money(Debt::with('adjustments')->find($betaDebt->id)->net_amount - DebtPayment::where('debt_id', $betaDebt->id)->sum('amount'));
ok($net < 0, 'the debt shows a credit (' . number_format(-$net, 2) . ')');
$r = $acct->send('POST', '/payments/' . $over->id . '/reverse', ['note' => 'typed by mistake']);
$rev = DebtPayment::where('reverses_payment_id', $over->id)->first();
ok($rev && money($rev->amount) === -100.0 && substr((string) $rev->paid_date, 0, 10) === $today, 'the wrong payment is reversed by a line of -100.00 dated today');
ok(money(DebtPayment::find($over->id)->amount) === 100.0 && DebtPayment::where('debt_id', $betaDebt->id)->count() === $before + 2, 'the wrong payment itself stays in the list');
$r = $acct->send('POST', '/payments/' . $over->id . '/reverse');
ok(DebtPayment::where('reverses_payment_id', $over->id)->count() === 1, 'it cannot be reversed twice');
// ---------------------------------------------------------------------------------------------------------------------------------------------
section('the accountant confirms the day (Daily Confirmation)');
$unconfirmed = Sale::whereNull('confirmed_at')->get();
$dates = $unconfirmed->pluck('sale_date')->map(function ($d) { return substr((string) $d, 0, 10); })->unique()->sort()->values()->all();
ok(count($dates) >= 1, 'records wait for confirmation on ' . implode(', ', $dates));
foreach (array_unique(array_merge($dates, Supplement::whereNull('confirmed_at')->pluck('supply_date')->map(function ($d) { return substr((string) $d, 0, 10); })->all())) as $date) {
    $saleIds = Sale::whereNull('confirmed_at')->where('sale_date', $date)->pluck('id')->all();
    $supplyIds = Supplement::whereNull('confirmed_at')->where('supply_date', $date)->pluck('id')->all();
    $r = $acct->send('POST', '/confirmations', ['date' => $date, 'sales' => $saleIds, 'supplements' => $supplyIds, 'from' => 'sales']);
    ok($r['status'] === 302, "$date: " . count($saleIds) . ' sales and ' . count($supplyIds) . ' supplements confirmed');
}
ok(Sale::whereNull('confirmed_at')->count() === 0 && Supplement::whereNull('confirmed_at')->count() === 0, 'nothing is left to confirm');
ok(Sale::onlyTrashed()->count() === 0, 'the deleted sale was erased for good with the confirmation');
$files = glob(storage_path('app/e2e-backups/daily/*'));
for ($i = 0; $i < 30 && !$files; ++$i) {
    sleep(1);
    $files = glob(storage_path('app/e2e-backups/daily/*'));
}
ok(count($files) >= 1, 'the dump of the confirmed day was made (' . count($files) . ' file)');

section('a confirmed sale is final');
$r = $assistant->send('PUT', '/ticket-sales/' . $credit->id, ['sale_date' => $today, 'comment' => 'changed after confirmation']);
ok(Sale::find($credit->id)->comment !== 'changed after confirmation', 'its comment cannot be changed');
$r = $assistant->send('DELETE', '/ticket-sales/' . $credit->id);
ok(Sale::find($credit->id) !== null, 'it cannot be deleted');
$terminalTicketSale = Sale::where('sale_type', 'ticket')->whereNotNull('user_id')->where('id', '<>', $credit->id)->where('id', '<>', $betaSale->id)->whereNull('reverses_sale_id')->where('usage', 'sale')->first();
if ($terminalTicketSale) {
    $spare = Ticket::where('sale_id', $terminalTicketSale->id)->where('status', config('ticket_statuses.sale'))->value('barcode');
    $before = money($terminalTicketSale->total_amount);
    $debtBefore = Debt::where('sale_id', $terminalTicketSale->id)->value('amount');
    if ($spare) {
        $r = $acct->send('POST', '/ticket-sales/' . $terminalTicketSale->id . '/return', ['ticket_barcodes' => $spare]); // (the cashier's sale: the accountant works on every sale, the assistant only on his own)
        $late = Sale::where('reverses_sale_id', $terminalTicketSale->id)->first();
        ok($late && $late->confirmed_at === null && money($late->total_amount) < 0 && substr((string) $late->sale_date, 0, 10) === $today, 'a return of a confirmed sale is a new entry dated today');
        ok(money(Sale::find($terminalTicketSale->id)->total_amount) === $before, 'the confirmed sale keeps its figures');
        if ($debtBefore !== null && $late && money($late->unpaid_amount) < 0) {
            // REVIEW A2: the return cuts the debt by a counter entry dated today, the debt keeps what the sale made
            ok(money(Debt::where('sale_id', $terminalTicketSale->id)->value('amount')) === money($debtBefore), 'the debt of the confirmed sale keeps its amount');
            ok(money(DB::table('debt_adjustments')->where('sale_id', $late->id)->sum('amount')) === money($late->unpaid_amount), 'the cut is a counter entry of the return (' . money($late->unpaid_amount) . ')');
        }
        note('returned2', $spare);
    }
}

section('the manager takes a confirmation back and the record can be edited again');
$manager = new Web($base);
ok($manager->login('wmgr'), 'the manager signs in');
$r = $assistant->send('POST', '/ticket-sales/' . $credit->id . '/unconfirm');
ok(Sale::find($credit->id)->confirmed_at !== null, 'the assistant cannot take a confirmation back');
$r = $manager->send('POST', '/ticket-sales/' . $credit->id . '/unconfirm');
ok(Sale::find($credit->id)->confirmed_at === null, 'the manager takes it back');
$r = $assistant->send('PUT', '/ticket-sales/' . $credit->id, ['sale_date' => $today, 'comment' => 'corrected']);
ok(Sale::find($credit->id)->comment === 'corrected', 'now the comment can be changed');
$r = $acct->send('POST', '/confirmations', ['date' => $today, 'sales' => [$credit->id], 'from' => 'sales']);
ok(Sale::find($credit->id)->confirmed_at !== null, 'and it is confirmed again');

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('who may open what');
$admin = isset($admin) ? $admin : new Web($base);
if (!isset($adminIn)) {
    $adminIn = $admin->login('wadm');
}
$status = function ($web, $path) {
    $p = $web->get($path);

    return $p['status'];
};
ok($status($acct, '/balances/go-live') === 403 && $status($acct, '/ticket-bulk') === 403, 'the accountant cannot open Go-live or Bulk Update');
ok($status($manager, '/balances/go-live') === 403 && $status($manager, '/ticket-bulk') === 403, 'nor can the manager');
ok($status($admin, '/balances/go-live') === 200 && $status($admin, '/ticket-bulk') === 200, 'the admin can');
ok($status($manager, '/users') === 200 && $status($acct, '/users') === 403 && $status($assistant, '/users') === 403, 'Users: the manager and the admin only');
ok($status($manager, '/payments/create') === 200 && $status($manager, '/loans/create') === 200, 'the manager records debt payments and settles ticket loans like the accountant');
ok($status($acct, '/sync') === 200 && $status($manager, '/sync') === 200 && $status($assistant, '/sync') === 403, 'the sync status: the accountant and the manager, not the assistant');
ok($status($assistant, '/reports/ordering') === 403 && $status($acct, '/reports/ordering') === 200, 'the Stock and ordering report: the accountant, not the assistant');
$edit = $manager->get('/users');
ok(strpos($edit['body'], '>wadm<') === false, 'the manager does not see the admin account in the list of users');

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('the pages the staff read');
$pages = ['/', '/ticket-sales', '/spot-sales', '/card-sales', '/supplements', '/remainders', '/payments', '/debt-summary', '/loans', '/cards', '/card-summary',
    '/ticket-summary', '/tickets/lookup', '/confirmations', '/confirmations/supplements', '/confirmations/summary', '/inbound', '/outbound', '/ticket-inbound',
    '/ticket-publish', '/audit', '/sync', '/balances/tanks', '/balances/ticket-sales', '/balances/debts', '/balances/loans', '/balances/remainders', '/users', '/devices', '/fuel',
    '/tanks', '/nozzles', '/balances/go-live', '/ticket-bulk'];
$bad = [];
foreach ($pages as $path) {
    $p = $acct->get($path);
    if ($p['status'] !== 200) {
        $admin = isset($admin) ? $admin : new Web($base);
        if (!isset($adminIn)) {
            $adminIn = $admin->login('wadm');
        }
        $p = $admin->get($path);
    }
    if ($p['status'] !== 200 || stripos($p['body'], 'Whoops') !== false || stripos($p['body'], 'Exception') !== false) {
        $bad[] = $path . ' (' . $p['status'] . ')';
    }
}
ok(!$bad, count($pages) . ' pages open without an error' . ($bad ? ': ' . implode(', ', $bad) : ''));
$from = date('Y-m-01');
$bad = [];
foreach (['sales', 'cash', 'profit', 'tickets', 'tanks', 'supply', 'ordering'] as $report) {
    $p = $acct->get("/reports/$report?from=$from&to=$today");
    $x = $acct->get("/reports/$report?from=$from&to=$today&export=xls");
    if ($p['status'] !== 200 || $x['status'] !== 200 || strpos($x['headers'], 'spreadsheetml') === false) {
        $bad[] = $report . ' (' . $p['status'] . '/' . $x['status'] . ')';
    }
}
$p = $acct->get("/reports/customer?from=$from&to=$today&customer_id=$acme");
if ($p['status'] !== 200) {
    $bad[] = 'customer (' . $p['status'] . ')';
}
ok(!$bad, 'the 8 reports open, and 7 of them export to xlsx' . ($bad ? ': ' . implode(', ', $bad) : ''));
$report = $acct->get("/reports/sales?from=$from&to=$today");
$kgNet = money(Sale::sum('amount_kg'));
$amountNet = money(Sale::sum('total_amount'));
$reportText = preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<script.*?<\/script>/s', '', $report['body'])));
ok(strpos($reportText, 'Total ') !== false && preg_match('/Total \d+ ' . preg_quote(number_format($kgNet, 2), '/') . ' ' . preg_quote(number_format($amountNet, 2), '/') . '/', $reportText) === 1,
    'the sales report totals the period net of returns: ' . number_format($kgNet, 2) . ' kg, ' . number_format($amountNet, 2) . ' sold');

section('the tank');
$levels = TankLevels::all()->first();
$expected = money(TankBalance::sum('balance') + FuelInbound::sum('amount_kg') - Supplement::sum('out_amount_kg'));
ok($levels && abs($levels->current - $expected) < 0.005, 'the tank holds opening + inbound - supplied = ' . number_format($expected, 2) . ' (' . ($levels ? number_format($levels->current, 2) : '-') . ')');
$tankBefore = $levels ? money($levels->current) : null;

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('the administrator makes the periodic backup up to yesterday');
$late = new Sale;
$late->forceFill([
    'customer_id' => $beta, 'sale_type' => 'spot', 'fuel_type' => 'D', 'price' => 7.5, 'amount_kg' => 2, 'payment_method' => 'cash', 'total_amount' => 15, 'paid_amount' => 15,
    'unpaid_amount' => 0, 'receipt' => false, 'usage' => 'sale', 'sale_date' => $yesterday, 'synced' => true, 'user_id' => User::where('username', 'wacc')->value('id'),
])->save();
$unconfirmedId = $late->id;
ok(Sale::whereNull('confirmed_at')->where('sale_date', $yesterday)->count() >= 1, 'a sale of yesterday is still waiting for the accountant');
$admin = isset($admin) ? $admin : new Web($base);
if (!isset($adminIn)) {
    $adminIn = $admin->login('wadm');
}
ok($adminIn, 'the administrator signs in');
// a backup is refused while a sale it would clear is not confirmed (REVIEW A1)
$owedBefore = round(Debt::sum('amount') - DebtPayment::sum('amount'), 2);
$r = $admin->send('POST', '/backup', ['up_to' => $yesterday]);
ok(DB::table('backups')->count() === 0 && Sale::where('id', $unconfirmedId)->exists(), 'the backup is refused while a sale of those days is not confirmed');
$r = $acct->send('POST', '/confirmations', ['date' => $yesterday, 'sales' => [$unconfirmedId], 'from' => 'sales']);
ok(Sale::find($unconfirmedId)->confirmed_at !== null, 'the accountant confirms it');
// and while a terminal has not uploaded since the business day ended; the terminal has uploaded since
$r = $admin->send('POST', '/backup', ['up_to' => $yesterday]);
$sinceDayEnd = date('Y-m-d H:i:s', strtotime($today . ' ' . config('backup.business_day_start_hour') . ':00') + 60);
if (DB::table('backups')->count() === 0) {
    // the clock of this run is before the end of that business day: the upload after it is simulated
    DB::table('devices')->update(['last_synced_at' => $sinceDayEnd]);
    $r = $admin->send('POST', '/backup', ['up_to' => $yesterday]);
}
$why = '';
if (DB::table('backups')->count() !== 1) {
    preg_match_all('/invalid-feedback[^>]*>\s*([^<]+)</', $r['page']['body'] ?? '', $em);
    $why = ' (' . trim(implode('; ', $em[1])) . ')';
}
ok($r['status'] === 302 && DB::table('backups')->count() === 1, 'the backup ran (export file + clearing)' . $why);
$export = glob(storage_path('app/e2e-backups/exports/*.zip'));
ok(count($export) === 1, 'the export file exists');
$levels = TankLevels::all()->first();
ok($levels && $tankBefore !== null && abs($levels->current - $tankBefore) < 0.005, 'the tank holds the same after the clearing (' . ($levels ? number_format($levels->current, 2) : '-') . ')');
// the debts and loans that are settled in full go with the backup, what is still owed stays: nothing changes in what is owed
ok(round(Debt::sum('amount') - DebtPayment::sum('amount'), 2) === $owedBefore, 'what is owed is the same after the clearing (' . number_format($owedBefore, 2) . ')');
ok(Debt::count() >= 1, 'the debts that still owe something are kept');
ok(!Sale::where('id', $unconfirmedId)->exists(), 'once confirmed, the sale is cleared by the backup');

note('published', Ticket::where('status', config('ticket_statuses.publish'))->count());

echo "\n$checks checks, " . ($checks - $fails) . " ok, $fails failed, $issues known issue(s)\n";
exit($fails > 0 ? 1 : 0);
