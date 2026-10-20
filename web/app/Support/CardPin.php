<?php

namespace App\Support;

// The PIN of a card: a salted SHA-256, the same kind the Smart210 checks for the cashier login (see DeviceHash). 4 to 8 digits.
class CardPin
{
    // [salt, hash] for a PIN typed on the web.
    public static function make($pin)
    {
        $salt = bin2hex(random_bytes(8));

        return ['pin_salt' => $salt, 'pin_hash' => DeviceHash::make($salt, (string) $pin)];
    }

    public static function check($card, $pin)
    {
        return $card->pin_hash !== null && hash_equals($card->pin_hash, DeviceHash::make((string) $card->pin_salt, (string) $pin));
    }
}
