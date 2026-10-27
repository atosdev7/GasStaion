<?php

namespace App\Support\Reports;

// Spreadsheet layout of each report: the same columns as the on-screen page, with raw numbers instead of formatted text.
// Every method takes the data array that is passed to the report view.
class ReportSheets
{
    public static function sales(array $d)
    {
        $fuels = $d['fuels'];
        $summary = [];
        foreach ($d['summary'] as $r) {
            $summary[] = [
                isset($fuels[$r->fuel_type]) ? $fuels[$r->fuel_type] : $r->fuel_type,
                __('report.sale_type_' . $r->sale_type), __('report.usage_' . $r->usage), __('report.payment_' . $r->payment_method),
                (int) $r->sales, (float) $r->kg, (float) $r->total, (float) $r->paid, (float) $r->unpaid,
            ];
        }
        if ($d['summary']->isNotEmpty()) {
            $summary[] = [
                __('report.total'), '', '', '', (int) $d['summary']->sum('sales'),
                (float) $d['summary']->sum('kg'), (float) $d['summary']->sum('total'),
                (float) $d['summary']->sum('paid'), (float) $d['summary']->sum('unpaid'),
            ];
        }

        $daily = [];
        foreach ($d['daily'] as $r) {
            $daily[] = [(string) $r->day, (int) $r->sales, (float) $r->kg, (float) $r->total, (float) $r->paid, (float) $r->unpaid];
        }

        return [
            [
                'title' => __('report.by_type'),
                'headings' => [__('report.fuel_type'), __('report.sale_type'), __('report.usage'), __('report.payment_method'),
                    __('report.count'), __('report.amount_kg'), __('report.total_amount'), __('report.paid_amount'), __('report.unpaid_amount')],
                'rows' => $summary,
                'money' => [5, 6, 7, 8],
            ],
            [
                'title' => __('report.by_day'),
                'headings' => [__('report.day'), __('report.count'), __('report.amount_kg'), __('report.total_amount'), __('report.paid_amount'), __('report.unpaid_amount')],
                'rows' => $daily,
                'money' => [2, 3, 4, 5],
            ],
        ];
    }

    public static function cash(array $d)
    {
        $rows = [];
        foreach ($d['rows'] as $r) {
            $rows[] = [(string) $r->day, __('report.payment_' . $r->method), (float) $r->sales, (float) $r->payments, (float) ($r->sales + $r->payments)];
        }

        return [[
            'title' => __('report.title_cash'),
            'headings' => [__('report.day'), __('report.payment_method'), __('report.from_sales'), __('report.from_payments'), __('report.total')],
            'rows' => $rows,
            'money' => [2, 3, 4],
        ]];
    }

    public static function profit(array $d)
    {
        $fuels = $d['fuels'];
        $rows = [];
        foreach ($d['rows'] as $r) {
            $rows[] = [
                isset($fuels[$r->fuel_type]) ? $fuels[$r->fuel_type] : $r->fuel_type, __('report.usage_' . $r->usage),
                (float) $r->kg, (float) $r->revenue, (float) $r->cost_total, (float) $r->profit, (int) $r->missing_cost,
            ];
        }
        if ($d['rows']->isNotEmpty()) {
            $rows[] = [
                __('report.total'), '', (float) $d['rows']->sum('kg'), (float) $d['rows']->sum('revenue'),
                (float) $d['rows']->sum('cost_total'), (float) $d['rows']->sum('profit'), (int) $d['rows']->sum('missing_cost'),
            ];
        }

        return [[
            'title' => __('report.title_profit'),
            'headings' => [__('report.fuel_type'), __('report.usage'), __('report.amount_kg'), __('report.revenue'),
                __('report.cost'), __('report.profit'), __('report.missing_cost')],
            'rows' => $rows,
            'money' => [2, 3, 4, 5],
        ]];
    }

