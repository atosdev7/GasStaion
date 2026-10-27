<?php

namespace App\Support\Tickets;

use App\Models\Tickets\OpeningTicket;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketPublish;

// How many of the old paper tickets entered in Initialize > Ticket Sales customers still hold. Every old ticket that is supplied gets a row (LegacyTickets), so what is
// left of an entry is its quantity less the old tickets of the same fuel type and spec supplied since its "as of" day. A supplied ticket is counted against
// the oldest entry it can belong to, once. Wrong counts are corrected by editing the entry.
class OpeningTickets
{
    /** The entries, oldest first, each with what was supplied against it and what is left: [['row', 'supplied', 'left'], ...]. */
    public static function rows()
    {
        $goLive = LegacyTickets::goLive();
        $old = $goLive === null ? [] : TicketPublish::where('date', '<', $goLive)->pluck('id')->all();

        $used = [];      // "fuel|spec" => the supply days of the old tickets used, oldest first
        if ($old) {
            foreach (Ticket::whereIn('publish_id', $old)->where('status', config('ticket_statuses.supply'))->whereNotNull('supply_date')
                         ->orderBy('supply_date')->get(['fuel_type', 'spec', 'supply_date']) as $ticket) {
                $used[$ticket->fuel_type . '|' . $ticket->spec][] = substr((string) $ticket->supply_date, 0, 10);
            }
        }

        $result = [];
        foreach (OpeningTicket::orderBy('as_of')->orderBy('id')->get() as $row) {
            $key = $row->fuel_type . '|' . $row->spec;
            $supplied = 0;
            foreach ($used[$key] ?? [] as $i => $day) {
                if ($supplied >= $row->quantity) {
                    break;
                }
                if ($day >= substr((string) $row->as_of, 0, 10)) {
                    $supplied++;
                    unset($used[$key][$i]);
                }
            }
            $result[] = ['row' => $row, 'supplied' => $supplied, 'left' => $row->quantity - $supplied];
        }

        return $result;
    }

    /** The kg still out there per fuel type: fuel type => ['count', 'kg']. */
    public static function owed()
    {
        $owed = [];
        foreach (self::rows() as $entry) {
            $fuel = $entry['row']->fuel_type;
            $owed[$fuel]['count'] = ($owed[$fuel]['count'] ?? 0) + $entry['left'];
            $owed[$fuel]['kg'] = ($owed[$fuel]['kg'] ?? 0) + $entry['left'] * (float) config('ticket_specs.' . $entry['row']->spec);
        }

        return $owed;
    }
}
