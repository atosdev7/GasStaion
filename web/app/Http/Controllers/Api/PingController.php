<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\Device;
use App\Support\Sync\Api;
use Illuminate\Http\Request;

class PingController extends Controller
{
    // Public connection test for the Smart210 server-setting page. It also tells whether the caller's IP address is a
    // registered device, so the screen can say "ask the admin to add this address" instead of failing later.
    public function ping(Request $request)
    {
        $device = Device::where('ip_address', $request->ip())->first();

        return Api::response([
            'status' => 'ok',
            'server_time' => now()->format('Y-m-d H:i:s'),
            'registered' => $device !== null,
            'device_id' => $device ? (int) $device->id : null,
            'device_name' => $device ? $device->name : null,
            'ip' => $request->ip(),
        ]);
    }
}
