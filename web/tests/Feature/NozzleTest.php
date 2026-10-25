<?php

namespace Tests\Feature;

use App\Models\Admin\Device;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The nozzles are managed on the Smart210 (it pushes them, see PushApiTest): the web app only lists them, and a tank that a nozzle draws from cannot
// be deleted.
class NozzleTest extends TestCase
{
    use RefreshDatabase;

    private $device;

    private $tank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->device = Device::forceCreate(['name' => 'Pump 1', 'ip_address' => '10.0.0.5']);
        $this->tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    public function testTheListShowsTheDeviceTheNumberAndTheTankWithItsFuel()
    {
        Nozzle::forceCreate(['device_id' => $this->device->id, 'number' => 7, 'tank_id' => $this->tank->id, 'fuel_type' => 'D', 'board_address' => 4]);

        $html = $this->as('manager')->get('/nozzles')->assertOk()->getContent();

        $this->assertStringContainsString('Pump 1', $html);
        $this->assertStringContainsString('T1 (Diesel)', $html);
        $this->assertRegExp('/<th>Nozzle number<\/th>\s*<th>Fuel type<\/th>\s*<th>Tank<\/th>/', $html);       // the fuel type is a column
        $this->assertRegExp('/<td>7<\/td>\s*<td>Diesel<\/td>\s*<td>T1 \(Diesel\)<\/td>/', $html);
        $this->assertStringContainsString('Nozzles', $html);                      // the page title / menu item
    }

    public function testTheListHasNoWayToAddChangeOrRemoveANozzle()
    {
        $nozzle = Nozzle::forceCreate(['device_id' => $this->device->id, 'number' => 1, 'tank_id' => $this->tank->id, 'fuel_type' => 'D']);

        $html = $this->as('admin')->get('/nozzles')->assertOk()->getContent();
        $this->assertStringContainsString('managed on the Smart210', $html);
        $this->assertStringNotContainsString('/nozzles/create', $html);
        $this->assertStringNotContainsString('/nozzles/' . $nozzle->id . '/edit', $html);
        $this->assertStringNotContainsString('btn-outline-danger', $html);        // no Delete
        $this->assertStringNotContainsString('trashed=1', $html);                 // no trash

        // and the routes are gone, for the admin too
        $data = ['device_id' => $this->device->id, 'number' => 2, 'fuel_type' => 'D', 'tank_id' => $this->tank->id];
        $this->get('/nozzles/create')->assertStatus(404);
        $this->post('/nozzles', $data)->assertStatus(405);
        $this->get('/nozzles/' . $nozzle->id . '/edit')->assertStatus(404);
        $this->put('/nozzles/' . $nozzle->id, $data)->assertStatus(404);
        $this->delete('/nozzles/' . $nozzle->id)->assertStatus(404);
        $this->post('/nozzles/' . $nozzle->id . '/restore')->assertStatus(404);
        $this->assertSame(1, Nozzle::count());
        $this->assertSame(1, (int) Nozzle::find($nozzle->id)->number);
    }

    public function testNozzlesAreAnItemOfConfigForAdminAfterTanks()
    {
        $html = $this->as('admin')->get('/')->assertOk()->getContent();
        $tanks = strpos($html, 'href="' . url('/tanks') . '"');
        $nozzles = strpos($html, 'href="' . url('/nozzles') . '"');
        $this->assertNotFalse($tanks);
        $this->assertNotFalse($nozzles);
        $this->assertTrue($tanks < $nozzles);
    }

    public function testPermissionsFollowTheTanks()
    {
        $this->as('admin')->get('/nozzles')->assertOk();
        $this->as('accountant')->get('/nozzles')->assertOk();      // fuel.view
        $this->as('cashier')->get('/nozzles')->assertForbidden();
    }

    public function testATankThatANozzleDrawsFromCannotBeDeleted()
    {
        $free = Tank::forceCreate(['name' => 'T2', 'fuel_type' => 'A']);
        Nozzle::forceCreate(['device_id' => $this->device->id, 'number' => 3, 'tank_id' => $this->tank->id, 'fuel_type' => 'D']);
        Nozzle::forceCreate(['device_id' => $this->device->id, 'number' => 1, 'tank_id' => $this->tank->id, 'fuel_type' => 'D']);

        // refused, with the nozzles named
        $this->as('admin')->delete('/tanks/' . $this->tank->id)->assertRedirect('/tanks');
        $this->assertNotNull(Tank::find($this->tank->id));
        $this->get('/tanks')->assertSee('cannot be deleted')->assertSee('nozzle(s) 1, 3');

        // a tank with no nozzle is deleted as before
        $this->delete('/tanks/' . $free->id)->assertRedirect('/tanks');
        $this->assertNull(Tank::find($free->id));

        // a nozzle that was removed on the Smart210 no longer holds its tank
        Nozzle::query()->delete();
        $this->delete('/tanks/' . $this->tank->id)->assertRedirect('/tanks');
        $this->assertNull(Tank::find($this->tank->id));
    }
}
