<?php

namespace App\Support\Reports;

use App\Http\Controllers\Reports\ReportController;
use App\Models\Cards\Card;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketPublish;
use App\Support\Cards\CardLedger;
use App\Support\Dashboard\TankLevels;
use App\Support\Tickets\LegacyTickets;
use App\Support\Tickets\OpeningTickets;
use Carbon\Carbon;

// The numbers for deciding when and how much fuel to order, per fuel type (all in kg): what the tanks hold, what is owed on tickets that were sold but not supplied yet
// (the tickets the system sold, the old paper tickets entered in Initialize > Ticket Sales, and the kg the cards hold), what is free after that, and how fast fuel is supplied and sold
// (average per day over the last 7, 30 and 90 full days) with the days the free fuel lasts at that speed.
class StockOrdering
{
    const WINDOWS = [7, 30, 90];

    /** @return array fuel type => object: tanks, system_owed, old_owed, card_owed, owed, free, supplied[days], sold[days], cover[days] (null: nothing supplied) */
    public static function rows()
    {
        $tanks = [];
        foreach (TankLevels::all() as $level) {
            $tanks[$level->tank->fuel_type] = ($tanks[$level->tank->fuel_type] ?? 0) + $level->current;
        }

        $system = static::systemOwed();
        $paper = OpeningTickets::owed();
        $cards = static::cardOwed();
        $end = today()->subDay();
        $goLive = LegacyTickets::goLive();

        $rows = [];
        foreach (config('fuel_types') as $fuel => $name) {
            $row = (object) [
                'fuel_type' => $fuel,
                'tanks' => (float) ($tanks[$fuel] ?? 0),
                'system_owed' => (float) ($system[$fuel] ?? 0),
                'old_owed' => (float) (isset($paper[$fuel]) ? $paper[$fuel]['kg'] : 0),
                'card_owed' => (float) ($cards[$fuel] ?? 0),
                'supplied' => [], 'sold' => [], 'cover' => [],
            ];
            $row->owed = $row->system_owed + $row->old_owed + $row->card_owed;
            $row->free = $row->tanks - $row->owed;

            foreach (self::WINDOWS as $days) {
                $from = $end->copy()->subDays($days - 1);
                // the system has not run for the whole window yet: the days it has run are the ones that count
                $length = $days;
                if ($goLive !== null && $goLive > $from->toDateString()) {
                    $length = min($days, max(1, $end->diffInDays(Carbon::parse($goLive), false) * -1 + 1));
                }
                $row->supplied[$days] = static::suppliedKg($fuel, $from, $end) / $length;
                $row->sold[$days] = static::soldKg($fuel, $from, $end) / $length;
                $row->cover[$days] = $row->supplied[$days] > 0 ? $row->free / $row->supplied[$days] : null;
            }
            $rows[$fuel] = $row;
        }

        return $rows;
    }

    // The kg of the tickets the system sold that nobody has had supplied yet, per fuel type. The old tickets whose row was made when one was scanned are counted with
    // the old paper tickets (OpeningTickets), not here.
    private static function systemOwed()
    {
        $goLive = LegacyTickets::goLive();
        $query = Ticket::where('status', config('ticket_statuses.sale'));
        if ($goLive !== null) {
            $query->where(function ($q) use ($goLive) {
                $q->whereNull('publish_id')->orWhereNotIn('publish_id', TicketPublish::where('date', '<', $goLive)->select('id'));
            });
        }

        $owed = [];
        foreach ($query->get(['fuel_type', 'spec', 'amount_kg']) as $ticket) {
            $kg = $ticket->amount_kg !== null ? (float) $ticket->amount_kg : (float) config('ticket_specs.' . $ticket->spec);
            $owed[$ticket->fuel_type] = ($owed[$ticket->fuel_type] ?? 0) + $kg;
        }

        return $owed;
    }

    // The kg the cards hold, per fuel type: fuel that was paid for and has to be supplied, like a ticket sold and not supplied yet. A card below zero (it was
    // supplied more than it held, see the Cards page) owes nothing to the holder and does not lower what the others hold; a deleted card is not counted.
    private static function cardOwed()
    {
        $balances = CardLedger::balances();
        $owed = [];
        foreach (Card::whereIn('id', $balances->keys()->all())->get(['id', 'fuel_type']) as $card) {
            $kg = (float) $balances[$card->id];
            if ($kg > 0) {
                $owed[$card->fuel_type] = ($owed[$card->fuel_type] ?? 0) + $kg;
            }
        }

        return $owed;
    }

    private static function suppliedKg($fuel, Carbon $from, Carbon $to)
    {
        return (float) Supplement::where('fuel_type', $fuel)
            ->whereRaw(ReportController::day('supplied_at', 'supply_date') . ' BETWEEN ? AND ?', [$from->toDateString(), $to->toDateString()])
            ->sum('out_amount_kg');
    }

    // Fuel sold as sales (not the self use or given away), the returns taken off.
    private static function soldKg($fuel, Carbon $from, Carbon $to)
    {
        return (float) Sale::where('fuel_type', $fuel)->where('usage', 'sale')->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])->sum('amount_kg');
    }
}
