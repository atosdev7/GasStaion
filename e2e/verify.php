<?php

// Compares what the web app's database holds with what the terminal reported (facts.json), and checks the numbers of the scenario that no
// terminal could know by itself (tank stock, ticket weights, totals).
//
//   php verify.php <scenario> <facts.json> [<dev-fingerprint-before.json>]

require __DIR__ . '/common.php';

use Illuminate\Support\Facades\DB;

$scenario = $argv[1] ?? 'basic';
$facts = json_decode(file_get_contents($argv[2]), true);
$devBefore = isset($argv[3]) ? json_decode(file_get_contents($argv[3]), true) : null;

$checks = 0;
$failures = 0;

function ok($condition, $what)
{
    global $checks, $failures;
    ++$checks;
    if (!$condition) {
        ++$failures;
    }
    echo ($condition ? '  ok    ' : '  FAIL  ') . $what . "\n";
}

function same($server, $localX100, $scale = 100)
{
    return $server !== null && abs((float) $server - $localX100 / $scale) < 0.005;
}

function key16($number)
{
    return sprintf('%016d', $number);
}

function section($title)
{
    echo "\n== $title\n";
}

// ---- the ledger of a card: the web app and the terminal hold the same entries and balances ----

if ($scenario === 'cards') {
    section('the card');
    $card = DB::table('cards')->where('id', $facts['card_id'])->first();
    ok($card && $card->uid === 'E2ECARD01' && $card->holder_name === 'Li Wei' && $card->holder_phone === '138 0000 1111' && $card->fuel_type === 'D' && $card->status === 'active',
        'the card registered on the terminal is on the web app, with its holder and phone number');

    section('the ledger on the web app');
    $web = DB::table('card_transactions')->where('card_id', $facts['card_id'])->orderBy('occurred_at')->orderBy('seq')->get();
    ok($web->count() === 5, 'five entries: 2 top-ups and a fill from the terminal, a top-up and a reversal from the web (' . $web->count() . ')');
    ok($web->pluck('type')->all() === ['topup', 'topup', 'fill', 'topup', 'reversal'], 'in the order they happened: top-up, top-up, fill, top-up, reversal (' . implode(', ', $web->pluck('type')->all()) . ')');
    $kg = $web->map(function ($e) { return (float) $e->kg; })->all();
    ok($kg === [40.0, 10.0, -20.0, 5.0, -10.0], 'the kg in and out: +40, +10, -20, +5, -10');
    $running = $web->map(function ($e) { return (float) $e->balance_kg; })->all();
    ok($running === [40.0, 50.0, 30.0, 35.0, 25.0], 'the running balances: 40, 50, 30, 35, 25 (' . implode(', ', $running) . ')');
    ok(abs(App\Support\Cards\CardLedger::balance($facts['card_id']) - 25) < 0.005, 'the balance of the card is 25.00');
    $topUp = $web->first();
    ok(abs((float) $topUp->price - 7.5) < 0.005 && abs((float) $topUp->total_amount - 300) < 0.005, 'the first top-up keeps its price and what was paid: 7.50 and 300.00');
    ok($web[4]->source_id === $facts['topup_sale'] && abs((float) $web[4]->kg + 10) < 0.005, 'the reversal names the top-up that was deleted');

    section('the records behind it');
    $deleted = DB::table('sales')->where('id', $facts['topup_sale'])->first();
    ok($deleted && $deleted->deleted_at !== null, 'the deleted top-up is still a row (deleted), and the ledger keeps both entries');
    $ticket = DB::table('tickets')->where('card_id', $facts['card_id'])->first();
    ok($ticket && abs((float) $ticket->amount_kg - 20) < 0.005 && (int) $ticket->status === (int) config('ticket_statuses.supply') && $ticket->sale_id === null && $ticket->code === App\Support\Tickets\Barcode::posCode('card', 'D'),
        'the fill of 20.00 kg is a ticket made for the card (no sale), supplied');
    $supply = DB::table('supplements')->where('id', $ticket->supplement_id ?? 'none')->first();
    ok($supply && abs((float) $supply->out_amount_kg - 20) < 0.005 && $supply->card_id === null, 'the fuel that came out for it is an ordinary supplement of 20.00 kg');
    $card = App\Models\Cards\Card::find($facts['card_id']);
    ok(abs($card->balance() - $card->balanceFromRecords()) < 0.005, 'the ledger agrees with the records that are left (' . $card->balance() . ')');

    section('the terminal holds the same ledger');
    $local = $facts['ledger'];
    ok(count($local) === 5, 'the terminal holds five entries (' . count($local) . ')');
    $same = true;
    foreach ($web as $i => $entry) {
        $l = $local[$i] ?? null;
        $same = $same && $l && $l['id'] === $entry->id && $l['type'] === $entry->type && same($entry->kg, $l['kg']) && same($entry->balance_kg, $l['balance_kg']) && $l['source_id'] === $entry->source_id;
    }
    ok($same, 'the same entries, kg and balances, in the same order');
    ok((int) $facts['card_balance'] === 2500, 'the card holds 25.00 kg on the terminal, as on the web app');

    section('the web app on its own');
    ok(DB::table('sync_errors')->count() === 0, 'no sync errors were logged');
    ok((int) $facts['pending'] === 0, 'the terminal has nothing left to upload');

    if ($devBefore !== null) {
        section('the dev database');
        ok(e2e_dev_fingerprint() === $devBefore, 'the dev database `gasstation` was not touched');
    }
    echo "\n" . ($checks - $failures) . " of $checks checks passed\n";
    exit($failures > 0 ? 1 : 0);
}

