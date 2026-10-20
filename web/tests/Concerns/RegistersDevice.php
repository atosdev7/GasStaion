<?php

namespace Tests\Concerns;

use App\Models\Device;
use Illuminate\Support\Facades\DB;

/**
 * A Smart210 is recognised by the IP address it calls from, and every test request comes from 127.0.0.1: a device with
 * that address is "the Smart210" of a test, any other address is a stranger.
 */
trait RegistersDevice
{
    protected function registerDevice($name = 'POS1', $ip = '127.0.0.1')
    {
        // What the Devices screen gives a new device: the default PIN from the settings.
        $s = DB::table('settings')->pluck('value', 'key');

        return Device::forceCreate([
            'name' => $name, 'ip_address' => $ip, 'purge_days' => 3,
            'pin_salt' => $s['default_device_pin_salt'] ?? null, 'pin_hash' => $s['default_device_pin_hash'] ?? null,
        ]);
    }

    // A call to the API from an address nobody registered.
    protected function fromAStranger($method, $uri, array $data = [])
    {
        return $this->call($method, $uri, [], [], [], [
            'REMOTE_ADDR' => '10.9.9.9', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], json_encode($data));
    }
}
