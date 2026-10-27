<?php

namespace App\Support\Tickets;

use App\Models\Admin\Setting;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketPublish;
use Carbon\Carbon;

// Press tickets that were published BEFORE the go-live date (Config for Admin > Go-live) and sold on paper before it. Nobody knows which of them a customer
// still holds, so no row is kept for the old ones: the publish record of the range is enough. A ticket with no row whose serial is inside a publish dated
// before go-live is a ticket sold before go-live; the first time one is used (supplied, restricted, surrendered for a loan) its row is made, sold, with the
// publish date of its publish as its publish date and its sale date, and the action goes on from there. A ticket that has a row follows its row (a
// ticket still in the stock room is published, never sold). Tickets published on or after go-live have no such fall-back: no row means no ticket.
// The rows of the old tickets that were used are never cleared by a backup (BackupRun), or a used one would become usable again.
class LegacyTickets
{
    const SETTING = 'go_live_date';

    // The go-live date (Y-m-d), or null while it is not set (then nothing is old).
    public static function goLive()
    {
        $value = optional(Setting::find(self::SETTING))->value;

        return $value ? substr((string) $value, 0, 10) : null;
    }

    // The publish record of the range this barcode is in, when the ticket is an old one: the sign is the business's, the publish is dated before go-live.
    public static function publishFor($barcode)
    {
        $goLive = self::goLive();
        if ($goLive === null) {
            return null;
        }

        $parsed = Barcode::parse($barcode);
        if (strlen((string) $barcode) !== 16 || $parsed['sign'] !== sprintf('%05d', config('ticket_sign'))) {
            return null;
        }

        return TicketPublish::where('code', $parsed['code'])->where('start_serial', '<=', $parsed['serial'])->where('end_serial', '>=', $parsed['serial'])
            ->where('date', '<', $goLive)->first();
    }

    // Makes the row of every old ticket among these barcodes that has none: sold, on the day of its publish. Safe to repeat (a ticket is made once).
    // Returns the tickets it made.
    public static function ensure($barcodes)
    {
        $barcodes = array_values(array_unique(array_filter((array) $barcodes, function ($barcode) {
            return is_string($barcode) && strlen($barcode) === 16 && ctype_digit($barcode);
        })));
        if (!$barcodes || self::goLive() === null) {
            return [];
        }

        $known = Ticket::whereIn('barcode', $barcodes)->pluck('barcode')->all();
        $made = [];
        foreach (array_diff($barcodes, $known) as $barcode) {
            $publish = self::publishFor($barcode);
            if (!$publish) {
                continue;
            }

            $day = Carbon::parse($publish->date)->startOfDay();
            $now = now();
            Ticket::insertOrIgnore([[
                'barcode' => $barcode, 'code' => $publish->code, 'serial' => Barcode::parse($barcode)['serial'], 'publish_id' => $publish->id,
                'publish_date' => $day, 'fuel_type' => $publish->fuel_type, 'spec' => $publish->spec, 'status' => config('ticket_statuses.sale'),
                'sale_date' => $day, 'synced' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]]);
            $made[] = $barcode;
        }

        return $made;
    }

    // The ranges of the old publishes, for the config of the terminals: a terminal that has no row for a ticket looks here.
    public static function ranges()
    {
        $goLive = self::goLive();
        if ($goLive === null) {
            return [];
        }

        return TicketPublish::where('date', '<', $goLive)->orderBy('id')->get()->map(function ($publish) {
            return [
                'code' => $publish->code, 'from' => (int) $publish->start_serial, 'to' => (int) $publish->end_serial, 'fuel_type' => $publish->fuel_type,
                'spec' => (int) $publish->spec, 'kg' => number_format((float) config('ticket_specs.' . $publish->spec), 2, '.', ''), 'date' => substr((string) $publish->date, 0, 10),
            ];
        })->all();
    }
}