// ---- five cards: every ledger and balance of the web app is what the terminal worked out, and the records behind them agree ----

if ($scenario === 'cardsmany') {
    section('the cards');
    ok(DB::table('cards')->count() === 5, 'five cards are on the web app (' . DB::table('cards')->count() . ')');
    foreach ($facts['cards'] as $f) {
        $card = App\Models\Cards\Card::find($f['id']);
        ok($card && $card->uid === $f['uid'] && $card->fuel_type === 'D' && $card->status === 'active' && !$card->free_payment, $f['uid'] . ' is registered, active and a normal card');
    }

    section('the ledger of every card on the web app');
    foreach ($facts['cards'] as $n => $f) {
        $web = DB::table('card_transactions')->where('card_id', $f['id'])->orderBy('occurred_at')->orderBy('seq')->get();
        ok($web->count() === $f['entries'], $f['uid'] . ' has ' . $f['entries'] . ' ledger entries (' . $web->count() . ')');
        $balance = App\Support\Cards\CardLedger::balance($f['id']);
        ok(abs($balance * 100 - $f['expected_x100']) < 0.5, $f['uid'] . ' holds ' . number_format($f['expected_x100'] / 100, 2) . ' kg (' . number_format($balance, 2) . ')');
        $card = App\Models\Cards\Card::find($f['id']);
        ok(abs($card->balance() - $card->balanceFromRecords()) < 0.005, $f['uid'] . ': the ledger agrees with the records that are left');
        $last = $web->last();
        ok($last && abs((float) $last->balance_kg - $balance) < 0.005, $f['uid'] . ': the running balance after the last entry is the balance');
        $negative = $web->filter(function ($e) { return (float) $e->balance_kg < -0.004; })->count();
        ok($negative === 0, $f['uid'] . ': the balance never fell below zero');
        $topups = DB::table('sales')->where('card_id', $f['id'])->where('sale_type', 'card')->whereNull('deleted_at')->count();
        $fills = DB::table('tickets')->where('card_id', $f['id'])->where('status', '<>', config('ticket_statuses.restrict'))->count();
        $wantedFills = $n === 0 ? 4 : 3;                                   // card 1 has the ticket that was typed in at the manual nozzle too
        ok($fills === $wantedFills, $f['uid'] . ' has ' . $wantedFills . ' tickets made for it (' . $fills . ')');
        $wanted = [3, 2, 4, 3, 4][$n];                            // card 2 lost its first top-up; cards 3 and 5 got one from the web
        ok($topups === $wanted, $f['uid'] . ' has ' . $wanted . ' top-up sales left (' . $topups . ')');
    }

    section('the ticket typed in for the manual nozzle');
    $card1 = $facts['cards'][0]['id'];
    $manualTicket = DB::table('tickets')->where('card_id', $card1)->where('amount_kg', 5)->first();
    $manual = DB::table('supplements')->where('id', $manualTicket->supplement_id ?? 'none')->first();
    ok($manual && $manual->mode === 'manual' && abs((float) $manual->out_amount_kg - 5) < 0.005, 'the ticket of 5.00 kg of card 1 was used by a supplement in manual mode, 5.00 kg');
    ok($manual && (float) $manual->density > 0 && abs((float) $manual->out_amount_lt - round(5 / (float) $manual->density, 2)) < 0.011, 'its litres follow from the kg and the density of the nozzle');
    ok($manual && DB::table('remainders')->where('supplement_id', $manual->id)->count() === 0, 'it has no remainder');
    $autoFills = DB::table('tickets')->where('card_id', $card1)->where('amount_kg', '<>', 5)->pluck('supplement_id')->all();
    ok(DB::table('supplements')->whereIn('id', $autoFills)->where('mode', 'auto')->count() === 3, 'the three fills with the auto nozzle are in auto mode');
    section('what the web app did');
    $deleted = DB::table('sales')->where('id', $facts['deleted_sale'])->first();
    ok($deleted && $deleted->deleted_at !== null, 'the deleted top-up is still a row (deleted)');
    ok(DB::table('card_transactions')->where('type', 'reversal')->where('source_id', $facts['deleted_sale'])->count() === 1, 'one reversal names the deleted top-up');
    ok(DB::table('sales')->where('sale_type', 'card')->whereNull('deleted_at')->count() === 15 - 1 + 2, 'card sales left: 15 from the terminal, one deleted, two made on the web');

    section('the terminal holds the same ledger');
    $webAll = DB::table('card_transactions')->orderBy('card_id')->orderBy('occurred_at')->orderBy('seq')->get();
    $local = $facts['ledger'];
    ok(count($local) === $webAll->count() && $webAll->count() === 34, 'the same number of entries on both sides (' . count($local) . ' and ' . $webAll->count() . ')');
    $same = count($local) === $webAll->count();
    foreach ($webAll as $i => $entry) {
        $l = $local[$i] ?? null;
        $same = $same && $l && $l['id'] === $entry->id && $l['type'] === $entry->type && same($entry->kg, $l['kg']) && same($entry->balance_kg, $l['balance_kg']) && $l['source_id'] === $entry->source_id
            && ($entry->total_amount === null || same($entry->total_amount, $l['total_amount']));   // the money of a top-up too (half cents)
    }
    ok($same, 'the same entries, kg and balances, in the same order');

    section('the web app on its own');
    ok(DB::table('sync_errors')->count() === 0, 'no sync errors were logged');
    ok((int) $facts['pending'] === 0, 'the terminal has nothing left to upload');

    if ($devBefore !== null) {
        section('the dev database');
        ok(e2e_dev_fingerprint() === $devBefore, 'the dev database `gasstation` was not touched');
    }
    echo "\n" . ($checks - $failures) . " of $checks checks passed\n";
    exit($failures > 0 ? 1 : 0);
}

