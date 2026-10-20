<?php

namespace Tests\Feature;

use App\Models\Admin\Device;
use App\Models\Admin\SyncError;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class SyncScreenTest extends TestCase
{
    use RefreshDatabase;

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function error($reason = 'unknown customer', $recordId = 'abc', $payload = '{"id":"abc"}')
    {
        return SyncError::record(Device::forceCreate(['name' => 'POS' . SyncError::count()])->id, 'sales', $recordId, $payload, $reason);
    }

    public function testWhoCanOpenTheScreenAndResolve()
    {
        $error = $this->error();

        foreach (['admin', 'manager', 'accountant', 'assistant'] as $role) {
            $this->as($role)->get('/sync')->assertOk();
        }
        $this->as('cashier')->get('/sync')->assertForbidden();
        $this->as('cashier')->post('/sync/errors/' . $error->id . '/resolve')->assertForbidden();
        $this->assertNull($error->fresh()->resolved_at);
    }

    public function testDevicesShowTheirSyncState()
    {
        Device::forceCreate(['name' => 'Never synced']);
        Device::forceCreate(['name' => 'Fresh one', 'last_synced_at' => now()->subMinutes(5)]);
        Device::forceCreate(['name' => 'Old one', 'last_synced_at' => now()->subHours(31)]);

        $html = $this->as('manager')->get('/sync')->assertOk()->getContent();

        $this->assertRegExp('/Never synced.*?' . preg_quote(__('sync.never'), '/') . '/s', $html);
        $this->assertRegExp('/Fresh one.*?' . preg_quote(__('sync.ok'), '/') . '/s', $html);
        $this->assertRegExp('/Old one.*?' . preg_quote(__('sync.stale'), '/') . '/s', $html);
    }

    public function testOpenErrorsAreListedAndResolvedOnesOnlyOnRequest()
    {
        $open = $this->error('customer does not exist');
        $done = $this->error('totals do not add up', 'def');
        $done->forceFill(['resolved_at' => now(), 'resolved_by' => 1])->save();

        $this->as('manager')->get('/sync')->assertOk()
            ->assertSee('customer does not exist')
            ->assertDontSee('totals do not add up');

        $this->get('/sync?all=1')->assertOk()
            ->assertSee('customer does not exist')
            ->assertSee('totals do not add up');
    }

    public function testResolvingMarksWhoAndWhenAndLeavesTheOthers()
    {
        $one = $this->error('first', 'a');
        $two = $this->error('second', 'b');

        $this->as('accountant')->post('/sync/errors/' . $one->id . '/resolve')->assertRedirect('/sync');

        $this->assertNotNull($one->fresh()->resolved_at);
        $this->assertSame(User::where('role', 'accountant')->value('id'), (int) $one->fresh()->resolved_by);
        $this->assertNull($two->fresh()->resolved_at);
        $this->post('/sync/errors/' . $one->id . '/resolve')->assertNotFound(); // already done
    }

    public function testAConflictIsMarkedAndAPayloadIsShownEscaped()
    {
        $this->error('conflict: the ticket is already supply on the server', 'T1', '{"note":"<script>alert(1)</script>"}');

        $this->as('manager')->get('/sync')->assertOk()
            ->assertSee(__('sync.conflict'))
            ->assertSee('the ticket is already supply on the server')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function testTheSameOpenErrorIsCountedNotRepeated()
    {
        $device = Device::forceCreate(['name' => 'POS1']);

        SyncError::record($device->id, 'sales', 'abc', '{"v":1}', 'unknown customer');
        $again = SyncError::record($device->id, 'sales', 'abc', '{"v":2}', 'unknown customer');

        $this->assertSame(1, SyncError::count());
        $this->assertSame(2, (int) $again->fresh()->occurrences);
        $this->assertSame('{"v":2}', $again->fresh()->payload); // the latest row as sent
    }

    public function testDifferentErrorsAndResolvedOnesStartANewEntry()
    {
        $device = Device::forceCreate(['name' => 'POS1']);
        $first = SyncError::record($device->id, 'sales', 'abc', '{}', 'unknown customer');

        SyncError::record($device->id, 'sales', 'abc', '{}', 'totals do not add up'); // another reason
        SyncError::record($device->id, 'sales', 'xyz', '{}', 'unknown customer'); // another record
        SyncError::record($device->id, 'tickets', 'abc', '{}', 'unknown customer'); // another table
        $this->assertSame(4, SyncError::count());

        $first->forceFill(['resolved_at' => now()])->save();
        SyncError::record($device->id, 'sales', 'abc', '{}', 'unknown customer'); // it came back after being resolved
        $this->assertSame(5, SyncError::count());
    }

    public function testRowsWithoutAKeyAreCountedToo()
    {
        $device = Device::forceCreate(['name' => 'POS1']);

        SyncError::record($device->id, 'sales', null, '{}', 'id is required');
        SyncError::record($device->id, 'sales', null, '{}', 'id is required');

        $this->assertSame(1, SyncError::count());
        $this->assertSame(2, (int) SyncError::first()->occurrences);
    }
}
