<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Admin\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RegistersDevice;
use Tests\TestCase;

// How a Smart210 is let in: by the IP address the admin gave it on the Devices screen.
class DeviceApiTest extends TestCase
{
    use RefreshDatabase, RegistersDevice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function payload($response)
    {
        return json_decode($response->getContent(), true);
    }

    public function testPingNeedsNothingAndReturnsServerTime()
    {
        $res = $this->get('/api/ping')->assertOk();
        $x = $this->payload($res);

        $this->assertSame('ok', $x['status']);
        $this->assertRegExp('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $x['server_time']);
        $this->assertStringContainsString('application/json', $res->headers->get('Content-Type'));
    }

    public function testTheServerTellsItsTimeInUtcItsZoneAndItsOffset()
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('Asia/Seoul');   // the station's zone (APP_TIMEZONE): UTC+9, no daylight saving time
        try {
            $ping = $this->payload($this->get('/api/ping')->assertOk());

            $this->assertSame('Asia/Seoul', $ping['timezone']);
            $this->assertSame(32400, $ping['utc_offset_seconds']);
            $this->assertRegExp('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $ping['server_time_utc']);
            // the same moment twice: local time is UTC plus nine hours
            $this->assertSame(32400, strtotime($ping['server_time'] . ' UTC') - strtotime($ping['server_time_utc'] . ' UTC'));

            // a zone with daylight saving time: the offset is the one of this moment
            date_default_timezone_set('America/New_York');
            $new = $this->payload($this->get('/api/ping')->assertOk());
            $this->assertContains($new['utc_offset_seconds'], [-18000, -14400]);
            $this->assertSame($new['utc_offset_seconds'], strtotime($new['server_time'] . ' UTC') - strtotime($new['server_time_utc'] . ' UTC'));
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testTheDeviceAndConfigAnswersCarryTheTimeToo()
    {
        $this->registerDevice();

        foreach (['/api/device', '/api/pull/config'] as $path) {
            $x = $this->payload($this->get($path)->assertOk());
            $this->assertSame(config('app.timezone'), $x['timezone'], $path);
            $this->assertArrayHasKey('server_time_utc', $x);
            $this->assertIsInt($x['utc_offset_seconds']);
        }
    }
    public function testPingTellsWhetherTheCallersAddressIsRegistered()
    {
        $x = $this->payload($this->get('/api/ping')->assertOk());
        $this->assertFalse($x['registered']);
        $this->assertNull($x['device_id']);
        $this->assertSame('127.0.0.1', $x['ip']);

        $device = $this->registerDevice();

        $x = $this->payload($this->get('/api/ping')->assertOk());
        $this->assertTrue($x['registered']);
        $this->assertSame((int) $device->id, $x['device_id']);
        $this->assertSame('POS1', $x['device_name']);
    }

    public function testARegisteredAddressCanUseTheApi()
    {
        $device = $this->registerDevice();

        $info = $this->payload($this->get('/api/device')->assertOk());

        $this->assertSame((int) $device->id, $info['device_id']);
        $this->assertSame('POS1', $info['name']);
        $this->assertSame(3, $info['purge_days']);
        $this->assertFalse($info['initial_sync_done']);
    }

    public function testAnUnregisteredAddressIsRefusedEverywhereButPing()
    {
        $this->registerDevice('POS1', '192.168.1.50'); // another terminal's address, not this caller's

        foreach ([['GET', '/api/device'], ['GET', '/api/pull/config'], ['GET', '/api/pull/customers'],
                  ['POST', '/api/pull/customers/ack'], ['POST', '/api/push/sales'], ['POST', '/api/sync/begin'],
                  ['POST', '/api/sync/initial-done']] as $call) {
            $res = $this->fromAStranger($call[0], $call[1])->assertStatus(403);
            $this->assertSame('error', $this->payload($res)['status']);
            $this->assertSame('this IP address is not registered', $this->payload($res)['message']);
        }
        $this->get('/api/device')->assertStatus(403); // this test client is 127.0.0.1, which nobody registered either
        $this->fromAStranger('GET', '/api/ping')->assertOk();
    }

    public function testADeviceWithNoAddressMatchesNobody()
    {
        Device::forceCreate(['name' => 'NOIP', 'purge_days' => 3]); // an address-less row must not match any caller

        $this->get('/api/device')->assertStatus(403);
    }

    // A device has no `active` flag; "removed" means soft-deleted, and its address stops working.
    public function testADeletedDeviceLosesAccess()
    {
        $device = $this->registerDevice();
        $this->get('/api/device')->assertOk();

        $device->delete();

        $this->get('/api/device')->assertStatus(403);
        $this->assertFalse($this->payload($this->get('/api/ping'))['registered']);
    }

    public function testTheTokenRoutesAreGone()
    {
        $this->registerDevice();

        $this->post('/api/register', ['username' => 'cash', 'password' => 'x'])->assertStatus(404);
        $this->get('/api/cashiers')->assertStatus(404);
    }

    // ---- sync/begin: what registering used to do for a new or replaced board ----

    public function testBeginPutsEveryPullTableBackToUnsyncedAndClearsInitialDone()
    {
        $device = $this->registerDevice();
        $device->initial_sync_done = true;
        $device->save();
        Customer::forceCreate(['name' => 'A']);
        Customer::query()->toBase()->update(['synced' => true]);

        $this->postJson('/api/sync/begin')->assertOk();

        $this->assertFalse((bool) $device->fresh()->initial_sync_done);
        $this->assertSame(0, Customer::where('synced', true)->count()); // A and the seeded Self come again
    }

    public function testBeginIsRepeatable()
    {
        $this->registerDevice();

        $this->postJson('/api/sync/begin')->assertOk();
        $this->postJson('/api/sync/begin')->assertOk();
    }

    public function testTooManyCallsAreAnswered429WithHowLongToWaitAndTheLimitIsRead()
    {
        // the terminal reads the 429 and the Retry-After header and asks again after that many seconds (ApiClient / ApiReply)
        config(['sync.throttle_per_minute' => 3]);
        $this->get('/api/ping')->assertOk();
        $this->get('/api/ping')->assertOk();
        $this->get('/api/ping')->assertOk();

        $res = $this->get('/api/ping')->assertStatus(429);
        $this->assertGreaterThan(0, (int) $res->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(60, (int) $res->headers->get('Retry-After'));
    }
}
