<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\Api;
use Closure;

/**
 * Authenticates a Smart210 by the IP address it calls from. The network is closed and the admin owns the addresses: each
 * device is added on the Devices screen with its address, and only those addresses may use the API (ping excepted).
 */
class DeviceIp
{
    public function handle($request, Closure $next)
    {
        // A deleted device stops working: Eloquent leaves soft-deleted rows out.
        $device = Device::where('ip_address', $request->ip())->first();

        if (!$device) {
            return Api::error('this IP address is not registered', 403);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
