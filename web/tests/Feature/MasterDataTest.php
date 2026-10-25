<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Tank;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    // $fuelType must be one of config('fuel_types')'s keys (currently 'A' or 'D').
    private function fuel($fuelType = 'D')
    {
        return FuelType::forceCreate(['fuel_type' => $fuelType, 'price' => 7.5]);
    }

    // ---- permissions ----

    public function testPermissionsGateEachScreen()
    {
        $this->as('cashier')->get('/users')->assertForbidden();
        $this->as('cashier')->get('/fuel')->assertForbidden();
        $this->as('manager')->get('/users')->assertForbidden();
        $this->as('manager')->get('/fuel')->assertOk();
        $this->as('manager')->get('/devices')->assertOk();
        $this->as('manager')->get('/devices/create')->assertForbidden(); // devices.view only
        $this->as('accountant')->get('/fuel')->assertOk();   // the accountant sets the price of a fuel type, like the manager
        $this->as('accountant')->get('/fuel/create')->assertOk();
        $this->as('assistant')->get('/fuel')->assertForbidden();
        $this->as('assistant')->get('/customers')->assertOk();
        $this->as('assistant')->get('/customers/create')->assertForbidden();
    }

    // ---- users ----

    public function testAdminCreatesUserWithBothPasswordHashes()
    {
        $this->as('admin')->post('/users', [
            'name' => 'Amy', 'username' => 'amy', 'role' => 'cashier', 'password' => 'secret1',
        ])->assertRedirect('/users');

        $u = User::where('username', 'amy')->first();
        $this->assertTrue(\Hash::check('secret1', $u->password));
        $this->assertSame(hash('sha256', $u->pos_salt . 'secret1'), $u->pos_hash);
        $this->assertFalse((bool) $u->synced);
    }

    public function testUserValidation()
    {
        $this->as('admin')->post('/users', ['name' => 'X', 'username' => 'admin', 'role' => 'cashier', 'password' => 'secret1'])
            ->assertSessionHasErrors('username');
        $this->post('/users', ['name' => 'X', 'username' => 'x', 'role' => 'boss', 'password' => 'secret1'])
            ->assertSessionHasErrors('role');
        $this->post('/users', ['name' => 'X', 'username' => 'x', 'role' => 'cashier'])
            ->assertSessionHasErrors('password');
    }

    public function testEditWithoutPasswordKeepsIt()
    {
        $admin = User::where('username', 'admin')->first();
        $this->as('admin')->put('/users/' . $admin->id, ['name' => 'Renamed', 'username' => 'admin', 'role' => 'admin'])
            ->assertRedirect('/users');

        $fresh = $admin->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame($admin->password, $fresh->password);
        $this->assertSame($admin->pos_hash, $fresh->pos_hash);
    }

    public function testNobodyDeactivatesThemself()
    {
        $admin = User::where('username', 'admin')->first();
        $this->as('admin')->post('/users/' . $admin->id . '/toggle')->assertForbidden();
        $this->assertTrue($admin->fresh()->active);
    }

    public function testDeactivatedUserCannotLogIn()
    {
        $this->as('admin');
        $u = User::forceCreate(['name' => 'Z', 'username' => 'zed', 'password' => bcrypt('secret1'), 'role' => 'cashier', 'active' => true]);
        $this->post('/users/' . $u->id . '/toggle');
        $this->assertFalse($u->fresh()->active);

        auth()->logout();
        $this->post('/login', ['username' => 'zed', 'password' => 'secret1']);
        $this->assertGuest();
    }

    // ---- fuel types and tanks ----

    public function testFuelTypeCrud()
    {
        $this->as('admin')->post('/fuel', ['fuel_type' => 'A', 'price' => '8.25', 'density' => '0.75'])->assertRedirect('/fuel');
        $f = FuelType::find('A');
        $this->assertEquals(8.25, $f->price);
        $this->assertEquals(0.75, $f->density);

        $this->put('/fuel/A', ['fuel_type' => 'A', 'price' => '9', 'density' => '0.76'])->assertRedirect('/fuel');
        $this->assertEquals(9, $f->fresh()->price);
        $this->assertEquals(0.76, $f->fresh()->density);

        $this->post('/fuel', ['fuel_type' => 'A', 'price' => '1'])->assertSessionHasErrors('fuel_type'); // already configured
        $this->post('/fuel', ['fuel_type' => 'X', 'price' => '1'])->assertSessionHasErrors('fuel_type'); // not in config/fuel_types.php
        $this->post('/fuel', ['fuel_type' => 'D', 'price' => '-1'])->assertSessionHasErrors('price');
        $this->post('/fuel', ['fuel_type' => 'D', 'price' => '1', 'density' => '0'])->assertSessionHasErrors('density');
    }

    // Only users have an activate/deactivate toggle now; other master data relies on delete alone.
    public function testFuelTypeHasNoActiveToggle()
    {
        $this->as('admin');
        $f = $this->fuel();

        $this->post('/fuel/' . $f->fuel_type . '/toggle')->assertStatus(404);
        $this->delete('/fuel/' . $f->fuel_type)->assertRedirect('/fuel');
        $this->assertNull(FuelType::find($f->fuel_type));
    }

    public function testFuelTypeDensityIsOptional()
    {
        $this->as('admin')->post('/fuel', ['fuel_type' => 'D', 'price' => '7'])->assertRedirect('/fuel');

        $this->assertNull(FuelType::find('D')->density);
    }

    // Tank creation only needs a config-valid fuel type key; it doesn't require a price/density
    // settings row to already exist for it (fuel type identity and its price/density are separate).
    public function testTankFuelTypeMustBeAConfiguredKey()
    {
        $this->as('manager')->post('/tanks', ['name' => 'T1', 'fuel_type' => 'A', 'capacity_kg' => '20000'])->assertRedirect('/tanks');
        $this->post('/tanks', ['name' => 'T2', 'fuel_type' => 'X'])->assertSessionHasErrors('fuel_type');
        $this->assertSame(1, Tank::count());
    }

    // ---- customers ----

    public function testCustomerCrudAndIndividualCannotBeChangedOrDeleted()
    {
        $this->as('accountant')->post('/customers', ['name' => 'ACME', 'phone' => '123'])->assertRedirect('/customers');
        $c = Customer::where('name', 'ACME')->first();
        $this->assertFalse((bool) $c->synced);

        $self = Customer::where('is_default', true)->first();
        $this->post('/customers/' . $self->id . '/toggle')->assertStatus(404); // no active toggle
        $this->delete('/customers/' . $self->id)->assertForbidden();
        $this->assertNotNull(Customer::find($self->id));
    }

    // ---- devices ----

    public function testNewDeviceGetsItsAddressAndTheDefaultPin()
    {
        $this->as('admin')->post('/devices', ['name' => 'POS1', 'ip_address' => '192.168.1.50', 'purge_days' => 3])->assertRedirect('/devices');

        $d = Device::where('name', 'POS1')->first();
        $this->assertSame('192.168.1.50', $d->ip_address); // the address is what lets the Smart210 use the API

        // default PIN is 0000
        $this->assertSame(hash('sha256', $d->pin_salt . '0000'), $d->pin_hash);
    }

    public function testDevicePinAndPurgeDays()
    {
        $this->as('admin')->post('/devices', ['name' => 'POS2', 'ip_address' => '192.168.1.51', 'purge_days' => 5, 'pin' => '482913']);
        $d = Device::where('name', 'POS2')->first();
        $this->assertSame(5, $d->purge_days);
        $this->assertSame(hash('sha256', $d->pin_salt . '482913'), $d->pin_hash);

        // blank PIN on edit keeps it
        $this->put('/devices/' . $d->id, ['name' => 'POS2', 'ip_address' => '192.168.1.51', 'purge_days' => 7]);
        $this->assertSame($d->pin_hash, $d->fresh()->pin_hash);
        $this->assertSame(7, $d->fresh()->purge_days);

        $this->post('/devices', ['name' => 'POS3', 'purge_days' => 0])->assertSessionHasErrors('purge_days');
        $this->post('/devices', ['name' => 'POS3', 'purge_days' => 3, 'pin' => '12'])->assertSessionHasErrors('pin');
    }

    public function testADeviceNeedsAValidAddressNotUsedByAnotherLiveDevice()
    {
        $this->as('admin')->post('/devices', ['name' => 'A', 'purge_days' => 3])->assertSessionHasErrors('ip_address');
        $this->post('/devices', ['name' => 'A', 'ip_address' => 'not an address', 'purge_days' => 3])->assertSessionHasErrors('ip_address');
        $this->post('/devices', ['name' => 'A', 'ip_address' => '192.168.1.60', 'purge_days' => 3])->assertRedirect('/devices');

        $this->post('/devices', ['name' => 'B', 'ip_address' => '192.168.1.60', 'purge_days' => 3])->assertSessionHasErrors('ip_address');

        $a = Device::where('name', 'A')->first();
        $this->put('/devices/' . $a->id, ['name' => 'A', 'ip_address' => '192.168.1.60', 'purge_days' => 4])->assertRedirect('/devices'); // its own address is fine
        $this->assertSame(4, $a->fresh()->purge_days);

        // A deleted device's address can be given to another one.
        $this->delete('/devices/' . $a->id);
        $this->post('/devices', ['name' => 'B', 'ip_address' => '192.168.1.60', 'purge_days' => 3])->assertRedirect('/devices');
        $this->assertSame(1, Device::where('ip_address', '192.168.1.60')->count());
    }
}
