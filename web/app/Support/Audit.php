<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class Audit
{
    /**
     * Write one audit line. $who defaults to the signed-in username, or "system".
     */
    public static function log($action, $detail = '', $who = null)
    {
        if ($who === null) {
            $user = auth()->user();
            $who = $user ? $user->username : 'system';
        }

        Log::channel('audit')->info(implode(' - ', [
            self::clean($who),
            strtoupper(self::clean($action)),
            self::clean($detail),
        ]));
    }

    // One line per entry: no line breaks, and no " - " that would blur the fields.
    private static function clean($text)
    {
        return trim(str_replace([" - ", "\r", "\n"], [' – ', ' ', ' '], (string) $text));
    }
}