// ---- the day: the terminal program already ran this very comparison (as "basic") before the web staff changed things; only the dev database is checked here ----

if ($scenario === 'day') {
    if ($devBefore !== null) {
        section('the dev database');
        ok(e2e_dev_fingerprint() === $devBefore, 'the dev database `gasstation` was not touched');
    }
    echo "\n" . ($checks - $failures) . " of $checks checks passed\n";
    exit($failures > 0 ? 1 : 0);
}

// ---- every record of the terminal is on the server, with the same values ----

section('sales');
$sales = DB::table('sales')->get()->keyBy('id');
$localSales = array_values(array_filter($facts['sales'], function ($s) { return $s['deleted_at'] === null; }));
ok($sales->count() === count($localSales), 'the web app holds ' . count($localSales) . ' sales (' . $sales->count() . ')');
foreach ($localSales as $l) {
    $s = $sales->get($l['id']);
    $good = $s && $s->sale_type === $l['sale_type'] && $s->usage === $l['usage'] && $s->fuel_type === $l['fuel_type']
        && same($s->price, $l['price']) && same($s->amount_kg, $l['amount_kg']) && same($s->total_amount, $l['total_amount'])
        && same($s->paid_amount, $l['paid_amount']) && same($s->unpaid_amount, $l['unpaid_amount'])
        && (int) $s->customer_id === (int) $l['customer_id'] && $s->comment === $l['comment'] && $s->sale_date === $l['sale_date']
        && (string) $s->created_at === $l['created_at'] && $s->sale_date !== null && $s->cost !== null && (float) $s->cost > 0;
    ok($good, "sale {$l['usage']} {$l['amount_kg']}: same values, date " . ($s ? $s->sale_date : '-') . ', cost filled');
}

