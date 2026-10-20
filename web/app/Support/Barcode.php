<?php

namespace App\Support;

/**
 * Ticket barcode: 5-digit sign + 2-digit code + 9-digit serial = 16 characters, stored and
 * handled as a zero-padded string (an identifier, not a quantity — never added to or compared
 * numerically, and a string keeps leading zeros in the sign/serial exact).
 *
 * The sign is one fixed constant for the whole business (config/ticket_sign.php), never per-ticket.
 * The code is a fixed, arbitrary 2-digit number per (fuel type, spec), assigned once at business
 * setup (config/ticket_codes.php) — not derived from the fuel type or spec by any formula.
 * Codes 21-25 are reserved for tickets the Smart210 creates itself (direct/self/other/ticket_loan/
 * remainder); codeFor() refuses to hand out a configured code in that range, since it can never be
 * a valid business (fuel type, spec) code.
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

    // For a reserved-code ticket (see config/ticket_reserved_codes.php) — the code is the fixed
    // reserved number itself, not derived from a fuel type/spec.
    public static function makeReserved($code, $serial)
    {
        return sprintf('%05d%2s%09d', config('ticket_sign'), $code, $serial);
    }

    public static function parse($barcode)
    {
        $s = str_pad((string) $barcode, 16, '0', STR_PAD_LEFT);

        return ['sign' => substr($s, 0, 5), 'code' => substr($s, 5, 2), 'serial' => (int) substr($s, 7)];
    }

    // Only meaningful for the Smart210's own numeric codes (21-25); codeFor() never returns one.
    public static function isReservedCode($code)
    {
        return (int) $code >= 21 && (int) $code <= 25;
    }
}
