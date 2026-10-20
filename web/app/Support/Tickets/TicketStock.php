<?php

namespace App\Support\Tickets;

use App\Models\Tickets\TicketInbound;
use App\Models\Tickets\TicketPublish;

/**
 * Blank ticket stock per (fuel type, spec): received by inbounds minus published (see TicketPublish).
 * spec is a key into config/ticket_specs.php (the ticket's fixed cylinder weight).
 */
class TicketStock
{
    /** @return \Illuminate\Support\Collection of objects: fuel_type, spec, received, published, available */
    public static function summary()
    {
        // Eloquent, not DB::table(): both models are soft-deletable, and going through the model
        // keeps a deleted row out of the sums automatically instead of relying on a hand-added filter.
        $received = TicketInbound::query()
            ->selectRaw('fuel_type, spec, SUM(quantity) AS n')
            ->groupBy('fuel_type', 'spec')->get();

        $published = TicketPublish::query()
            ->selectRaw('fuel_type, spec, SUM(end_serial - start_serial + 1) AS n')
            ->groupBy('fuel_type', 'spec')->get();

        $pub = [];
        foreach ($published as $p) {
            $pub[$p->fuel_type . ':' . $p->spec] = (int) $p->n;
        }

        return $received->map(function ($r) use ($pub) {
            $done = $pub[$r->fuel_type . ':' . $r->spec] ?? 0;

            return (object) [
                'fuel_type' => $r->fuel_type,
                'spec' => (int) $r->spec,
                'received' => (int) $r->n,
                'published' => $done,
                'available' => (int) $r->n - $done,
            ];
        })->sortBy('fuel_type')->values();
    }

    public static function available($fuelType, $spec)
    {
        $row = static::summary()->first(function ($r) use ($fuelType, $spec) {
            return $r->fuel_type === (string) $fuelType && (int) $r->spec === (int) $spec;
        });

        return $row ? $row->available : 0;
    }
}
