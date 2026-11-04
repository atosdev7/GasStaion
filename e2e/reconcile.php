<?php

// The reconciliation of a test day (TEST_PLAN.md 2.5), read-only: the web database against itself (money and kg add up, tanks, tickets, debts) and the
// terminal's own database against the web (every terminal record is on the web with the same values).
//
//   php e2e\reconcile.php [--terminal=path\to\smart210.db] [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]
//
// --from/--to limit the checks of the day-bound records (default: everything). The tanks, tickets and debts are checked as they stand now.
// Lines "ok" / "FAIL" / "note"; the exit code is 1 when a check failed.

$terminalDb = __DIR__ . '/../smart210/build/Desktop_Qt_5_15_2_MinGW_64_bit_Debug/debug/data-day1/smart210.db';
$from = '0000-01-01';
$to = '9999-12-31';
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--terminal=') === 0) {
        $terminalDb = substr($a, 11);
    } elseif (strpos($a, '--from=') === 0) {
        $from = substr($a, 7);
    } elseif (strpos($a, '--to=') === 0) {
        $to = substr($a, 5);
    }
}

$env = [];
foreach (file(__DIR__ . '/../web/.env') as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = trim($m[2], "\"'");
    }
}
$web = new PDO('mysql:host=' . $env['DB_HOST'] . ';port=' . $env['DB_PORT'] . ';dbname=' . $env['DB_DATABASE'], $env['DB_USERNAME'], isset($env['DB_PASSWORD']) ? $env['DB_PASSWORD'] : '');
$web->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$term = is_file($terminalDb) ? new PDO('sqlite:' . $terminalDb) : null;

