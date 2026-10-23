<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

// The request limit of the device API (a Smart210 and its address), read from the configuration at the moment of the request:
// config('sync.throttle_per_minute'), set with API_THROTTLE_PER_MINUTE in .env. (Writing the number into the middleware list of the HTTP kernel
// does not work for .env: the kernel is made before Laravel has read it.)
class ThrottleDevices extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        return parent::handle($request, $next, (int) config('sync.throttle_per_minute'), 1, $prefix);
    }
}
