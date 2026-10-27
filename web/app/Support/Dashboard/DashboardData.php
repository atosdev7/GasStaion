<?php

namespace App\Support\Dashboard;

use App\Models\Debts\DebtPayment;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelOutbound;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketInbound;
use App\Models\Tickets\TicketPublish;

// The numbers behind the charts of the dashboard. All of it is read-only; deleted records are not counted (Eloquent soft deletes).
class DashboardData
{
    const DAYS = 14;

    // The last DAYS days, oldest first, as Y-m-d.
    public static function days()
    {
        $days = [];
        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $days[] = today()->subDays($i)->toDateString();
        }

        return $days;
    }

    // Kg sold (sales of fuel only), the amount, the kg supplied and the kg of own use and given-away fuel on a day: [kg, amount, supplied, own].
    public static function day($date)
    {
        $sales = Sale::where('sale_date', $date)->where('usage', 'sale');

        return (object) [
            'kg' => (float) (clone $sales)->sum('amount_kg'),
            'amount' => (float) (clone $sales)->sum('total_amount'),
            'own' => (float) Sale::where('sale_date', $date)->whereIn('usage', ['self', 'other'])->sum('amount_kg'),
            'supplied' => (float) Supplement::whereRaw('COALESCE(supply_date, DATE(supplied_at)) = ?', [$date])->sum('out_amount_kg'),
        ];
    }

    // Today against yesterday: each figure with the change in percent (null when yesterday had none).
    public static function compare()
    {
        $today = self::day(today()->toDateString());
        $yesterday = self::day(today()->subDay()->toDateString());
        $change = function ($now, $before) {
            return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
        };

        return (object) [
            'today' => $today,
            'yesterday' => $yesterday,
            'kgChange' => $change($today->kg, $yesterday->kg),
            'amountChange' => $change($today->amount, $yesterday->amount),
            'suppliedChange' => $change($today->supplied, $yesterday->supplied),
        ];
    }

    // Per day of the last DAYS days: kg of ticket, spot and card sales (a return takes its kg off), and the confirmation status of the day:
    // 'confirmed' (everything of the day is confirmed: sales, supplies, ticket stock, fuel stock and debt records, as the Daily Confirmation wants), 'open' (some of it
    // is not) or 'none' (nothing recorded).
    public static function salesByDay()
    {
        $days = self::days();
        $rows = [];
        foreach ($days as $day) {
            $rows[$day] = (object) ['day' => $day, 'ticket' => 0.0, 'spot' => 0.0, 'card' => 0.0, 'status' => 'none'];
        }

        $sales = Sale::whereBetween('sale_date', [$days[0], end($days)])->where('usage', 'sale')
            ->selectRaw('sale_date AS d, sale_type, SUM(amount_kg) AS kg')->groupBy('sale_date', 'sale_type')->get();
        foreach ($sales as $row) {
            if (isset($rows[$row->d]) && in_array($row->sale_type, ['ticket', 'spot', 'card'], true)) {
                $rows[$row->d]->{$row->sale_type} = (float) $row->kg;
            }
        }

        $count = [];
        $open = [];
        $add = function ($query, $column) use (&$count, &$open) {
            foreach ($query->selectRaw("$column AS d, COUNT(*) AS n, SUM(confirmed_at IS NULL) AS open")->groupBy($column)->get() as $r) {
                $d = substr((string) $r->d, 0, 10);
                $count[$d] = ($count[$d] ?? 0) + (int) $r->n;
                $open[$d] = ($open[$d] ?? 0) + (int) $r->open;
            }
        };
        $add(Sale::whereBetween('sale_date', [$days[0], end($days)]), 'sale_date');
        $add(Supplement::whereBetween('supply_date', [$days[0], end($days)]), 'supply_date');
        $range = [$days[0], end($days)];
        $add(TicketInbound::whereBetween('inbound_date', $range), 'inbound_date');
        $add(TicketPublish::whereBetween('date', $range), 'date');
        $add(DebtPayment::whereBetween('paid_date', $range), 'paid_date');
        $add(TicketLoanSettlement::whereBetween('date', $range), 'date');
        $add(FuelInbound::whereBetween('inbound_date', $range), 'inbound_date');
        $add(FuelOutbound::whereNull('supplement_id')->whereBetween('outbound_date', $range), 'outbound_date');
        foreach ($days as $day) {
            if (!empty($count[$day])) {
                $rows[$day]->status = empty($open[$day]) ? 'confirmed' : 'open';
            }
        }

        return array_values($rows);
    }

    // The level (kg) of every tank at the end of each of the last DAYS days: the level now, less what came in after that day, plus what was
    // supplied after it. Returns [days => [...], lines => [[tank, capacity, levels => [...]], ...]].
    public static function tankTrend($tanks)
    {
        $days = self::days();
        $from = $days[0];

        $in = [];
        foreach (FuelInbound::where('inbound_date', '>', $from)->selectRaw('tank_id, inbound_date AS d, SUM(amount_kg) AS kg')->groupBy('tank_id', 'inbound_date')->get() as $r) {
            $in[$r->tank_id][substr((string) $r->d, 0, 10)] = (float) $r->kg;
        }
        $out = [];
        $supplied = FuelOutbound::where('outbound_date', '>', $from)
            ->selectRaw('tank_id, outbound_date AS d, SUM(amount_kg) AS kg')
            ->groupBy('tank_id', 'outbound_date')->get();
        foreach ($supplied as $r) {
            $out[$r->tank_id][substr((string) $r->d, 0, 10)] = (float) $r->kg;
        }

        $lines = [];
        foreach ($tanks as $t) {
            $levels = [];
            foreach ($days as $day) {
                $level = $t->current;
                foreach ($in[$t->tank->id] ?? [] as $d => $kg) {
                    if ($d > $day) {
                        $level -= $kg;
                    }
                }
                foreach ($out[$t->tank->id] ?? [] as $d => $kg) {
                    if ($d > $day) {
                        $level += $kg;
                    }
                }
                $levels[] = round($level, 2);
            }
            $lines[] = (object) ['tank' => $t->tank, 'capacity' => $t->capacity, 'levels' => $levels];
        }

        return (object) ['days' => $days, 'lines' => $lines];
    }

    // The tickets by fuel type and spec of every combination that is sold (config/ticket_codes): `blank` = received and not published yet,
    // `ready` = published and not sold yet.
    public static function ticketStock()
    {
        $received = TicketInbound::selectRaw('fuel_type, spec, SUM(quantity) AS n')->groupBy('fuel_type', 'spec')->get();
        $published = TicketPublish::selectRaw('fuel_type, spec, SUM(`count`) AS n')->groupBy('fuel_type', 'spec')->get();
        $ready = Ticket::where('status', config('ticket_statuses.publish'))->selectRaw('fuel_type, spec, COUNT(*) AS n')->groupBy('fuel_type', 'spec')->get();
        $sum = function ($rows, $fuel, $spec) {
            return (int) $rows->where('fuel_type', $fuel)->where('spec', $spec)->sum('n');
        };

        $stock = [];
        foreach (config('ticket_codes') as $fuel => $specs) {
            foreach (array_keys($specs) as $spec) {
                $stock[] = (object) [
                    'fuel_type' => $fuel,
                    'spec' => $spec,
                    'kg' => config('ticket_specs.' . $spec),
                    'blank' => max(0, $sum($received, $fuel, $spec) - $sum($published, $fuel, $spec)),
                    'ready' => $sum($ready, $fuel, $spec),
                ];
            }
        }

        return $stock;
    }

    // A round top for a chart axis that holds $value; the steps divide by four (the axis has four intervals).
    public static function niceMax($value)
    {
        if ($value <= 0) {
            return 1;
        }
        $power = pow(10, floor(log10($value)));
        foreach ([1, 1.2, 1.6, 2, 2.4, 3.2, 4, 5, 6, 8, 10] as $step) {
            if ($value <= $step * $power) {
                return $step * $power;
            }
        }

        return 10 * $power;
    }
}
