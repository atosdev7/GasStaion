<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Models\FuelType;
use App\Models\Nozzle;
use App\Models\Sale;
use App\Models\Supplement;
use App\Models\Tank;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketRestrictTest extends TestCase
{
    use RefreshDatabase;

    private const BARCODE = '0000006000000001';

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function ticket($status, $saleId = null)
    {
        return Ticket::forceCreate([
            'barcode' => self::BARCODE, 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 1,
            'status' => config('ticket_statuses.' . $status), 'sale_id' => $saleId,
        ]);
    }

    private function sale()
    {
        return Sale::forceCreate([
            'sale_date' => today()->toDateString(), 'customer_id' => Customer::forceCreate(['name' => 'ACME'])->id, 'sale_type' => 'ticket',
            'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 5, 'payment_method' => 'cash', 'total_amount' => 10,
            'paid_amount' => 10, 'unpaid_amount' => 0, 'usage' => 'sale', 'user_id' => 1,
        ]);
    }

    private function status()
    {
        return Ticket::find(self::BARCODE)->status;
    }

    public function testRestrictAndLiftAPublishedTicket()
    {
        $this->ticket('publish');

        $this->as('accountant')->post('/tickets/' . self::BARCODE . '/restrict')
            ->assertRedirect('/tickets/lookup')
            ->assertSessionHas('status', __('tickets.ticket_restricted'));
        $this->assertSame(config('ticket_statuses.restrict'), $this->status());

        $this->post('/tickets/' . self::BARCODE . '/lift-restriction')->assertSessionHas('status');
        $this->assertSame(config('ticket_statuses.publish'), $this->status());
    }

    public function testLiftingASoldTicketRestoresSale()
    {
        $this->ticket('sale', $this->sale()->id);

        $this->as('manager')->post('/tickets/' . self::BARCODE . '/restrict');
        $this->assertSame(config('ticket_statuses.restrict'), $this->status());
        $this->assertNotNull(Ticket::find(self::BARCODE)->sale_id); // sale link kept

        $this->post('/tickets/' . self::BARCODE . '/lift-restriction');
        $this->assertSame(config('ticket_statuses.sale'), $this->status());
    }

    public function testASuppliedTicketCannotBeRestricted()
    {
        $this->ticket('supply');

        $this->as('accountant')->post('/tickets/' . self::BARCODE . '/restrict')
            ->assertSessionHasErrors('barcode');
        $this->assertSame(config('ticket_statuses.supply'), $this->status());
    }

    public function testLiftingAnUnrestrictedTicketIsRejected()
    {
        $this->ticket('sale', $this->sale()->id);

        $this->as('accountant')->post('/tickets/' . self::BARCODE . '/lift-restriction')
            ->assertSessionHasErrors('barcode');
        $this->assertSame(config('ticket_statuses.sale'), $this->status());
    }

    public function testARestrictedTicketCannotBeSupplied()
    {
        $this->ticket('restrict', $this->sale()->id);
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 1, 'density' => 0.89]);
        $device = Device::forceCreate(['name' => 'Device 1']);
        $tank = Tank::forceCreate(['name' => 'Tank 1', 'fuel_type' => 'D']);
        $nozzle = Nozzle::forceCreate(['device_id' => $device->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => 'D']);

        $this->as('manager')->post('/supplements', [
            'ticket_barcodes' => self::BARCODE, 'nozzle_id' => $nozzle->id,
            'out_amount_lt' => 5, 'supplied_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('ticket_barcodes');
        $this->assertSame(0, Supplement::count());
    }

    public function testLookupShowsTheRightButton()
    {
        $this->ticket('publish');

        $this->as('accountant')->get('/tickets/lookup?modal=1&barcode=' . self::BARCODE)
            ->assertSee(__('tickets.restrict_ticket'))->assertDontSee(__('tickets.lift_restriction'));

        Ticket::find(self::BARCODE)->forceFill(['status' => config('ticket_statuses.restrict')])->save();
        $this->get('/tickets/lookup?modal=1&barcode=' . self::BARCODE)
            ->assertSee(__('tickets.lift_restriction'))->assertDontSee(__('tickets.restrict_ticket'));

        $this->as('assistant')->get('/tickets/lookup?modal=1&barcode=' . self::BARCODE)
            ->assertOk()->assertDontSee(__('tickets.lift_restriction'));
    }

    public function testPermissions()
    {
        $this->ticket('publish');

        $this->as('assistant')->post('/tickets/' . self::BARCODE . '/restrict')->assertForbidden();
        $this->as('cashier')->post('/tickets/' . self::BARCODE . '/restrict')->assertForbidden();
        $this->assertSame(config('ticket_statuses.publish'), $this->status());
    }
}