section('supplements and remainders');
$supplements = DB::table('supplements')->join('nozzles', 'nozzles.id', '=', 'supplements.nozzle_id')
    ->select('supplements.*', 'nozzles.number as nozzle_number')->get()->keyBy('id');
ok($supplements->count() === count($facts['supplements']), 'the web app holds ' . count($facts['supplements']) . ' supplements (' . $supplements->count() . ')');
foreach ($facts['supplements'] as $l) {
    $s = $supplements->get($l['id']);
    $tickets = DB::table('tickets')->where('supplement_id', $l['id'])->get();
    $weight = 0;
    foreach ($tickets as $t) {
        $weight += $t->amount_kg !== null ? (float) $t->amount_kg : (float) config('ticket_specs')[$t->spec];
    }
    $good = $s && $s->fuel_type === $l['fuel_type'] && (int) $s->nozzle_number === (int) $l['nozzle_number']
        && same($s->out_amount_lt, $l['out_amount_lt']) && same($s->out_amount_kg, $l['out_amount_kg']) && same($s->preset_kg, $l['preset_kg'])
        && same($s->density, $l['density'], 10000) && $s->mode === $l['mode'] && $s->comment === $l['comment'] && $s->supply_date === $l['supply_date']
        && $s->device_id !== null && abs($weight - $l['preset_kg'] / 100) < 0.005;
    ok($good, "supplement nozzle {$l['nozzle_number']} " . ($l['out_amount_kg'] / 100) . ' kg: same values, its tickets weigh ' . $weight . ' kg = what it covers');
}
$remainders = DB::table('remainders')->get()->keyBy('id');
ok($remainders->count() === count($facts['remainders']), 'the web app holds ' . count($facts['remainders']) . ' remainders (' . $remainders->count() . ')');
foreach ($facts['remainders'] as $l) {
    $r = $remainders->get($l['id']);
    $ticket = $r ? DB::table('tickets')->where('barcode', $r->ticket_barcode)->first() : null;
    ok($r && $r->supplement_id === $l['supplement_id'] && $r->ticket_barcode === key16($l['ticket_barcode']) && same($r->amount_kg, $l['amount_kg'])
        && $ticket && $ticket->code === '29' && $ticket->serial > 500000000 && abs((float) $ticket->amount_kg - $l['amount_kg'] / 100) < 0.005,
        'remainder ' . ($l['amount_kg'] / 100) . ' kg: its ticket has the diesel remainder code 29 and a serial in the Smart210 range');
}

section('ticket loans and settlements');
$loans = DB::table('ticket_loans')->get()->keyBy('id');
foreach ($facts['loans'] as $l) {
    $s = $loans->get($l['id']);
    $sum = (float) DB::table('ticket_loan_settlements')->where('loan_id', $l['id'])->sum('amount_kg');
    ok($s && same($s->amount_kg, $l['amount_kg']) && (int) $s->customer_id === (int) $l['customer_id'] && abs((float) $s->settled_amount_kg - $sum) < 0.005
        && same($s->settled_amount_kg, $l['settled_amount_kg']), 'loan ' . ($l['amount_kg'] / 100) . ' kg: settled ' . $sum . ' kg, the same sum on both sides');
}
$settlements = DB::table('ticket_loan_settlements')->get()->keyBy('id');
ok($settlements->count() === count($facts['settlements']), 'the web app holds ' . count($facts['settlements']) . ' settlements (' . $settlements->count() . ')');
foreach ($facts['settlements'] as $l) {
    $s = $settlements->get($l['id']);
    ok($s && $s->loan_id === $l['loan_id'] && $s->ticket_barcode === key16($l['ticket_barcode']) && same($s->amount_kg, $l['amount_kg']) && $s->date === $l['date'],
        'settlement with ticket ' . key16($l['ticket_barcode']));
}

