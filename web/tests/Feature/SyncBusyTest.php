<?php

namespace Tests\Feature;

use App\Support\Admin\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RegistersDevice;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class SyncBusyTest extends TestCase
{
    use RefreshDatabase, RegistersDevice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->registerDevice();
    }

    public function testEveryDataCallAnswersBusyWhileABackupRuns()
    {
        Cache::put(BackupRun::BUSY_KEY, true, 60);

        foreach (['GET' => ['/api/pull/config', '/api/pull/customers'], 'POST' => ['/api/pull/customers/ack', '/api/push/sales', '/api/sync/initial-done', '/api/sync/uploaded']] as $method => $uris) {
            foreach ($uris as $uri) {
                $response = $this->json($method, $uri, [])->assertStatus(503);
                $this->assertSame('busy', json_decode($response->getContent(), true)['status']);
                $this->assertSame('30', $response->headers->get('Retry-After'));
            }
        }
    }

    public function testPingAndTheDeviceCheckStayAvailable()
    {
        Cache::put(BackupRun::BUSY_KEY, true, 60);

        $this->get('/api/ping')->assertOk();
        $this->get('/api/device')->assertOk();
    }

    public function testSyncWorksAgainOnceTheFlagIsGone()
    {
        Cache::put(BackupRun::BUSY_KEY, true, 60);
        $this->get('/api/pull/config')->assertStatus(503);

        Cache::forget(BackupRun::BUSY_KEY);

        $this->get('/api/pull/config')->assertOk();
        $this->assertFalse(BackupRun::isRunning());
    }
}