    public static function customer(array $d)
    {
        $fuels = $d['fuels'];

        $sales = [];
        foreach ($d['sales'] as $s) {
            $sales[] = [
                (string) $s->sale_date, isset($fuels[$s->fuel_type]) ? $fuels[$s->fuel_type] : $s->fuel_type,
                __('report.sale_type_' . $s->sale_type), __('report.usage_' . $s->usage),
                (float) $s->amount_kg, (float) $s->total_amount, (float) $s->paid_amount, (float) $s->unpaid_amount,
            ];
        }

        $payments = [];
        foreach ($d['payments'] as $p) {
            $payments[] = [(string) $p->paid_date, __('report.payment_' . $p->payment_method), (float) $p->amount, (string) $p->note];
        }

        $debts = [];
        foreach ($d['debts'] as $x) {
            $debts[] = [
                $x->display_date ? (string) $x->display_date : __('report.opening_balance'), __('report.payment_' . $x->payment_method),
                (float) $x->amount, (float) $x->settled, (float) $x->balance,
            ];
        }

        $loans = [];
        foreach ($d['loans'] as $l) {
            $loans[] = [
                $l->sale_id ? (string) $l->loaned_at : __('report.opening_balance'), isset($fuels[$l->fuel_type]) ? $fuels[$l->fuel_type] : $l->fuel_type,
                (float) $l->amount_kg, (float) $l->settled_amount_kg, (float) $l->balance_kg,
            ];
        }

        return [
            [
                'title' => $d['customer'],
                'headings' => [__('report.customer_id'), $d['customer']],
                'rows' => [
                    [__('report.open_debt_balance'), (float) $d['debts']->sum('balance')],
                    [__('report.unallocated'), (float) $d['unallocated']],
                    [__('report.open_loan_kg'), (float) $d['loans']->sum('balance_kg')],
                ],
                'money' => [1],
            ],
            [
                'title' => __('report.sales_in_period'),
                'headings' => [__('report.sale_date'), __('report.fuel_type'), __('report.sale_type'), __('report.usage'),
                    __('report.amount_kg'), __('report.total_amount'), __('report.paid_amount'), __('report.unpaid_amount')],
                'rows' => $sales,
                'money' => [4, 5, 6, 7],
            ],
            [
                'title' => __('report.payments_in_period'),
                'headings' => [__('report.paid_date'), __('report.payment_method'), __('report.amount'), __('report.note')],
                'rows' => $payments,
                'money' => [2],
            ],
            [
                'title' => __('report.debts'),
                'headings' => [__('report.sale_date'), __('report.payment_method'), __('report.amount'), __('report.settled_amount'), __('report.balance')],
                'rows' => $debts,
                'money' => [2, 3, 4],
            ],
            [
                'title' => __('report.loans'),
                'headings' => [__('report.loaned_at'), __('report.fuel_type'), __('report.amount_kg'), __('report.settled_amount'), __('report.balance')],
                'rows' => $loans,
                'money' => [2, 3, 4],
            ],
        ];
    }

    public static function tickets(array $d)
    {
        $fuels = $d['fuels'];
        $rows = [];
        foreach ($d['rows'] as $r) {
            $rows[] = [
                isset($fuels[$r->fuel_type]) ? $fuels[$r->fuel_type] : $r->fuel_type,
                $r->spec === null ? __('report.direct') : config('ticket_specs.' . $r->spec) . ' ' . __('report.kg'),
                $r->received === null ? '-' : (int) $r->received, $r->blank === null ? '-' : (int) $r->blank,
                (int) $r->publish, (int) $r->sale, (int) $r->supply, (int) $r->restrict, (int) $r->old,
            ];
        }

        return [[
            'title' => __('report.title_tickets'),
            'headings' => [__('report.fuel_type'), __('report.spec'), __('report.received'), __('report.blank'),
                __('report.unsold'), __('report.sold'), __('report.supplied'), __('report.restricted'), __('report.old_unused')],
            'rows' => $rows,
        ]];
    }

    public static function tanks(array $d)
    {
        $fuels = $d['fuels'];
        $rows = [];
        foreach ($d['rows'] as $r) {
            $rows[] = [
                $r->tank->name, isset($fuels[$r->tank->fuel_type]) ? $fuels[$r->tank->fuel_type] : $r->tank->fuel_type,
                (float) $r->opening, (float) $r->inbound, (float) $r->supplied, (float) $r->current,
                $r->capacity !== null ? (float) $r->capacity : '-', $r->percent !== null ? (float) $r->percent : '-',
            ];
        }

        return [[
            'title' => __('report.title_tanks'),
            'headings' => [__('report.tank_id'), __('report.fuel_type'), __('report.opening'), __('report.inbound'),
                __('report.outbound'), __('report.current'), __('report.capacity'), __('report.percent_full')],
            'rows' => $rows,
            'money' => [2, 3, 4, 5, 6],
        ]];
    }

