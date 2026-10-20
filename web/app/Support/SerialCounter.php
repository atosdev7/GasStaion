<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Ticket;

// Next serial for a ticket code minted on the fly (e.g. spot-sale 'direct' tickets). The highest
// serial ever issued is kept in settings, because backup removes used tickets and "max(serial) + 1"
// would then hand out a barcode that was already issued. Call inside the minting transaction.
class SerialCounter
{
    public static function next($code)
    {
        $key = 'serial_high_' . $code;
        // The counter row is made first, so the lock below always has a row to hold: with no row yet two sales at the very first use
        // would both read "nothing" and take the same serial. A second maker of the row waits for the first one's transaction.
        Setting::insertOrIgnore(['key' => $key, 'value' => '0']);
        $stored = Setting::lockForUpdate()->find($key);
        // Serials above the device base belong to the Smart210 and must not push the web app's numbering up into them.
        $highest = Ticket::where('code', $code)->where('serial', '<=', config('ticket_serial.device_base'))->max('serial');
        $next = max((int) optional($stored)->value, (int) $highest) + 1;

        Setting::updateOrCreate(['key' => $key], ['value' => (string) $next]);

        return $next;
    }
}
