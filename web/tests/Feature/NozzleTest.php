<?php

namespace Tests\Feature;

use App\Models\Admin\Device;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function testANozzleHasAFuelTypeThatItsTankHolds()
    {
        $this->as('manager')->post('/nozzles', ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => $this->tank->id, 'board_address' => 3])
            ->assertRedirect('/nozzles');

        $nozzle = Nozzle::first();
        $this->assertSame('D', $nozzle->fuel_type);
        $this->assertSame(3, (int) $nozzle->board_address);
        $this->assertSame($this->tank->id, (int) $nozzle->tank_id);

        // the board address is optional
        $this->post('/nozzles', ['device_id' => $this->device->id, 'number' => 2, 'fuel_type' => 'D', 'tank_id' => $this->tank->id])->assertRedirect('/nozzles');
        $this->assertNull(Nozzle::where('number', 2)->first()->board_address);
    }

    public function testTheFuelTypeIsRequiredAndMustBeTheOneOfTheTank()
    {
        $this->as('manager');
        $this->post('/nozzles', ['device_id' => $this->device->id, 'number' => 1, 'tank_id' => $this->tank->id])->assertSessionHasErrors('fuel_type');
        $this->post('/nozzles', ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'X', 'tank_id' => $this->tank->id])->assertSessionHasErrors('fuel_type');

        // a diesel tank for a gasoline nozzle
        $this->post('/nozzles', ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'A', 'tank_id' => $this->tank->id])->assertSessionHasErrors('tank_id');
        $this->assertStringContainsString('different fuel type', session('errors')->first('tank_id'));
        $this->assertSame(0, Nozzle::count());
    }

    public function testTheFuelTypeAndTheTankAreChangedTogether()
    {
        $petrol = Tank::forceCreate(['name' => 'T2', 'fuel_type' => 'A']);
        $nozzle = Nozzle::forceCreate(['device_id' => $this->device->id, 'number' => 1, 'tank_id' => $this->tank->id, 'fuel_type' => 'D']);

        // the tank alone is not enough: the fuel type has to follow
        $this->as('manager')->put('/nozzles/' . $nozzle->id, ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => $petrol->id])
            ->assertSessionHasErrors('tank_id');
        $this->assertSame('D', $nozzle->fresh()->fuel_type);

        $this->put('/nozzles/' . $nozzle->id, ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'A', 'tank_id' => $petrol->id, 'board_address' => ''])
            ->assertRedirect('/nozzles');

        $this->assertSame('A', $nozzle->fresh()->fuel_type);
        $this->assertSame($petrol->id, (int) $nozzle->fresh()->tank_id);
        $this->assertNull($nozzle->fresh()->board_address);
    }

    public function testTheFormKeepsTheFuelTypeAndTheTankTogetherWithAScript()
    {
        $petrol = Tank::forceCreate(['name' => 'T2', 'fuel_type' => 'A']);
        $nozzle = Nozzle::forceCreate(['device_id' => $this->device->id, 'number' => 1, 'tank_id' => $this->tank->id, 'fuel_type' => 'D']);

        $html = $this->as('admin')->get('/nozzles/create')->assertOk()->getContent();
        // the fields, in this order
        $this->assertRegExp('/id="f_device_id".*id="f_number".*id="f_fuel_type".*id="f_tank_id".*id="f_board_address"/s', $html);
        $this->assertStringContainsString('<option value="A"', $html);
        $this->assertStringContainsString('<option value="D"', $html);
        // the script knows the fuel type of each tank
        $this->assertStringContainsString('var fuels = {"' . $this->tank->id . '":"D","' . $petrol->id . '":"A"};', $html);

        // editing: the fuel type of the nozzle is selected
        $edit = $this->get('/nozzles/' . $nozzle->id . '/edit')->assertOk()->getContent();
        $this->assertRegExp('/<option value="D" selected>/', $edit);
        // other forms have no such script
        $this->assertStringNotContainsString('var fuels', $this->get('/tanks/create')->assertOk()->getContent());
    }

    public function testANumberIsUsedOnceOnADevice()
    {
        $other = Device::forceCreate(['name' => 'Pump 2', 'ip_address' => '10.0.0.6']);
        $first = Nozzle::forceCreate(['device_id' => $this->device->id, 'number' => 1, 'tank_id' => $this->tank->id, 'fuel_type' => 'D']);

        $this->as('manager')->post('/nozzles', ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => $this->tank->id])->assertSessionHasErrors('number');
        $this->post('/nozzles', ['device_id' => $other->id, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => $this->tank->id])->assertRedirect('/nozzles');   // another device
        $this->assertSame(2, Nozzle::count());

        // its own number is no clash when it is saved again; a deleted nozzle keeps its number (restore it), and the message says so
        $this->put('/nozzles/' . $first->id, ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => $this->tank->id])->assertRedirect('/nozzles');
        $first->delete();
        $response = $this->post('/nozzles', ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => $this->tank->id])->assertSessionHasErrors('number');
        $this->assertStringContainsString('restore it', session('errors')->first('number'));
        $this->post('/nozzles/' . $first->id . '/restore')->assertRedirect('/nozzles?trashed=1');
        $this->assertNotNull(Nozzle::find($first->id));
    }

    public function testBadValuesAreRefused()
    {
        $this->as('manager');
        $this->post('/nozzles', ['device_id' => 999, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => $this->tank->id])->assertSessionHasErrors('device_id');
        $this->post('/nozzles', ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => 999])->assertSessionHasErrors('tank_id');
        $this->post('/nozzles', ['device_id' => $this->device->id, 'number' => 0, 'fuel_type' => 'D', 'tank_id' => $this->tank->id])->assertSessionHasErrors('number');
        $this->post('/nozzles', ['device_id' => $this->device->id, 'number' => 256, 'fuel_type' => 'D', 'tank_id' => $this->tank->id])->assertSessionHasErrors('number');
        $this->post('/nozzles', ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => $this->tank->id, 'board_address' => 300])->assertSessionHasErrors('board_address');
        $this->assertSame(0, Nozzle::count());
    }

    public function testTheListShowsTheDeviceTheNumberAndTheTankWithItsFuel()
    {
        Nozzle::forceCreate(['device_id' => $this->device->id, 'number' => 7, 'tank_id' => $this->tank->id, 'fuel_type' => 'D', 'board_address' => 4]);

        $html = $this->as('manager')->get('/nozzles')->assertOk()->getContent();

        $this->assertStringContainsString('Pump 1', $html);
        $this->assertStringContainsString('T1 (Diesel)', $html);
        $this->assertStringContainsString('<th>Nozzle number</th>', $html);
        $this->assertRegExp('/<th>Nozzle number<\/th>\s*<th>Fuel type<\/th>\s*<th>Tank<\/th>/', $html);       // the fuel type is a column
        $this->assertRegExp('/<td>7<\/td>\s*<td>Diesel<\/td>\s*<td>T1 \(Diesel\)<\/td>/', $html);
        $this->assertStringContainsString('<td>7</td>', $html);
        $this->assertStringContainsString('Nozzles', $html);                      // the page title / menu item
        $this->as('accountant')->get('/nozzles/create')->assertForbidden();       // fuel.view only
        $this->as('admin')->get('/nozzles/create')->assertOk()->assertSee('T1 (Diesel)');
    }

    public function testNozzlesAreAnItemOfConfigForAdminAfterTanks()
    {
        $html = $this->as('admin')->get('/')->assertOk()->getContent();
        $tanks = strpos($html, 'href="' . url('/tanks') . '"');
        $nozzles = strpos($html, 'href="' . url('/nozzles') . '"');
        $this->assertNotFalse($tanks);
        $this->assertNotFalse($nozzles);
        $this->assertTrue($tanks < $nozzles);

        // a deleted nozzle can be restored
        $nozzle = Nozzle::forceCreate(['device_id' => $this->device->id, 'number' => 1, 'tank_id' => $this->tank->id, 'fuel_type' => 'D']);
        $this->as('admin')->delete('/nozzles/' . $nozzle->id)->assertRedirect('/nozzles');
        $this->assertNull(Nozzle::find($nozzle->id));
        $this->post('/nozzles/' . $nozzle->id . '/restore')->assertRedirect('/nozzles?trashed=1');
        $this->assertNotNull(Nozzle::find($nozzle->id));
    }

    public function testPermissionsFollowTheTanks()
    {
        $this->as('admin')->get('/nozzles')->assertOk();
        $this->as('accountant')->get('/nozzles')->assertOk();      // fuel.view
        $this->as('cashier')->get('/nozzles')->assertForbidden();
        $this->as('assistant')->post('/nozzles', ['device_id' => $this->device->id, 'number' => 1, 'fuel_type' => 'D', 'tank_id' => $this->tank->id])->assertForbidden();
        $this->assertSame(0, Nozzle::count());
    }
}
