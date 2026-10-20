<?php

namespace App\Support;

use App\Models\Remainder;
use App\Models\Supplement;
use App\Models\Ticket;

// The tickets were paid for in full, so what was not dispensed becomes a new ticket for the customer: already sold, no sale behind it,
// with the remainder row that says which supply it came from (none for an opening remainder). The Smart210 gets the ticket with the next pull.
class RemainderMaker
{
    // The kg a supply may still return as a remainder: what its tickets covered and was not dispensed, when it has no remainder yet.
    public static function room(Supplement $supplement)
    {
        if ($supplement->remainder()->exists()) {
            return 0.0;
        }

        return max(0.0, round($supplement->preset_kg - $supplement->out_amount_kg, 2));
    }

    // $createdAt: the moment of the remainder (the moment of the supply for a remainder made by a supply); $date: its day.
    public static function make(Supplement $supplement, $leftKg, $createdAt, $date, $comment = null)
    {
        return self::create($supplement->id, $supplement->fuel_type, $leftKg, $createdAt, $date, $comment);
    }

    // An opening remainder (Initialize): a remainder ticket that existed before the program, so no supply is behind it (supplement_id NULL).
    public static function opening($fuelType, $kg, $date, $comment = null)
    {
        return self::create(null, $fuelType, $kg, now(), $date, $comment);
    }

    private static function create($supplementId, $fuelType, $leftKg, $createdAt, $date, $comment = null)
    {
        $code = config('ticket_reserved_codes.remainder');
        $serial = SerialCounter::next($code);
        $barcode = Barcode::makeReserved($code, $serial);

        Ticket::forceCreate([
            'barcode' => $barcode, 'fuel_type' => $fuelType, 'spec' => null,
            'code' => $code, 'serial' => $serial, 'amount_kg' => $leftKg,
            'status' => config('ticket_statuses.sale'), 'sale_date' => now(), 'synced' => false,
        ]);

        return Remainder::forceCreate([
            'supplement_id' => $supplementId, 'ticket_barcode' => $barcode, 'fuel_type' => $fuelType,
            'amount_kg' => $leftKg, 'comment' => ($comment !== null && trim($comment) !== '') ? trim($comment) : null,
            'remainder_date' => $date, 'created_at' => $createdAt,
        ]);
    }
}
