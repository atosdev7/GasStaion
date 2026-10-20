<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use App\Models\Tickets\TicketPublish;
use App\Models\Tickets\TicketInbound;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
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

    public function testDeleteHidesFromTheListButRestoreBringsItBack()
    {
        $this->as('admin');
        $f = FuelType::forceCreate(['fuel_type' => 'A', 'price' => 8]);

        $this->delete('/fuel/A')->assertRedirect('/fuel');
        $this->assertNull(FuelType::find('A'));           // hidden from normal queries
        $this->assertNotNull(FuelType::withTrashed()->find('A')); // still on disk
        $this->get('/fuel')->assertOk()->assertDontSee('Gasoline'); // config('fuel_types.A') display name
        $this->get('/fuel?trashed=1')->assertOk()->assertSee('Gasoline');

        $this->post('/fuel/A/restore')->assertRedirect('/fuel?trashed=1');
        $this->assertNotNull(FuelType::find('A'));
        $this->get('/fuel')->assertOk()->assertSee('Gasoline');
    }

    public function testDeletingLeavesRowsThatReferenceItIntact()
    {
        $this->as('admin');
        // Tanks don't reference the fuel_types settings row at all any more (just the config key
        // directly), so deleting the settings row can't touch anything that "references" it.
        $f = FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7]);
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => $f->fuel_type]);

        $this->delete('/fuel/D');

        $this->assertSame('D', $tank->fresh()->fuel_type); // the reference itself is untouched
    }

    // Only User still has `active`; it is untouched by delete (a separate concept).
    public function testActiveFlagIsSeparateFromDeleteForUsers()
    {
        $this->as('admin');
        $u = User::forceCreate(['name' => 'Z', 'username' => 'zed', 'password' => bcrypt('x'), 'role' => 'cashier', 'active' => false]);

        $this->delete('/users/' . $u->id);

        $this->assertFalse((bool) User::withTrashed()->find($u->id)->active); // untouched by delete
    }

    public function testNobodyDeletesTheirOwnAccountOrTheSelfCustomer()
    {
        $admin = $this->as('admin')->app->make('auth')->user();
        $this->delete('/users/' . $admin->id)->assertForbidden();
        $this->assertNotNull(User::find($admin->id));

        $self = Customer::where('is_self', true)->first();
        $this->delete('/customers/' . $self->id)->assertForbidden();
        $this->assertNotNull(Customer::find($self->id));
    }

    public function testDeleteAndRestoreNeedManagePermission()
    {
        $f = FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7]);
        $f->delete();

        $this->as('accountant'); // fuel.view only
        $this->delete('/fuel/D')->assertForbidden();
        $this->post('/fuel/D/restore')->assertForbidden();
        $this->get('/fuel?trashed=1')->assertOk(); // viewing (including trash) is fine
    }

    public function testEveryMasterDataScreenSupportsDeleteAndRestore()
    {
        $this->as('admin');
        $f = FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7]);
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => $f->fuel_type]);
        $customer = Customer::forceCreate(['name' => 'ACME']);
        $device = Device::forceCreate(['name' => 'POS1', 'purge_days' => 3]);

        foreach (['tanks' => $tank, 'customers' => $customer, 'devices' => $device] as $path => $model) {
            $class = get_class($model);
            $this->delete("/$path/{$model->id}")->assertRedirect("/$path");
            $this->assertNull($class::find($model->id), $path);
            $this->post("/$path/{$model->id}/restore")->assertRedirect("/$path?trashed=1");
            $this->assertNotNull($class::find($model->id), $path);
        }
    }

    public function testStockModelsAreSoftDeletable()
    {
        $f = FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7]);
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => $f->fuel_type]);

        $inbound = FuelInbound::forceCreate([
            'tank_id' => $tank->id, 'fuel_type' => $f->fuel_type, 'amount_kg' => 100, 'cost' => 6,
            'is_default' => true, 'inbound_date' => '2026-09-20', 'user_id' => 1,
        ]);
        $ticketInbound = TicketInbound::forceCreate([
            'fuel_type' => $f->fuel_type, 'spec' => 0, 'quantity' => 100, 'inbound_date' => '2026-09-20', 'user_id' => 1,
        ]);
        $ticketPublish = TicketPublish::forceCreate([
            'fuel_type' => $f->fuel_type, 'spec' => 0, 'code' => '06', 'start_serial' => 1, 'end_serial' => 100, 'count' => 100, 'user_id' => 1,
        ]);

        foreach ([$inbound, $ticketInbound, $ticketPublish] as $model) {
            $class = get_class($model);
            $model->delete();
            $this->assertNull($class::find($model->id));
            $this->assertNotNull($class::withTrashed()->find($model->id));
            $model->restore();
            $this->assertNotNull($class::find($model->id));
        }
    }
}