    public static function ordering(array $d)
    {
        $fuels = $d['fuels'];
        $windows = $d['windows'];
        $rows = [];
        foreach ($d['rows'] as $r) {
            $row = [isset($fuels[$r->fuel_type]) ? $fuels[$r->fuel_type] : $r->fuel_type, $r->tanks, $r->system_owed, $r->old_owed, $r->owed, $r->free];
            foreach (['supplied', 'sold'] as $kind) {
                foreach ($windows as $days) {
                    $row[] = round($r->$kind[$days], 2);
                }
            }
            foreach ($windows as $days) {
                $row[] = $r->cover[$days] === null ? '-' : round($r->cover[$days], 1);
            }
            $rows[] = $row;
        }

        $headings = [__('report.fuel_type'), __('report.ord_tanks'), __('report.ord_system_owed'), __('report.ord_old_owed'), __('report.ord_owed'), __('report.ord_free')];
        foreach (['ord_supplied', 'ord_sold', 'ord_cover'] as $key) {
            foreach ($windows as $days) {
                $headings[] = __('report.' . $key) . ' ' . $days . ' ' . __('report.ord_days_short');
            }
        }

        return [[
            'title' => __('report.title_ordering'),
            'headings' => $headings,
            'rows' => $rows,
            'money' => array_merge(range(1, 5), range(6, 5 + 2 * count($windows))),
        ]];
    }

    public static function supply(array $d)
    {
        $rows = [];
        foreach ($d['rows'] as $r) {
            $rows[] = [
                (string) $r->day, isset($d['nozzleLabels'][$r->nozzle_id]) ? $d['nozzleLabels'][$r->nozzle_id] : $r->nozzle_id,
                (int) $r->supplies, (float) $r->liters, (float) $r->kg,
            ];
        }
        if ($d['rows']->isNotEmpty()) {
            $rows[] = [__('report.total'), '', (int) $d['rows']->sum('supplies'), (float) $d['rows']->sum('liters'), (float) $d['rows']->sum('kg')];
        }

        return [[
            'title' => __('report.title_supply'),
            'headings' => [__('report.day'), __('report.nozzle_id'), __('report.count'), __('report.liters'), __('report.amount_kg')],
            'rows' => $rows,
            'money' => [3, 4],
        ]];
    }