function q(PDO $db, $sql, array $args = [])
{
    $st = $db->prepare($sql);
    $st->execute($args);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function val(PDO $db, $sql, array $args = [])
{
    $r = q($db, $sql, $args);

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

function m($v)
{
    return number_format((float) $v, 2);
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('sales: money and kg');
$rows = q($web, 'SELECT * FROM sales WHERE deleted_at IS NULL AND sale_date BETWEEN ? AND ?', [$from, $to]);
$byType = [];
$badTotal = 0;
$badSplit = 0;
foreach ($rows as $r) {
    $byType[$r['sale_type']][0] = ($byType[$r['sale_type']][0] ?? 0) + 1;
    $byType[$r['sale_type']][1] = ($byType[$r['sale_type']][1] ?? 0) + $r['amount_kg'];
    $byType[$r['sale_type']][2] = ($byType[$r['sale_type']][2] ?? 0) + $r['total_amount'];
    if ($r['sale_type'] !== 'card' && $r['usage'] === 'sale' && abs($r['amount_kg'] * $r['price'] - $r['total_amount']) > 0.011) {
        ++$badTotal;
    }
    if (abs($r['paid_amount'] + $r['unpaid_amount'] - $r['total_amount']) > 0.011) {
        ++$badSplit;
    }
}
foreach ($byType as $type => $v) {
    echo '  note  ' . str_pad($type, 7) . $v[0] . ' records, ' . m($v[1]) . ' kg, ' . m($v[2]) . "\n";
}
ok($badTotal === 0, 'every sale has total = kg x price' . ($badTotal ? " ($badTotal differ)" : ''));
ok($badSplit === 0, 'every sale has paid + unpaid = total' . ($badSplit ? " ($badSplit differ)" : ''));

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('supplies and the tanks');
$sup = q($web, 'SELECT * FROM supplements WHERE deleted_at IS NULL AND supply_date BETWEEN ? AND ?', [$from, $to]);
$kgBy = [];
foreach ($sup as $s) {
    $kgBy[$s['fuel_type']] = ($kgBy[$s['fuel_type']] ?? 0) + $s['out_amount_kg'];
}
foreach ($kgBy as $f => $kg) {
    echo "  note  fuel $f: " . m($kg) . ' kg supplied in ' . count(array_filter($sup, function ($s) use ($f) { return $s['fuel_type'] === $f; })) . " supplies\n";
}
$orphans = (int) val($web, 'SELECT COUNT(*) FROM supplements s WHERE s.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM fuel_outbounds o WHERE o.supplement_id = s.id AND o.deleted_at IS NULL)');
ok($orphans === 0, 'every supply has its outbound row from the tank' . ($orphans ? " ($orphans have none)" : ''));
$diff = (int) val($web, 'SELECT COUNT(*) FROM supplements s JOIN fuel_outbounds o ON o.supplement_id = s.id AND o.deleted_at IS NULL WHERE s.deleted_at IS NULL AND ABS(o.amount_kg - s.out_amount_kg) > 0.005');
ok($diff === 0, 'the outbound of every supply has its kg' . ($diff ? " ($diff differ)" : ''));
foreach (q($web, 'SELECT t.id, t.name, t.fuel_type, t.capacity_kg FROM tanks t WHERE t.deleted_at IS NULL ORDER BY t.id') as $t) {
    $open = (float) val($web, 'SELECT COALESCE(SUM(balance),0) FROM tank_balances WHERE tank_id = ?', [$t['id']]);
    $in = (float) val($web, 'SELECT COALESCE(SUM(amount_kg),0) FROM fuel_inbounds WHERE tank_id = ? AND deleted_at IS NULL', [$t['id']]);
    $outSup = (float) val($web, 'SELECT COALESCE(SUM(s.out_amount_kg),0) FROM supplements s JOIN fuel_outbounds o ON o.supplement_id = s.id AND o.deleted_at IS NULL WHERE o.tank_id = ? AND s.deleted_at IS NULL', [$t['id']]);
    $outHand = (float) val($web, 'SELECT COALESCE(SUM(amount_kg),0) FROM fuel_outbounds WHERE tank_id = ? AND supplement_id IS NULL AND deleted_at IS NULL', [$t['id']]);
    $now = $open + $in - $outSup - $outHand;
    echo '  note  ' . $t['name'] . ' (' . $t['fuel_type'] . '): opening ' . m($open) . ' + inbound ' . m($in) . ' - supplied ' . m($outSup) . ' - by hand ' . m($outHand) . ' = ' . m($now) . "\n";
    ok($now >= 0 && ($t['capacity_kg'] === null || $now <= $t['capacity_kg'] + 0.005), $t['name'] . ' holds ' . m($now) . ' kg, within 0 and its capacity ' . m($t['capacity_kg']));
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('tickets');
$st = q($web, 'SELECT status, COUNT(*) n FROM tickets GROUP BY status');
$stat = [];
foreach ($st as $r) {
    $stat[(int) $r['status']] = (int) $r['n'];
}
echo '  note  in stock ' . ($stat[0] ?? 0) . ', sold ' . ($stat[1] ?? 0) . ', supplied ' . ($stat[2] ?? 0) . ', restricted ' . ($stat[3] ?? 0) . "\n";
ok((int) val($web, 'SELECT COUNT(*) FROM tickets WHERE status = 0 AND sale_id IS NOT NULL') === 0, 'no ticket in stock belongs to a sale');
// a remainder ticket is handed to the customer (status sold) without a sale
ok((int) val($web, 'SELECT COUNT(*) FROM tickets x WHERE status = 1 AND sale_id IS NULL AND card_id IS NULL AND NOT EXISTS (SELECT 1 FROM remainders r WHERE r.ticket_barcode = x.barcode)') === 0, 'every sold ticket belongs to a sale, a card or is a remainder ticket');
$rem = (int) val($web, 'SELECT COUNT(*) FROM remainders r WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.barcode = r.ticket_barcode)');
ok($rem === 0, 'every remainder has its ticket' . ($rem ? " ($rem missing)" : ''));
$open = (int) val($web, 'SELECT COUNT(*) FROM remainders r JOIN tickets t ON t.barcode = r.ticket_barcode WHERE t.status = 2 AND r.complete_date IS NULL');
ok($open === 0, 'every remainder whose ticket was supplied is complete' . ($open ? " ($open are still open)" : ''));
$dup = (int) val($web, 'SELECT COUNT(*) FROM (SELECT barcode FROM tickets GROUP BY barcode HAVING COUNT(*) > 1) x');
ok($dup === 0, 'no barcode exists twice');

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('debts: what each customer owes');
foreach (q($web, 'SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY id') as $c) {
    $opening = (float) val($web, 'SELECT COALESCE(SUM(amount),0) FROM debts WHERE customer_id = ? AND sale_id IS NULL AND deleted_at IS NULL', [$c['id']]);
    $unpaid = (float) val($web, 'SELECT COALESCE(SUM(unpaid_amount),0) FROM sales WHERE customer_id = ? AND deleted_at IS NULL', [$c['id']]);
    $pay = (float) val($web, 'SELECT COALESCE(SUM(amount),0) FROM debt_payments WHERE customer_id = ? AND deleted_at IS NULL', [$c['id']]);
    $owed = (float) val($web, 'SELECT COALESCE(SUM(amount),0) FROM debts WHERE customer_id = ? AND deleted_at IS NULL', [$c['id']])
        + (float) val($web, 'SELECT COALESCE(SUM(amount),0) FROM debt_adjustments WHERE customer_id = ? AND deleted_at IS NULL', [$c['id']]) - $pay;
    $expected = $opening + $unpaid - $pay;
    if ($owed == 0 && $expected == 0) {
        continue;
    }
    ok(abs($owed - $expected) < 0.011, $c['name'] . ' owes ' . m($owed) . ' (opening ' . m($opening) . ' + unpaid on sales ' . m($unpaid) . ' - payments ' . m($pay) . ' = ' . m($expected) . ')');
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
section('sync');
ok((int) val($web, 'SELECT COUNT(*) FROM sync_errors WHERE resolved_at IS NULL') === 0, 'no unresolved sync error on the web app');

if ($term) {
    section('the terminal against the web');
    $ts = q($term, 'SELECT id, sale_type, fuel_type, amount_kg, total_amount, paid_amount, unpaid_amount, sale_date, deleted_at FROM sales WHERE sale_date BETWEEN ? AND ?', [$from, $to]);
    $ids = [];
    foreach ($ts as $r) {
        $ids[$r['id']] = $r;
    }
    echo '  note  the terminal holds ' . count($ids) . " sales\n";
    $missing = 0;
    $differ = 0;
    foreach (array_chunk(array_keys($ids), 500) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $w = [];
        foreach (q($web, "SELECT id, sale_type, fuel_type, amount_kg, total_amount, paid_amount, unpaid_amount, sale_date, deleted_at FROM sales WHERE id IN ($in)", $chunk) as $r) {
            $w[$r['id']] = $r;
        }
        foreach ($chunk as $id) {
            $t = $ids[$id];
            if ($t['deleted_at'] !== null) {
                continue;
            }
            if (!isset($w[$id]) || $w[$id]['deleted_at'] !== null) {
                ++$missing;
                continue;
            }
            foreach (['sale_type', 'fuel_type', 'sale_date'] as $k) {
                if (substr((string) $t[$k], 0, 10) !== substr((string) $w[$id][$k], 0, 10)) {
                    ++$differ;
                    continue 2;
                }
            }
            foreach (['amount_kg', 'total_amount', 'paid_amount', 'unpaid_amount'] as $k) {
                if (abs($t[$k] / 100 - $w[$id][$k]) > 0.005) {   // the terminal keeps kg and money times 100
                    ++$differ;
                    continue 2;
                }
            }
        }
    }
    ok($missing === 0, 'every sale of the terminal is on the web app' . ($missing ? " ($missing missing)" : ''));
    ok($differ === 0, 'and has the same type, fuel, date, kg and money' . ($differ ? " ($differ differ)" : ''));

    $tsup = q($term, 'SELECT id, fuel_type, preset_kg, out_amount_kg, out_amount_lt, supply_date FROM supplements WHERE supply_date BETWEEN ? AND ?', [$from, $to]);
    echo '  note  the terminal holds ' . count($tsup) . " supplies\n";
    $missing = 0;
    $differ = 0;
    foreach (array_chunk($tsup, 500) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $w = [];
        foreach (q($web, "SELECT id, fuel_type, preset_kg, out_amount_kg, out_amount_lt, deleted_at FROM supplements WHERE id IN ($in)", array_column($chunk, 'id')) as $r) {
            $w[$r['id']] = $r;
        }
        foreach ($chunk as $t) {
            if (!isset($w[$t['id']]) || $w[$t['id']]['deleted_at'] !== null) {
                ++$missing;
            } elseif ($t['fuel_type'] !== $w[$t['id']]['fuel_type'] || abs($t['out_amount_kg'] / 100 - $w[$t['id']]['out_amount_kg']) > 0.005 || abs($t['preset_kg'] / 100 - $w[$t['id']]['preset_kg']) > 0.005
                || abs($t['out_amount_lt'] / 100 - $w[$t['id']]['out_amount_lt']) > 0.005) {
                ++$differ;
            }
        }
    }
    ok($missing === 0, 'every supply of the terminal is on the web app' . ($missing ? " ($missing missing)" : ''));
    ok($differ === 0, 'and has the same kg, litres and preset' . ($differ ? " ($differ differ)" : ''));
    ok((int) val($term, 'SELECT COUNT(*) FROM supplements WHERE uploaded_at IS NULL') === 0 && (int) val($term, 'SELECT COUNT(*) FROM sales WHERE uploaded_at IS NULL') === 0
        && (int) val($term, 'SELECT COUNT(*) FROM remainders WHERE uploaded_at IS NULL') === 0, 'nothing waits for upload on the terminal');
    $trem = (int) val($term, 'SELECT COUNT(*) FROM remainders');
    $wrem = 0;
    foreach (array_chunk(array_column(q($term, 'SELECT id FROM remainders'), 'id'), 500) as $chunk) {
        $wrem += (int) val($web, 'SELECT COUNT(*) FROM remainders WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', $chunk);
    }
    ok($trem === $wrem, "the $trem remainders of the terminal are on the web app ($wrem found)");
}

echo "\n$checks checks, $fails failed\n";
exit($fails ? 1 : 0);
