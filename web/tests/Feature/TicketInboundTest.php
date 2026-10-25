<?php

namespace Tests\Feature;

use App\Support\Tickets\TicketStock;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketInboundTest extends TestCase
{
    use RefreshDatabase;

    private $diesel; // config('fuel_types') key, no DB row needed to reference it

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->diesel = 'D';
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    // spec 0 = 5kg (see config/ticket_specs.php)
    private function receive($quantity, $spec = 0, $fuel = null)
    {
        return $this->post('/ticket-inbound', [
            'fuel_type' => $fuel ?: $this->diesel, 'spec' => $spec, 'quantity' => $quantity, 'inbound_date' => '2026-09-20',
        ]);
    }

    public function testSpecsAreTheFourFixedCylinderWeights()
    {
        $this->assertSame([0 => 5, 1 => 10, 2 => 15, 3 => 50], config('ticket_specs'));
    }

    public function testInboundAddsToBlankStock()
    {
        $this->as('manager');
        $this->receive(300)->assertRedirect('/ticket-inbound');
        $this->receive(200);

        $this->assertSame(500, TicketStock::available($this->diesel, 0));
        $this->assertSame(0, TicketStock::available($this->diesel, 1)); // other spec (10kg)
    }

    public function testInboundValidation()
    {
        $this->as('manager');
        $this->receive(0)->assertSessionHasErrors('quantity');
        $this->receive(10, 4)->assertSessionHasErrors('spec');   // only 0-3 exist
        $this->receive(10, 'abc')->assertSessionHasErrors('spec');
        $this->post('/ticket-inbound', ['fuel_type' => 'X', 'spec' => 0, 'quantity' => 5, 'inbound_date' => '2026-09-20'])
            ->assertSessionHasErrors('fuel_type');
        $this->assertSame(0, DB::table('ticket_inbounds')->count());
    }

    public function testPermissions()
    {
        $this->as('assistant')->get('/ticket-inbound')->assertOk();
        $this->receive(10)->assertRedirect('/ticket-inbound'); // assistant can manage stock

        $this->as('accountant')->get('/ticket-inbound')->assertOk(); // the accountant manages the stock like the assistant
        $this->get('/ticket-inbound/create')->assertOk();
        $this->receive(1)->assertRedirect('/ticket-inbound');

        $this->as('cashier')->get('/ticket-inbound')->assertForbidden();
        $this->get('/ticket-inbound/create')->assertForbidden();
        $this->receive(1)->assertForbidden();
        $this->assertSame(2, DB::table('ticket_inbounds')->count());
    }

    public function testScreensRender()
    {
        $this->as('manager');
        $this->receive(10);

        $this->get('/ticket-inbound')->assertOk()->assertSee('Diesel')->assertSee('5')->assertSee(__('report.kg'));
        $this->get('/ticket-inbound/create')->assertOk()->assertSee('50')->assertSee(__('report.kg')); // spec dropdown lists 50kg too
    }

    private function firstInbound()
    {
        return \App\Models\Tickets\TicketInbound::orderBy('id')->first();
    }

    private function publishTickets($from, $to)
    {
        return $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => $from, 'end_serial' => $to]);
    }

    public function testEditChangesQuantityDateAndNoteAndTheStockFollows()
    {
        $this->as('manager');
        $this->receive(300);
        $row = $this->firstInbound();

        $this->get('/ticket-inbound/' . $row->id . '/edit')->assertOk()->assertSee('300')->assertSee('Diesel');
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => 250, 'inbound_date' => '2026-09-22', 'note' => 'counted again'])
            ->assertRedirect('/ticket-inbound');

        $row = $row->fresh();
        $this->assertSame(250, (int) $row->quantity);
        $this->assertSame('counted again', $row->note);
        $this->assertSame('2026-09-22', substr((string) $row->inbound_date, 0, 10));
        $this->assertSame(250, TicketStock::available($this->diesel, 0));
        $this->assertSame('D', $row->fuel_type); // the fuel type and the spec do not change
        $this->assertSame(0, (int) $row->spec);
    }

    public function testEditIsValidatedAndNeverLeavesFewerReceivedThanPublished()
    {
        $this->as('manager');
        $this->receive(10);
        $this->publishTickets(1, 6);
        $row = $this->firstInbound();

        $this->put('/ticket-inbound/' . $row->id, ['quantity' => 0, 'inbound_date' => '2026-09-20'])->assertSessionHasErrors('quantity');
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => 5, 'inbound_date' => '2026-09-20'])->assertSessionHasErrors('quantity'); // 6 published
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => 6, 'inbound_date' => '2026-09-20'])->assertRedirect('/ticket-inbound'); // exactly what was published
        $this->assertSame(6, (int) $row->fresh()->quantity);
        $this->assertSame(0, TicketStock::available($this->diesel, 0));
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => 6, 'inbound_date' => 'nope'])->assertSessionHasErrors('inbound_date');
        $this->put('/ticket-inbound/999999', ['quantity' => 6, 'inbound_date' => '2026-09-20'])->assertNotFound();
    }

    public function testDeleteRemovesARecordUnlessItsTicketsWerePublished()
    {
        $this->as('manager');
        $this->receive(100);
        $this->receive(50);
        $first = $this->firstInbound();
        $this->publishTickets(1, 120);
        $this->assertSame(30, TicketStock::available($this->diesel, 0));

        // deleting the 100 would leave 50 received for 120 published
        $this->delete('/ticket-inbound/' . $first->id)->assertRedirect('/ticket-inbound')->assertSessionHasErrors('inbound');
        $this->assertNotNull($first->fresh());

        // deleting the 50 would leave 100 received for 120 published: refused too
        $second = \App\Models\Tickets\TicketInbound::orderByDesc('id')->first();
        $this->delete('/ticket-inbound/' . $second->id)->assertSessionHasErrors('inbound');
        $this->assertSame(2, \App\Models\Tickets\TicketInbound::count());

        // a record nothing was published from can go
        $this->receive(20, 1); // 10 kg tickets: nothing published
        $ten = \App\Models\Tickets\TicketInbound::where('spec', 1)->first();
        $this->delete('/ticket-inbound/' . $ten->id)->assertRedirect('/ticket-inbound')->assertSessionHasNoErrors();
        $this->assertNull(\App\Models\Tickets\TicketInbound::find($ten->id));
        $this->assertNotNull(\App\Models\Tickets\TicketInbound::onlyTrashed()->find($ten->id)); // kept as deleted
        $this->assertSame(0, TicketStock::available($this->diesel, 1));
    }

    public function testTheActionsColumnShowsToThoseWhoManageTheStock()
    {
        $this->as('assistant');
        $this->receive(10);
        $row = $this->firstInbound();

        $this->get('/ticket-inbound')->assertOk()->assertSee('Actions')->assertSee(route('ticket-inbound.edit', $row->id));

        // the accountant adds, changes and deletes too
        $this->as('accountant')->get('/ticket-inbound')->assertOk()->assertSee('Actions')->assertSee(route('ticket-inbound.edit', $row->id));
        $this->get('/ticket-inbound/' . $row->id . '/edit')->assertOk();
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => 12, 'inbound_date' => '2026-09-20'])->assertRedirect('/ticket-inbound');
        $this->assertSame(12, (int) $row->fresh()->quantity);
        $this->delete('/ticket-inbound/' . $row->id)->assertRedirect('/ticket-inbound');
        $this->assertNull(\App\Models\Tickets\TicketInbound::find($row->id));

        // the cashier does nothing here
        $row = \App\Models\Tickets\TicketInbound::withTrashed()->find($row->id);
        $row->restore();
        $this->as('cashier')->get('/ticket-inbound/' . $row->id . '/edit')->assertForbidden();
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => 1, 'inbound_date' => '2026-09-20'])->assertForbidden();
        $this->delete('/ticket-inbound/' . $row->id)->assertForbidden();
        $this->assertSame(12, (int) $row->fresh()->quantity);
    }


    public function testTheInboundTableFiltersByDateFuelTypeAndSpec()
    {
        $this->as('manager');
        $this->post('/ticket-inbound', ['fuel_type' => $this->diesel, 'spec' => 0, 'quantity' => 100, 'inbound_date' => '2026-10-01']);
        $this->post('/ticket-inbound', ['fuel_type' => $this->diesel, 'spec' => 2, 'quantity' => 200, 'inbound_date' => '2026-10-02']);
        $this->post('/ticket-inbound', ['fuel_type' => 'A', 'spec' => 0, 'quantity' => 300, 'inbound_date' => '2026-10-02']);
        $quantities = function ($query) {
            return $this->get('/ticket-inbound' . $query)->assertOk()->viewData('rows')->pluck('quantity')->map(function ($q) {
                return (int) $q;
            })->all();
        };

        $this->assertSame([300, 200, 100], $quantities(''));                         // everything, the newest day first
        $this->assertSame([100], $quantities('?inbound_date=2026-10-01'));           // the day
        $this->assertSame([300, 200], $quantities('?inbound_date=2026-10-02'));
        $this->assertSame([200, 100], $quantities('?fuel_type=' . $this->diesel));   // the fuel type
        $this->assertSame([300], $quantities('?fuel_type=A'));
        $this->assertSame([300, 100], $quantities('?spec=0'));                       // the spec (spec 0 is 5 kg: it is a value, not "no filter")
        $this->assertSame([200], $quantities('?spec=2'));
        $this->assertSame([], $quantities('?spec=3'));
        $this->assertSame([100], $quantities('?fuel_type=' . $this->diesel . '&spec=0'));   // together
        $this->assertSame([200], $quantities('?inbound_date=2026-10-02&fuel_type=' . $this->diesel . '&spec=2'));

        $this->get('/ticket-inbound?inbound_date=nope')->assertSessionHasErrors('inbound_date');
        $this->get('/ticket-inbound?fuel_type=Z')->assertSessionHasErrors('fuel_type');
        $this->get('/ticket-inbound?spec=9')->assertSessionHasErrors('spec');

        // the filters are in the head of the table and keep what was chosen
        $html = $this->get('/ticket-inbound?inbound_date=2026-10-02&fuel_type=' . $this->diesel . '&spec=2')->assertOk()->getContent();
        $this->assertStringContainsString('id="ticket-inbound-filters"', $html);
        $this->assertStringContainsString('name="inbound_date" form="ticket-inbound-filters" type="date" value="2026-10-02"', $html);
        $this->assertRegExp('/<option value="' . $this->diesel . '" selected>/', $html);
        $this->assertRegExp('/<option value="2" selected>15 kg<\/option>/', $html);
        $this->assertStringContainsString('All fuel types', $html);
        $this->assertStringContainsString('All specs', $html);
        $this->get('/ticket-inbound?spec=3')->assertOk()->assertSee('No data');
    }

    public function testTheInboundTableIsPagedByFiftyAndKeepsTheFilters()
    {
        $this->as('manager');
        for ($i = 1; $i <= 52; $i++) {
            \App\Models\Tickets\TicketInbound::forceCreate([
                'fuel_type' => $this->diesel, 'spec' => $i % 2, 'quantity' => $i, 'inbound_date' => '2026-09-20', 'user_id' => 1,
            ]);
        }
        \App\Models\Tickets\TicketInbound::forceCreate(['fuel_type' => 'A', 'spec' => 0, 'quantity' => 1, 'inbound_date' => '2026-09-20', 'user_id' => 1]);

        $first = $this->get('/ticket-inbound?fuel_type=' . $this->diesel)->assertOk();
        $this->assertCount(50, $first->viewData('rows'));
        $this->assertSame(52, $first->viewData('rows')->total());                                      // the Gasoline one is not counted
        $this->assertStringContainsString('fuel_type=' . $this->diesel . '&amp;page=2', $first->getContent());
        $this->assertCount(2, $this->get('/ticket-inbound?fuel_type=' . $this->diesel . '&page=2')->viewData('rows'));
        $this->assertSame(53, $this->get('/ticket-inbound')->viewData('rows')->total());
    }
    // ---- a negative quantity takes blank tickets out of the stock ----

    public function testANegativeQuantityTakesBlankTicketsOutOfTheStockButNeverBelowWhatWasPublished()
    {
        $this->as('manager');
        $this->receive(100)->assertRedirect('/ticket-inbound');
        $this->receive(-8)->assertRedirect('/ticket-inbound')->assertSessionHasNoErrors();   // spoiled in printing
        $this->assertSame(92, TicketStock::available($this->diesel, 0));
        $this->assertSame(-8, (int) \App\Models\Tickets\TicketInbound::orderByDesc('id')->first()->quantity);

        // zero is nothing to record; the count stays whole and inside the limit
        $this->receive(0)->assertSessionHasErrors('quantity');
        $this->receive(1.5)->assertSessionHasErrors('quantity');
        $this->receive(-10000001)->assertSessionHasErrors('quantity');

        // publish 90 of the 92: only 2 are left to take out
        $this->post('/ticket-publish', ['stock_item' => $this->diesel . ':0', 'start_serial' => 1, 'end_serial' => 90])->assertSessionHasNoErrors();
        $this->receive(-3)->assertSessionHasErrors('quantity');
        $this->assertStringContainsString('fewer blank tickets', session('errors')->first('quantity'));
        $this->receive(-2)->assertSessionHasNoErrors();
        $this->assertSame(0, TicketStock::available($this->diesel, 0));

        // a record can be changed to a negative quantity (and back) within the same limit; deleting a negative one puts the tickets back
        $row = \App\Models\Tickets\TicketInbound::where('quantity', -2)->first();
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => -1, 'inbound_date' => '2026-09-20'])->assertSessionHasNoErrors();
        $this->assertSame(1, TicketStock::available($this->diesel, 0));
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => -5, 'inbound_date' => '2026-09-20'])->assertSessionHasErrors('quantity');
        $this->delete('/ticket-inbound/' . $row->id)->assertSessionHasNoErrors();
        $this->assertSame(2, TicketStock::available($this->diesel, 0));
    }

    // ---- confirmed with the daily confirmation ----

    public function testAConfirmedInboundCannotBeChangedOrDeletedUntilAManagerTakesTheConfirmationBack()
    {
        $this->as('accountant');
        $this->receive(10);
        $row = $this->firstInbound();
        $this->get('/ticket-inbound')->assertSee('Not confirmed');

        $this->post('/confirmations', ['date' => '2026-09-20', 'from' => 'tickets', 'ticket_inbounds' => [$row->id]])->assertRedirect()->assertSessionHas('status');
        $this->assertTrue($row->fresh()->isConfirmed());

        $page = $this->get('/ticket-inbound')->assertOk();
        $page->assertSee('Confirmed')->assertDontSee(route('ticket-inbound.edit', $row->id));
        $this->get('/ticket-inbound/' . $row->id . '/edit')->assertRedirect('/ticket-inbound')->assertSessionHasErrors('inbound');
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => 99, 'inbound_date' => '2026-09-20'])->assertSessionHasErrors('inbound');
        $this->delete('/ticket-inbound/' . $row->id)->assertSessionHasErrors('inbound');
        $this->assertSame(10, (int) $row->fresh()->quantity);
        $this->assertNull($row->fresh()->deleted_at);

        // the accountant cannot take it back, a manager can
        $this->post('/ticket-inbound/' . $row->id . '/unconfirm')->assertForbidden();
        $this->as('manager')->post('/ticket-inbound/' . $row->id . '/unconfirm')->assertRedirect();
        $this->assertFalse($row->fresh()->isConfirmed());
        $this->put('/ticket-inbound/' . $row->id, ['quantity' => 11, 'inbound_date' => '2026-09-20'])->assertSessionHasNoErrors();
        $this->assertSame(11, (int) $row->fresh()->quantity);
    }

    public function testGasolineHas50KgTickets()
    {
        $this->assertSame('08', config('ticket_codes.A.3'));
        $this->assertSame('08', \App\Support\Tickets\Barcode::codeFor('A', 3));

        $this->as('manager');
        $this->receive(5, 3, 'A')->assertRedirect('/ticket-inbound');
        $this->post('/ticket-publish', ['stock_item' => 'A:3', 'start_serial' => 1, 'end_serial' => 5])->assertSessionHasNoErrors();
        $barcodes = \App\Models\Tickets\Ticket::where('fuel_type', 'A')->where('spec', 3)->orderBy('serial')->pluck('barcode')->all();
        $this->assertCount(5, $barcodes);
        $this->assertSame('08', substr($barcodes[0], 5, 2));
        $this->assertSame(0, TicketStock::available('A', 3));
    }
}