    // One tab of the Daily Confirmation Sales page, as one sheet: the same table as on the page, raw numbers, the totals at the foot. When the
    // table has several sides (spot sales: gasoline, diesel) they stand side by side under a heading each (merged), and row n holds the n-th sale
    // of every side. A side shows the payment of each sale (detail 'payment') or its sale type and usage (detail 'usage'); a side with `specs`
    // (spec key => kg: the ticket sales) has a column for the tickets of each spec under a merged Count heading, over a second heading row.
    // $d: title, sides (title, rows, withFuel, detail, specs), fuels, customers.
    public static function confirmationSales(array $d)
    {
        $sides = $d['sides'];
        $hasSpecs = !empty($sides[0]['specs']);

        $top = [];
        $headings = [];
        $merges = [];
        $money = [];
        $widths = [];
        foreach ($sides as $side) {
            $start = count($headings);
            $columns = [__('report.time')];
            if ($side['withFuel']) {
                $columns[] = __('report.fuel_type');
            }
            $columns[] = __('report.customer_id');
            $columns = array_merge($columns, $side['detail'] === 'payment' ? [__('report.payment_method')] : [__('report.sale_type'), __('report.usage')]);
            $specs = !empty($side['specs']) ? $side['specs'] : [];
            $specStart = $start + count($columns);
            $labels = array_fill(0, count($columns), '');
            foreach ($specs as $kg) {
                $columns[] = '';
                $labels[] = $kg . __('report.kg');
            }
            $columns = array_merge($columns, [__('report.amount_kg'), __('report.total_amount'), __('report.paid_amount'), __('report.unpaid_amount'), __('report.confirmed')]);
            $labels = array_merge($labels, ['', '', '', '', '']);
            $widths[] = count($columns);

            if ($hasSpecs) {
                // two heading rows: each column its heading over two rows, the specs under a Count heading
                $second = $labels;
                $first = $columns;
                foreach (array_keys($columns) as $i) {
                    $isSpec = $i >= count($columns) - 5 - count($specs) && $i < count($columns) - 5;
                    if ($isSpec) {
                        $first[$i] = $i === count($columns) - 5 - count($specs) ? __('report.count') : '';
                    } else {
                        $merges[] = self::cellName($start + $i) . '1:' . self::cellName($start + $i) . '2';
                    }
                }
                $merges[] = self::cellName($specStart) . '1:' . self::cellName($specStart + count($specs) - 1) . '1';
                $top = array_merge($top, $first);
                $headings = array_merge($headings, $second);
            } else {
                $headings = array_merge($headings, $columns);
                $top = array_merge($top, [(string) $side['title']], array_fill(0, count($columns) - 1, ''));
                $merges[] = self::cellName($start) . '1:' . self::cellName($start + count($columns) - 1) . '1';
            }
            $end = $start + count($columns);
            $money = array_merge($money, [$end - 5, $end - 4, $end - 3, $end - 2]);
        }

        $lines = 0;
        foreach ($sides as $side) {
            $lines = max($lines, $side['rows']->count());
        }

        $rows = [];
        for ($i = 0; $i < $lines; $i++) {
            $row = [];
            foreach ($sides as $k => $side) {
                $sale = $side['rows']->get($i);
                if (!$sale) {
                    $row = array_merge($row, array_fill(0, $widths[$k], ''));
                    continue;
                }
                $row[] = $sale->created_at ? $sale->created_at->format('H:i') : '';
                if ($side['withFuel']) {
                    $row[] = isset($d['fuels'][$sale->fuel_type]) ? $d['fuels'][$sale->fuel_type] : $sale->fuel_type;
                }
                // the name of a card sale is the holder of its card
                $row[] = $sale->card_id ? (isset($d['cards'][$sale->card_id]) ? $d['cards'][$sale->card_id] : '') : (isset($d['customers'][$sale->customer_id]) ? $d['customers'][$sale->customer_id] : '');
                $row = array_merge($row, $side['detail'] === 'payment' ? [__('report.payment_' . $sale->payment_method)] : [__('report.sale_type_' . $sale->sale_type), __('report.usage_' . $sale->usage)]);
                foreach (!empty($side['specs']) ? array_keys($side['specs']) : [] as $spec) {
                    $count = $sale->ticketsOfSpec($spec);
                    $row[] = $count ?: '';   // blank, not 0, when the sale has none of that spec (as on the page)
                }
                $row = array_merge($row, [(float) $sale->amount_kg, (float) $sale->total_amount, (float) $sale->paid_amount, (float) $sale->unpaid_amount,
                    $sale->confirmed_at ? __('report.confirmed_yes') : '']);
            }
            $rows[] = $row;
        }
        if ($lines) {
            $total = [];
            foreach ($sides as $k => $side) {
                $specKeys = !empty($side['specs']) ? array_keys($side['specs']) : [];
                $label = array_fill(0, $widths[$k] - 5 - count($specKeys), '');
                $label[0] = __('report.total');
                $counts = [];
                foreach ($specKeys as $spec) {
                    $sum = (int) $side['rows']->sum(function ($sale) use ($spec) {
                        return $sale->ticketsOfSpec($spec);
                    });
                    $counts[] = $sum ?: '';
                }
                $total = array_merge($total, $label, $counts, [(float) $side['rows']->sum('amount_kg'), (float) $side['rows']->sum('total_amount'),
                    (float) $side['rows']->sum('paid_amount'), (float) $side['rows']->sum('unpaid_amount'), '']);
            }
            $rows[] = $total;
        }

        $sheet = ['title' => $d['title'], 'headings' => $headings, 'rows' => $rows, 'money' => $money];
        if (count($sides) > 1 || $hasSpecs) {
            $sheet['headings'] = [$top, $headings];
            $sheet['merges'] = $merges;
            $sheet['borders'] = true;
        }

        return [$sheet];
    }

