<?php

namespace App\Support\Tickets;

/**
 * Ticket barcode: 5-digit sign + 2-digit code + 9-digit serial = 16 characters, stored and
 * handled as a zero-padded string (an identifier, not a quantity — never added to or compared
 * numerically, and a string keeps leading zeros in the sign/serial exact).
 *
 * The sign is one fixed constant for the whole business (config/ticket_sign.php), never per-ticket.
 * The code is a fixed, arbitrary 2-digit number per (fuel type, spec), assigned once at business
 * setup (config/ticket_codes.php) — not derived from the fuel type or spec by any formula.
 * A ticket a POS issues itself (the Smart210, or a sale entered on the web app) has a code of its own (config/ticket_pos_codes.php): the first digit is the fuel type
 * (1 gasoline, 2 diesel), the second the kind of the ticket (1 spot sale, 2 self use, 3 other use, 4 ticket loan, 5 card, 9 remainder), and its serial counts per code.
 * Those are called reserved codes here; codeFor() refuses to hand out one, since it can never be a press code.
 */
class Barcode
{
    const MAX_SERIAL = 999999999;

    /** The 2-digit code for a fuel type at a given spec, or null if either isn't configured. */
    public static function codeFor($fuelType, $spec)
    {
        $code = config('ticket_codes.' . $fuelType . '.' . $spec);
        if ($code === null || !ctype_digit((string) $code) || strlen((string) $code) !== 2) {
            return null;
        }
        if (self::isReservedCode($code)) {
            return null;
        }

        return (string) $code;
    }

    public static function make($fuelType, $spec, $serial)
    {
        $code = self::codeFor($fuelType, $spec);

        return sprintf('%05d%2s%09d', config('ticket_sign'), $code, $serial);
    }

    // For a ticket a POS issued: `$code` is its code (see posCode()), not derived from a spec.
    public static function makeReserved($code, $serial)
    {
        return sprintf('%05d%2s%09d', config('ticket_sign'), $code, $serial);
    }

    public static function parse($barcode)
    {
        $s = str_pad((string) $barcode, 16, '0', STR_PAD_LEFT);

        return ['sign' => substr($s, 0, 5), 'code' => substr($s, 5, 2), 'serial' => (int) substr($s, 7)];
    }

    // The code of a ticket a POS issues: `$type` is direct (spot sale), self, other, ticket_loan, card or remainder; null when the fuel type or the kind is unknown.
    public static function posCode($type, $fuelType)
    {
        $fuel = config('ticket_pos_codes.fuel.' . $fuelType);
        $kind = config('ticket_pos_codes.type.' . $type);

        return $fuel === null || $kind === null ? null : $fuel . $kind;
    }

    // Every code a POS ticket can have (fuel type x kind).
    public static function posCodes()
    {
        $codes = [];
        foreach (config('ticket_pos_codes.fuel') as $fuel) {
            foreach (config('ticket_pos_codes.type') as $kind) {
                $codes[] = $fuel . $kind;
            }
        }

        return $codes;
    }

    // Is this the code of a ticket a POS issues (not a press ticket)? codeFor() never returns one.
    public static function isReservedCode($code)
    {
        $code = (string) $code;

        return strlen($code) === 2 && in_array($code[0], config('ticket_pos_codes.fuel'), true) && in_array($code[1], config('ticket_pos_codes.type'), true);
    }

    // What a POS ticket is (direct, self, other, ticket_loan, card, remainder), or null for a press ticket.
    public static function posType($code)
    {
        if (!self::isReservedCode($code)) {
            return null;
        }
        $kind = array_search(((string) $code)[1], config('ticket_pos_codes.type'), true);

        return $kind === false ? null : $kind;
    }

    // The fuel type letter of a POS ticket's code, or null for a press ticket.
    public static function posFuel($code)
    {
        if (!self::isReservedCode($code)) {
            return null;
        }
        $fuel = array_search(((string) $code)[0], config('ticket_pos_codes.fuel'), true);

        return $fuel === false ? null : $fuel;
    }
}