section('tickets');
$statusSale = config('ticket_statuses.sale');
$statusSupply = config('ticket_statuses.supply');
$tickets = DB::table('tickets')->get()->keyBy('barcode');
$bad = [];
foreach ($facts['tickets'] as $l) {
    $t = $tickets->get(key16($l['barcode']));
    $good = $t && (int) $t->status === (int) $l['status'] && $t->supplement_id === $l['supplement_id'] && ($l['status'] != $statusSale || $t->sale_id === $l['sale_id']);
    if (!$good) {
        $bad[] = key16($l['barcode']);
    }
}
ok(count($bad) === 0, count($facts['tickets']) . ' tickets sold or supplied: the same status, sale and supplement on both sides' . ($bad ? ' (differ: ' . implode(', ', $bad) . ')' : ''));

if ($scenario === 'basic') {
    // the old paper ticket the terminal supplied: its row was made on the web, dated as its publish (published before go-live, sold on paper, now supplied)
    $old = $tickets->get(key16(6000000801));
    $publish = DB::table('ticket_publishes')->where('code', '06')->where('start_serial', 801)->first();
    ok($old && (int) $old->status === $statusSupply && $old->supplement_id !== null, 'the old ticket 801 is supplied on the web app too');
    ok($old && $publish && (int) $old->publish_id === (int) $publish->id && substr((string) $old->publish_date, 0, 10) === substr((string) $publish->date, 0, 10)
        && substr((string) $old->sale_date, 0, 10) === substr((string) $publish->date, 0, 10), 'its row has the publish date of its publish as its publish date and its sale date');
    ok(DB::table('tickets')->whereBetween('serial', [802, 810])->where('code', '06')->count() === 0, 'the other old tickets still have no rows');
}

section('the web app on its own');
ok(DB::table('sync_errors')->count() === 0, 'no sync errors were logged');
ok((int) $facts['pending'] === 0, 'the terminal has nothing left to upload');

if ($scenario === 'basic') {
    section('the numbers of the shift');
    ok(DB::table('sales')->count() === 6, '6 sales: 4 spot + 2 ticket sales');
    foreach (['sale' => 2, 'self' => 2, 'other' => 1, 'ticket_loan' => 1] as $use => $n) {
        ok(DB::table('sales')->where('usage', $use)->count() === $n, "$n sales for use '$use'");
    }
    ok(abs((float) DB::table('sales')->where('usage', 'sale')->sum('total_amount') - 191.25) < 0.005, 'sold for 191.25 (78.75 + 112.50)');
    ok(abs((float) DB::table('sales')->where('usage', 'sale')->sum('paid_amount') - 191.25) < 0.005, 'all of it paid');
    ok(DB::table('tickets')->whereIn('code', ['21', '22', '23', '24'])->where('status', $statusSale)->count() === 4, 'the four spot sales have their tickets (diesel: 21 spot sale, 22 self use, 23 other use, 24 ticket loan), sold');
    foreach ([1, 2, 3, 101] as $n) {
        ok(DB::table('tickets')->where('barcode', sprintf('0000006%09d', $n))->where('status', $statusSale)->whereNotNull('sale_id')->exists(), "ticket $n of the stock is sold, with its sale");
    }
    ok(DB::table('tickets')->where('status', $statusSupply)->count() === 15, '15 tickets are supplied: 7 for loans, 7 at the nozzles, 1 old paper ticket');
    ok(DB::table('ticket_loan_settlements')->count() === 7, '7 settlements');
    ok(DB::table('supplements')->count() === 5 && DB::table('remainders')->count() === 1, '5 supplements and 1 remainder');
    $amounts = DB::table('supplements')->orderBy('out_amount_kg')->pluck('out_amount_kg')->map(function ($v) { return (float) $v; })->all();
    ok($amounts === [5.0, 5.0, 10.0, 12.0, 23.0], 'the supplements dispensed 5, 5, 10, 12 and 23 kg');
    ok(DB::table('supplements')->where('mode', 'manual')->count() === 2, 'two manual supplies: one at the manual dispenser, one reading typed in');
    ok(DB::table('supplements')->where('out_amount_kg', 12)->where('preset_kg', 30)->exists(), 'the 12 kg supply had 30 kg of tickets (the rest is the remainder)');
    $remainderTicket = DB::table('remainders')->first();
    ok($remainderTicket && DB::table('tickets')->where('barcode', $remainderTicket->ticket_barcode)->where('status', $statusSupply)->exists(), 'the remainder ticket was used in the next supply');
    $items = DB::table('sale_items')->orderBy('start_serial')->get();
    ok($items->count() === 2, 'the two ticket sales brought their items (' . $items->count() . ')');
    ok($items->count() === 2 && (int) $items[0]->spec === 0 && (int) $items[0]->start_serial === 1 && (int) $items[0]->end_serial === 3
        && (int) $items[0]->quantity === 3 && abs((float) $items[0]->amount_kg - 15) < 0.005
        && (int) $items[1]->spec === 1 && (int) $items[1]->start_serial === 101 && (int) $items[1]->end_serial === 101
        && (int) $items[1]->quantity === 1 && abs((float) $items[1]->amount_kg - 10) < 0.005,
        'the items are the ones entered: 3 x 5 kg from serial 1 to 3, and 1 x 10 kg at serial 101');
    ok(DB::table('sales')->where('comment', 'paid by card')->count() === 1, 'the comment on the first sale arrived');
    ok(DB::table('supplements')->where('comment', 'ticket check done')->count() === 1, 'the comment on the manual supply arrived (an edit after the upload)');
    $levels = App\Support\Dashboard\TankLevels::all()->first();
    ok($levels && abs($levels->current - 99945) < 0.005, 'the tank holds 99945 kg: 100000 in, 55 supplied (' . ($levels ? $levels->current : '-') . ')');
}