    // The table of the Daily Confirmation Supplements page, as one sheet: the fuel types side by side under a heading each (merged), row n holding
    // the n-th supplement of every side, the totals at the foot. $d: title, sides (title, rows), nozzleLabels.
    public static function confirmationSupplements(array $d)
    {
        $sides = $d['sides'];
        $columns = [__('report.supplied_at'), __('report.nozzle_id'), __('report.mode'), __('report.preset_amount'),
            __('report.out_amount_kg'), __('report.remainder'), __('report.comment'), __('report.confirmed')];
        $width = count($columns);

        $top = [];
        $headings = [];
        $merges = [];
        $money = [];
        $lines = 0;
        foreach ($sides as $i => $side) {
            $start = $i * $width;
            $headings = array_merge($headings, $columns);
            $top = array_merge($top, [(string) $side['title']], array_fill(0, $width - 1, ''));
            $merges[] = self::cellName($start) . '1:' . self::cellName($start + $width - 1) . '1';
            $money = array_merge($money, [$start + 3, $start + 4]);
            $lines = max($lines, $side['rows']->count());
        }

        $rows = [];
        for ($i = 0; $i < $lines; $i++) {
            $row = [];
            foreach ($sides as $side) {
                $s = $side['rows']->get($i);
                if (!$s) {
                    $row = array_merge($row, array_fill(0, $width, ''));
                    continue;
                }
                $row = array_merge($row, [
                    (string) $s->supplied_at,
                    isset($d['nozzleLabels'][$s->nozzle_id]) ? $d['nozzleLabels'][$s->nozzle_id] : $s->nozzle_id,
                    __('report.mode_' . $s->mode),
                    (float) $s->preset_kg, (float) $s->out_amount_kg,
                    $s->remainder ? $s->remainder->ticket_barcode . ' (' . number_format($s->remainder->amount_kg, 2, '.', '') . ' ' . __('report.kg') . ')' : '',
                    (string) $s->comment,
                    $s->confirmed_at ? __('report.confirmed_yes') : '',
                ]);
            }
            $rows[] = $row;
        }
        if ($lines) {
            $total = [];
            foreach ($sides as $side) {
                $total = array_merge($total, [__('report.total'), '', '', (float) $side['rows']->sum('preset_kg'),
                    (float) $side['rows']->sum('out_amount_kg'), '', '', '']);
            }
            $rows[] = $total;
        }

        return [['title' => $d['title'], 'headings' => [$top, $headings], 'rows' => $rows, 'money' => $money, 'merges' => $merges, 'borders' => true]];
    }

