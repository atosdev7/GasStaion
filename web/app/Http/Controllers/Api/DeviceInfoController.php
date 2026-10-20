<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Api;
use Illuminate\Http\Request;

class DeviceInfoController extends Controller
{
    // Tells the Smart210 that its address is registered, plus the current device settings.
    public function show(Request $request)
    {
        $device = $request->attributes->get('device');

        return Api::response([
            'status' => 'ok',
            'device_id' => (int) $device->id,
            'name' => $device->name,
            'purge_days' => (int) $device->purge_days,
            'initial_sync_done' => (bool) $device->initial_sync_done,
            'server_time' => now()->format('Y-m-d H:i:s'),
        ]);
    }
}