if ($scenario === 'restore') {
    section('after the restore');
    ok(DB::table('sales')->count() === count(array_filter($facts['sales'], function ($s) { return $s['deleted_at'] === null; })),
        'every sale of the terminal is on the restored server (the ones uploaded after the dump came back)');
    ok(DB::table('settings')->where('key', 'restored_at')->exists(), 'the restore marker is set');
    $remainders = DB::table('remainders')->count();
    ok($remainders === count($facts['remainders']) && $remainders >= 1, 'the remainder of the supply made after the dump is back, with its ticket');
}

if ($scenario === 'offline') {
    section('the four days of work without a network');
    $base = $facts['base'];
    foreach ([3, 2, 1] as $d) {
        $day = date('Y-m-d', strtotime("$base -$d days"));
        $sold = DB::table('sales')->where('sale_date', $day)->where('usage', 'sale')->sum('total_amount');
        $expected = 2 * (5 + $d) * 7.5 + 5 * 7.5; // two spot sales and a 5 kg ticket
        ok(DB::table('sales')->where('sale_date', $day)->count() === 4, "$day: 4 sales");
        ok(abs((float) $sold - $expected) < 0.005, "$day: sold for $expected (" . $sold . ')');
        // the fills of the restarts are stamped with the real clock: before 9:00 they belong to yesterday's business day (3 fills: 4, 3 and 10 kg)
        $expectedFills = ($d === 1 && (int) date('G') < 9) ? 4 : 1;
        ok(DB::table('supplements')->where('supply_date', $day)->count() === $expectedFills, "$day: $expectedFills supplement(s)");
    }
    $stopped = DB::table('supplements')->where('supply_date', date('Y-m-d', strtotime("$base -2 days")))->first();
    ok($stopped && abs((float) $stopped->out_amount_kg - 10) < 0.005 && DB::table('remainders')->where('supplement_id', $stopped->id)->exists(), 'the supply that stopped at 10 of 15 kg has its remainder');
    ok(DB::table('sales')->where('sale_date', $base)->count() >= 4, 'today has its sales');

    section('faults and power cuts left no trace on the server');
    $fills = DB::table('supplements')->whereIn('out_amount_kg', [4, 3, 10])->count();
    ok($fills >= 3, 'the fills of the restarts and the power cut were recorded once each');
    $levels = App\Support\Dashboard\TankLevels::all()->first();
    $supplied = (float) DB::table('supplements')->sum('out_amount_kg');
    ok($levels && abs($levels->current - (100000 - $supplied)) < 0.005, 'the tank holds 100000 minus everything supplied (' . ($levels ? $levels->current : '-') . ')');
}

if ($devBefore !== null) {
    section('the dev database');
    ok(e2e_dev_fingerprint() === $devBefore, 'the dev database `gasstation` was not touched');
}

echo "\n" . ($checks - $failures) . " of $checks checks passed\n";
exit($failures > 0 ? 1 : 0);