    // The Summary page of the Daily Confirmation as three sheets: the sales (a row for each kind and fuel type: Ticket Sales with the tickets of
    // each spec, Spot Sales, Self, Others), the supplements (a row for each kind, the fuel types side by side) and the remainders (the fuel types
    // side by side). $d: date, summary (see ConfirmationController::summary), fuels.
    public static function confirmationSummary(array $d)
    {
        $summary = $d['summary'];
        $fuelName = function ($fuel) use ($d) {
            return isset($d['fuels'][$fuel]) ? $d['fuels'][$fuel] : $fuel;
        };
        $kindName = function ($kind) {
            return in_array($kind, ['ticket', 'spot', 'card'], true) ? __('menu.' . $kind . '_sales') : __('report.sum_kind_' . $kind);
        };

        // the sales: Type | Fuel type | Count | Tickets 5kg ... | Weight | Total | Paid | Unpaid; the spec columns are for the ticket sales only
        $specs = $summary['ticketSpecs'];
        $headings = [__('report.sale_type'), __('report.fuel_type'), __('report.sum_count')];
        foreach ($specs as $kg) {
            $headings[] = __('report.tickets') . ' ' . $kg . __('report.kg');
        }
        $headings = array_merge($headings, [__('report.amount_kg'), __('report.total_amount'), __('report.paid_amount'), __('report.unpaid_amount')]);
        $sales = [];
        $sums = ['count' => 0, 'kg' => 0.0, 'total' => 0.0, 'paid' => 0.0, 'unpaid' => 0.0];
        $specSums = array_fill_keys(array_keys($specs), 0);
        foreach ($summary['salesBy'] as $kind => $byFuel) {
            foreach ($byFuel as $fuel => $line) {
                $row = [$kindName($kind), $fuelName($fuel), (int) $line['count']];
                foreach (array_keys($specs) as $spec) {
                    $n = $kind === 'ticket' && isset($line['specs'][$spec]) ? (int) $line['specs'][$spec] : '';
                    $row[] = $n;
                    $specSums[$spec] += (int) $n;
                }
                $sales[] = array_merge($row, [(float) $line['kg'], (float) $line['total'], (float) $line['paid'], (float) $line['unpaid']]);
                $sums['count'] += $line['count'];
                foreach (['kg', 'total', 'paid', 'unpaid'] as $key) {
                    $sums[$key] += $line[$key];
                }
            }
        }
        $sales[] = array_merge([__('report.total'), '', $sums['count']], array_values($specSums), [(float) $sums['kg'], (float) $sums['total'], (float) $sums['paid'], (float) $sums['unpaid']]);
        $first = 3 + count($specs);

        // the supplements: Type | per fuel: Count, Preset amount, Out amount (kg), Remainder, Comment
        $fuelKeys = $summary['fuelKeys'];
        $top = [''];
        $second = [__('report.sum_ticket_kind')];
        $merges = ['A1:A2'];
        $money = [];
        foreach ($fuelKeys as $i => $fuel) {
            $start = 1 + $i * 5;
            $top = array_merge($top, [$fuelName($fuel), '', '', '', '']);
            $second = array_merge($second, [__('report.sum_count'), __('report.preset_amount'), __('report.out_amount_kg'), __('report.remainder'), __('report.comment')]);
            $merges[] = self::cellName($start) . '1:' . self::cellName($start + 4) . '1';
            $money = array_merge($money, [$start + 1, $start + 2, $start + 3]);
        }
        $top[0] = __('report.sum_ticket_kind');
        $supplements = [];
        foreach ($summary['supplementsBy'] as $kind => $byFuel) {
            $row = [__('report.sum_kind_' . $kind)];
            foreach ($fuelKeys as $fuel) {
                $line = $byFuel[$fuel];
                $row = array_merge($row, [(int) $line['count'], (float) $line['preset'], (float) $line['kg'], (float) $line['remainder'], (string) $line['comment']]);
            }
            $supplements[] = $row;
        }
        $total = [__('report.total')];
        foreach ($fuelKeys as $fuel) {
            $column = collect($summary['supplementsBy'])->pluck($fuel);
            $total = array_merge($total, [(int) $column->sum('count'), (float) $column->sum('preset'), (float) $column->sum('kg'), (float) $column->sum('remainder'), '']);
        }
        $supplements[] = $total;

        // the remainders: per fuel: Made, Made (kg), Used up, Used up (kg)
        $topR = [];
        $secondR = [];
        $row = [];
        $mergesR = [];
        $moneyR = [];
        foreach (array_keys($summary['remaindersBy']) as $i => $fuel) {
            $line = $summary['remaindersBy'][$fuel];
            $start = $i * 4;
            $topR = array_merge($topR, [$fuelName($fuel), '', '', '']);
            $secondR = array_merge($secondR, [__('report.sum_made'), __('report.sum_made') . ' (' . __('report.kg') . ')', __('report.sum_used'), __('report.sum_used') . ' (' . __('report.kg') . ')']);
            $row = array_merge($row, [(int) $line['made'], (float) $line['made_kg'], (int) $line['used'], (float) $line['used_kg']]);
            $mergesR[] = self::cellName($start) . '1:' . self::cellName($start + 3) . '1';
            $moneyR = array_merge($moneyR, [$start + 1, $start + 3]);
        }

        return [
            ['title' => __('report.sales'), 'headings' => $headings, 'rows' => $sales, 'money' => [$first, $first + 1, $first + 2, $first + 3]],
            ['title' => __('report.supplements'), 'headings' => [$top, $second], 'rows' => $supplements, 'money' => $money, 'merges' => $merges, 'borders' => true],
            ['title' => __('menu.remainders'), 'headings' => [$topR, $secondR], 'rows' => [$row], 'money' => $moneyR, 'merges' => $mergesR, 'borders' => true],
        ];
    }

