<?php

namespace App\Support;

/**
 * JSON replies for the Smart210 API. Every reply has a "status" ("ok" or "error").
 * Conventions: ids and counts are JSON numbers, flags are true/false, missing values are null,
 * decimals (kg, money) are strings such as "12.50", and times are "YYYY-MM-DD HH:MM:SS" in the station's local time.
 */
class Api
{
    public static function response(array $data, $status = 200)
    {
        return response()->json($data, $status);
    }

    public static function error($message, $status)
    {
        return self::response(['status' => 'error', 'message' => $message], $status);
    }
}
