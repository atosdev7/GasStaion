<?php

namespace App\Support\Sync;

use Carbon\Carbon;

// What a Smart210 needs to keep time with this server: the time in the station's own time zone (config app.timezone, set with APP_TIMEZONE),
// the same moment in UTC, the name of the zone and its offset from UTC right now (it changes with daylight saving time). With these the terminal
// can set its clock exactly (UTC), tell its business day (9 AM to 9 AM) in the station's time and follow a change of the offset.
class ServerTime
{
    public static function fields()
    {
        $now = Carbon::now();

        return [
            'server_time' => $now->format('Y-m-d H:i:s'),
            'server_time_utc' => $now->copy()->utc()->format('Y-m-d H:i:s'),
            'timezone' => $now->getTimezone()->getName(),
            'utc_offset_seconds' => $now->getOffset(),
        ];
    }
}