    // The Remainders tab of the Daily Confirmation Supplements page: the remainders generated on the date, with the supply each came from.
    // $d: title, rows (remainders with their supplement), fuels, nozzleLabels.
    public static function confirmationRemainders(array $d)
    {
        $rows = [];
        foreach ($d['rows'] as $r) {
            $supply = '';
            if ($r->supplement) {
                $nozzle = isset($d['nozzleLabels'][$r->supplement->nozzle_id]) ? $d['nozzleLabels'][$r->supplement->nozzle_id] : $r->supplement->nozzle_id;
                $supply = $r->supplement->supplied_at . ' - ' . $nozzle;
            }
            $rows[] = [
                (string) $r->ticket_barcode, isset($d['fuels'][$r->fuel_type]) ? $d['fuels'][$r->fuel_type] : $r->fuel_type, (float) $r->amount_kg,
                $supply, $r->complete_date ? substr((string) $r->complete_date, 0, 10) : '', (string) $r->comment,
            ];
        }
        if ($d['rows']->isNotEmpty()) {
            $rows[] = [__('report.total'), '', (float) $d['rows']->sum('amount_kg'), '', '', ''];
        }

        return [[
            'title' => $d['title'],
            'headings' => [__('report.barcode'), __('report.fuel_type'), __('report.amount_kg'), __('report.remainder_of_supply'), __('report.complete_date'), __('report.comment')],
            'rows' => $rows,
            'money' => [2],
        ]];
    }

    // 0 -> A, 25 -> Z, 26 -> AA
    private static function cellName($index)
    {
        $name = '';
        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $name = chr(65 + ($i - 1) % 26) . $name;
        }

