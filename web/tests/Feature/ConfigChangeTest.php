<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Tank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

// Config for Admin: a fuel type that is in use is not deleted and its key does not change; a change of a fuel type, a tank or a user is written to the audit log
// with what it was and what it became (a password only as "changed").
class ConfigChangeTest extends TestCase
{
    use RefreshDatabase;

    private $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'config_audit_' . uniqid();
        mkdir($this->dir);
        config(['logging.channels.audit.path' => $this->dir . DIRECTORY_SEPARATOR . 'audit.log']);
    }

    protected function tearDown(): void
    {
        foreach (Log::channel('audit')->getLogger()->getHandlers() as $handler) {
            $handler->close();
        }
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') as $f) {
            unlink($f);
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function log()
    {
        foreach (Log::channel('audit')->getLogger()->getHandlers() as $handler) {
            $handler->close();
        }

        return implode("\n", array_map('file_get_contents', glob($this->dir . DIRECTORY_SEPARATOR . 'audit-*.log')));
    }

    public function testAFuelTypeThatATankUsesIsNotDeleted()
    {
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7.5]);
        Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);

        $this->as('admin')->delete('/fuel/D')->assertRedirect('/fuel')->assertSessionHas('error');
        $this->assertNotNull(FuelType::find('D'));

        Tank::where('name', 'T1')->delete();
        $this->delete('/fuel/D')->assertRedirect('/fuel')->assertSessionMissing('error');
        $this->assertNull(FuelType::find('D'));
    }

    // a capacity below the stock of the tank is saved, with a warning
    public function testACapacityBelowTheStockIsSavedWithAWarning()
    {
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => 5000]);
        \App\Models\Fuel\TankBalance::create(['tank_id' => $tank->id, 'balance' => 3000]);

        $this->as('admin')->put('/tanks/' . $tank->id, ['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => '2000'])->assertRedirect('/tanks')->assertSessionHas('warning');
        $this->assertEquals(2000, $tank->fresh()->capacity_kg);
        $this->get('/tanks')->assertOk()->assertSee('is below the fuel it holds now');

        $this->put('/tanks/' . $tank->id, ['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => '4000'])->assertRedirect('/tanks')->assertSessionMissing('warning');
        $this->put('/tanks/' . $tank->id, ['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => ''])->assertRedirect('/tanks')->assertSessionMissing('warning');

        $this->post('/tanks', ['name' => 'T2', 'fuel_type' => 'D', 'capacity_kg' => '10'])->assertRedirect('/tanks')->assertSessionMissing('warning');   // a new tank holds nothing
    }

    public function testTheFuelTypeOfARowDoesNotChange()
    {
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7.5]);

        $html = $this->as('admin')->get('/fuel/D/edit')->assertOk()->getContent();
        $this->assertMatchesFormLocked($html);

        $this->put('/fuel/D', ['fuel_type' => 'A', 'price' => '8'])->assertSessionHasErrors('fuel_type');
        $this->assertEquals(7.5, FuelType::find('D')->price);
        $this->assertNull(FuelType::find('A'));

        $this->put('/fuel/D', ['fuel_type' => 'D', 'price' => '8'])->assertRedirect('/fuel');
        $this->assertEquals(8, FuelType::find('D')->price);
    }

    // the key is sent in a hidden field and the select is disabled
    private function assertMatchesFormLocked($html)
    {
        $this->assertRegExp('/<input type="hidden" name="fuel_type" value="D">/', $html);
        $this->assertRegExp('/<select id="f_fuel_type"\s+disabled/', $html);
    }

    public function testAPriceChangeIsWrittenWithTheOldAndTheNewPrice()
    {
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7.5]);

        $this->as('admin')->put('/fuel/D', ['fuel_type' => 'D', 'price' => '8.25', 'density' => '0.84'])->assertRedirect('/fuel');

        $this->assertRegExp('/ - admin - FUEL_UPDATE - #D .*price 7\.50 -> 8\.25/', $this->log());
    }

    public function testATankAndAUserChangeIsWrittenAndAPasswordOnlyAsChanged()
    {
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => 1000]);
        $this->as('admin')->put('/tanks/' . $tank->id, ['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => '1500'])->assertRedirect('/tanks');
        $this->assertRegExp('/ - TANKS_UPDATE - #' . $tank->id . ' T1: capacity_kg 1000\.00 -> 1500/', $this->log());

        $cashier = User::forceCreate(['name' => 'Z', 'username' => 'zed', 'password' => bcrypt('secret1'), 'role' => 'cashier', 'active' => true]);
        $this->put('/users/' . $cashier->id, ['name' => 'Z', 'username' => 'zed', 'role' => 'assistant', 'password' => 'newsecret1'])->assertRedirect('/users');

        $log = $this->log();
        $this->assertRegExp('/ - USERS_UPDATE - #' . $cashier->id . ' Z: role cashier -> assistant, password or PIN changed/', $log);
        $this->assertStringNotContainsString('newsecret1', $log);
        $this->assertStringNotContainsString('$2y$', $log);

        // a new user: its fields, no password
        $this->post('/users', ['name' => 'Amy', 'username' => 'amy', 'role' => 'cashier', 'password' => 'secret1'])->assertRedirect('/users');
        $log = $this->log();
        $this->assertRegExp('/ - USERS_CREATE - #\d+ Amy: .*username amy.*role cashier.*password or PIN changed/', $log);
        $this->assertStringNotContainsString('secret1', $log);
    }
}