        return $name;
    }

    // The Cards Summary page, one sheet: a row per card (customer name, card id, fuel type, opening balance, topped up kg and amount, used, closing
    // balance in kg), then the totals of each fuel type. $d: rows, fuels.
    public static function cardSummary(array $d)
    {
        $rows = [];
        foreach ($d['rows'] as $row) {
            $fuel = $row['card']->fuel_type;
            $rows[] = [
                $row['card']->holder_name, $row['card']->uid, isset($d['fuels'][$fuel]) ? $d['fuels'][$fuel] : $fuel,
                (float) $row['opening'], (float) $row['topped_kg'], (float) $row['topped_amount'], (float) $row['used_kg'], (float) $row['closing'],
            ];
        }
        $all = collect($d['rows']);
        foreach ($d['fuels'] as $key => $name) {
            $mine = $all->filter(function ($row) use ($key) {
                return $row['card']->fuel_type === $key;
            });
            if ($mine->isNotEmpty()) {
                $rows[] = [__('report.total') . ' ' . $name, '', '', (float) $mine->sum('opening'), (float) $mine->sum('topped_kg'), (float) $mine->sum('topped_amount'),
                    (float) $mine->sum('used_kg'), (float) $mine->sum('closing')];
            }
        }

        return [[
            'title' => __('menu.card_summary'),
            'headings' => [__('report.holder'), __('report.uid'), __('report.fuel_type'), __('report.cards_opening'), __('report.topped_up_kg'), __('report.topped_up_amount'),
                __('report.used_kg'), __('report.closing')],
            'rows' => $rows,
            'money' => [3, 4, 5, 6, 7],
        ]];
    }

    // The debts Summary page: a sheet for the money owed by customer (opening balance, new debts, payments, closing balance, each in bank and cash) and, when it is
    // given, one for the tickets borrowed (kg, the fuel types side by side). $d: payments, loans (or null), fuels, fuelKeys.
    public static function debtSummary(array $d)
    {
        // the money owed: Customer | per group (opening balance, new debts, payments, closing balance): bank, cash
        $groups = ['opening', 'debts', 'payments', 'closing'];
        $top = [__('report.customer_id')];
        $second = [''];
        $merges = ['A1:A2'];
        $money = [];
        foreach ($groups as $i => $group) {
            $start = 1 + $i * 2;
            $top = array_merge($top, [__('debt_summary.' . $group), '']);
            $second = array_merge($second, [__('report.payment_bank'), __('report.payment_cash')]);
            $merges[] = self::cellName($start) . '1:' . self::cellName($start + 1) . '1';
            $money = array_merge($money, [$start, $start + 1]);
        }
        $rows = [];
        foreach ($d['payments'] as $row) {
            $line = [$row['name']];
            foreach ($groups as $group) {
                $line = array_merge($line, [(float) $row[$group]['bank'], (float) $row[$group]['cash']]);
            }
            $rows[] = $line;
        }
        if ($rows) {
            $all = collect($d['payments']);
            $total = [__('report.total')];
            foreach ($groups as $group) {
                $total = array_merge($total, [(float) $all->sum($group . '.bank'), (float) $all->sum($group . '.cash')]);
            }
            $rows[] = $total;
        }
        $sheets = [[
            'title' => __('debts.tab_payments'),
            'headings' => [$top, $second],
            'rows' => $rows,
            'money' => $money,
            'merges' => $merges,
            'borders' => true,
        ]];

        if ($d['loans'] !== null) {
            $top = [__('report.customer_id')];
            $second = [''];
            $merges = ['A1:A2'];
            $money = [];
            foreach ($d['fuelKeys'] as $i => $fuel) {
                $start = 1 + $i * 4;
                $top = array_merge($top, [isset($d['fuels'][$fuel]) ? $d['fuels'][$fuel] : $fuel, '', '', '']);
                $second = array_merge($second, [__('report.debt_summary_opening'), __('report.loaned'), __('report.settled'), __('report.debt_summary_closing')]);
                $merges[] = self::cellName($start) . '1:' . self::cellName($start + 3) . '1';
                $money = array_merge($money, [$start, $start + 1, $start + 2, $start + 3]);
            }
            $empty = ['opening' => 0, 'loaned' => 0, 'settled' => 0, 'closing' => 0];
            $loanRows = [];
            foreach ($d['loans'] as $row) {
                $line = [$row['name']];
                foreach ($d['fuelKeys'] as $fuel) {
                    $f = isset($row['fuels'][$fuel]) ? $row['fuels'][$fuel] : $empty;
                    $line = array_merge($line, [(float) $f['opening'], (float) $f['loaned'], (float) $f['settled'], (float) $f['closing']]);
                }
                $loanRows[] = $line;
            }
            if ($loanRows) {
                $total = [__('report.total')];
                foreach ($d['fuelKeys'] as $fuel) {
                    $column = collect($d['loans'])->pluck('fuels.' . $fuel);
                    $total = array_merge($total, [(float) $column->sum('opening'), (float) $column->sum('loaned'), (float) $column->sum('settled'), (float) $column->sum('closing')]);
                }
                $loanRows[] = $total;
            }
            $sheets[] = ['title' => __('menu.ticket_loans'), 'headings' => [$top, $second], 'rows' => $loanRows, 'money' => $money, 'merges' => $merges, 'borders' => true];
        }

        return $sheets;
    }

    // The Summary Tickets table of one fuel type and spec: Date | Inbound (Count) | Publish (Count, Start, End) | Balance, with the totals at the foot.
    public static function ticketSummary(array $d)
    {
        $rows = [];
        foreach ($d['rows'] as $r) {
            $rows[] = [(string) $r->day, $r->in ? (int) $r->in : '', $r->out ? (int) $r->out : '', $r->first, $r->last, (int) $r->balance]; // blank, not 0, when nothing came in or went out (as on the page)
        }
        if ($d['rows']->isNotEmpty()) {
            $rows[] = [__('report.total'), (int) $d['totalIn'], (int) $d['totalOut'], '', '', ''];
        }
        $fuels = $d['fuels'];
        $name = (isset($fuels[$d['fuelType']]) ? $fuels[$d['fuelType']] : $d['fuelType']) . ' ' . config('ticket_specs.' . $d['spec']) . 'kg';

        return [[
            'title' => $name,
            'headings' => [
                [__('report.date'), __('report.summary_in'), __('report.summary_publish'), '', '', __('report.summary_balance')],
                ['', __('report.summary_count'), __('report.summary_count'), __('report.summary_start'), __('report.summary_end'), ''],
            ],
            'merges' => ['A1:A2', 'C1:E1', 'F1:F2'],
            'borders' => true,
            'rows' => $rows,
        ]];
    }
}
