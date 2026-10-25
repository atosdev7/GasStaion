<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Admin\Device;
use App\Models\Fuel\Nozzle;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketLookupTest extends TestCase
{
    use RefreshDatabase;

    private $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->customer = Customer::forceCreate(['name' => 'ACME']);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    public function testBlankSearchShowsNoResults()
    {
        $page = $this->as('manager')->get('/tickets/lookup')->assertOk();
        $this->assertNull($page->viewData('openBarcode')); // no popup is opened for a page without a barcode
    }

    public function testLookupShowsAPublishedTicket()
    {
        Ticket::forceCreate([
            'barcode' => '0000006000000001', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 1,
            'status' => config('ticket_statuses.publish'),
        ]);

        $this->as('manager')->get('/tickets/lookup?modal=1&barcode=0000006000000001')->assertOk()
            ->assertSee('Diesel')
            ->assertSee(__('initialize.ticket_status_publish'));
    }

    public function testLookupShowsASoldTicketWithSaleDetails()
    {
        $sale = Sale::forceCreate([
            'sale_date' => today()->toDateString(), 'customer_id' => $this->customer->id, 'sale_type' => 'ticket', 'fuel_type' => 'D',
            'price' => 2, 'amount_kg' => 5, 'payment_method' => 'cash', 'total_amount' => 10,
            'paid_amount' => 10, 'unpaid_amount' => 0, 'usage' => 'sale', 'user_id' => 1,
        ]);
        Ticket::forceCreate([
            'barcode' => '0000006000000001', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 1,
            'status' => config('ticket_statuses.sale'), 'sale_id' => $sale->id, 'sale_date' => now(),
        ]);

        $this->as('manager')->get('/tickets/lookup?modal=1&barcode=0000006000000001')->assertOk()
            ->assertSee(__('initialize.ticket_status_sale'))
            ->assertSee('ACME')
            ->assertSee('10.00');
    }

    public function testLookupShowsADirectSpotTicketByItsOwnAmountKg()
    {
        Ticket::forceCreate([
            'barcode' => '0000021000000001', 'fuel_type' => 'A', 'spec' => null, 'code' => '21', 'serial' => 1,
            'amount_kg' => 23.5, 'status' => config('ticket_statuses.sale'),
        ]);

        $this->as('manager')->get('/tickets/lookup?modal=1&barcode=0000021000000001')->assertOk()
            ->assertSee('23.50');
    }

    public function testLookupShowsALoanSettledTicket()
    {
        $loan = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 5,
            'settled_amount_kg' => 5, 'user_id' => 1,
        ]);
        Ticket::forceCreate([
            'barcode' => '0000006000000001', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 1,
            'status' => config('ticket_statuses.supply'), 'supply_date' => now(),
        ]);
        TicketLoanSettlement::forceCreate([
            'loan_id' => $loan->id, 'ticket_barcode' => '0000006000000001', 'amount_kg' => 5,
            'settled_at' => now(), 'user_id' => 1,
        ]);

        $this->as('manager')->get('/tickets/lookup?modal=1&barcode=0000006000000001')->assertOk()
            ->assertSee(__('initialize.ticket_status_supply'))
            ->assertSee('ACME');
    }

    public function testLookupShowsASuppliedTicketWithDispensingDetails()
    {
        $device = Device::forceCreate(['name' => 'Device 1']);
        $nozzle = Nozzle::forceCreate([
            'device_id' => $device->id, 'number' => 3, 'tank_id' => 1, 'fuel_type' => 'D',
        ]);
        $supplement = Supplement::forceCreate([
            'fuel_type' => 'D', 'nozzle_id' => $nozzle->id, 'device_id' => $device->id,
            'out_amount_lt' => 10, 'out_amount_kg' => 8.9, 'preset_kg' => 8.9,
            'supplied_at' => now(),
        ]);
        Ticket::forceCreate([
            'barcode' => '0000006000000001', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 1,
            'status' => config('ticket_statuses.supply'), 'supply_date' => now(), 'supplement_id' => $supplement->id,
        ]);

        $this->as('manager')->get('/tickets/lookup?modal=1&barcode=0000006000000001')->assertOk()
            ->assertSee(__('initialize.ticket_status_supply'))
            ->assertSee('Device 1')
            ->assertSee('10.00')
            ->assertSee('8.90');
    }

    public function testTicketNotFound()
    {
        $this->as('manager')->get('/tickets/lookup?modal=1&barcode=9999999999999999')->assertOk()
            ->assertSee(__('tickets.ticket_not_found'));
    }

    public function testPermissions()
    {
        $this->as('manager')->get('/tickets/lookup')->assertOk();
        $this->as('accountant')->get('/tickets/lookup')->assertOk();
        $this->as('assistant')->get('/tickets/lookup')->assertOk();
        $this->as('cashier')->get('/tickets/lookup')->assertOk();
    }

    public function testLookupShowsTheBatchDateOfAPublishedTicket()
    {
        $this->as('manager');
        $this->post('/ticket-inbound', ['fuel_type' => 'D', 'spec' => 0, 'quantity' => 10, 'inbound_date' => '2026-09-01']);
        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 1, 'end_serial' => 5, 'date' => '2026-09-18']);
        $batch = \App\Models\Tickets\TicketPublish::first();
        $barcode = \App\Support\Tickets\Barcode::make('D', 0, 3);

        // the batch date, the batch number, its range, and a link to the batch for those who may see the stock
        $this->get('/tickets/lookup?modal=1&barcode=' . $barcode)->assertOk()
            ->assertSee('#' . $batch->id)->assertSee('2026-09-18')->assertSee('(1 - 5)')
            ->assertSee(route('ticket-publish.show', $batch->id));

        // the cashier may look tickets up but not see the stock: no link
        $this->as('cashier')->get('/tickets/lookup?modal=1&barcode=' . $barcode)->assertOk()
            ->assertSee('2026-09-18')->assertDontSee(route('ticket-publish.show', $batch->id));

        // the date of the batch changed later (by the database: a batch cannot be changed on the screens): the lookup follows
        $batch->forceFill(['date' => '2026-09-20'])->save();
        $this->as('manager');
        $this->get('/tickets/lookup?modal=1&barcode=' . $barcode)->assertOk()->assertSee('2026-09-20')->assertDontSee('2026-09-18');

        // a ticket without a batch row (an old one) shows its own publish date
        \App\Models\Tickets\Ticket::forceCreate([
            'barcode' => '0000006000000099', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 99, 'publish_id' => 9999,
            'publish_date' => '2026-08-05 10:00:00', 'status' => config('ticket_statuses.publish'),
        ]);
        $this->get('/tickets/lookup?modal=1&barcode=0000006000000099')->assertOk()->assertSee('#9999')->assertSee('2026-08-05');
    }

    // ---- the table of tickets ----

    private $batchIds = [];

    // two batches (the ids are not 1 and 2: auto-increment numbers go on between tests)
    private function publishSome()
    {
        $this->as('manager');
        $this->post('/ticket-inbound', ['fuel_type' => 'D', 'spec' => 0, 'quantity' => 200, 'inbound_date' => '2026-09-01']);
        $this->post('/ticket-inbound', ['fuel_type' => 'D', 'spec' => 1, 'quantity' => 50, 'inbound_date' => '2026-09-01']);
        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 1, 'end_serial' => 60, 'date' => '2026-09-10']);   // batch 1: 60 tickets
        $this->post('/ticket-publish', ['stock_item' => 'D:1', 'start_serial' => 1, 'end_serial' => 10, 'date' => '2026-09-20']);   // batch 2: 10 tickets
        $this->batchIds = \App\Models\Tickets\TicketPublish::orderBy('id')->pluck('id')->all();
        \DB::table('tickets')->where('barcode', \App\Support\Tickets\Barcode::make('D', 0, 3))->update(['status' => config('ticket_statuses.sale'), 'sale_date' => '2026-09-11 09:00:00']);
        \DB::table('tickets')->where('barcode', \App\Support\Tickets\Barcode::make('D', 0, 4))->update(['status' => config('ticket_statuses.supply'), 'supply_date' => '2026-09-12 10:00:00']);
        \DB::table('tickets')->where('barcode', \App\Support\Tickets\Barcode::make('D', 1, 2))->update(['status' => config('ticket_statuses.restrict')]);
    }

    public function testThePageHasATableOfTicketsWithPagesAndCounts()
    {
        $this->publishSome();

        $page = $this->get('/tickets/lookup')->assertOk();
        $tickets = $page->viewData('tickets');
        $this->assertSame(70, $tickets->total());
        $this->assertCount(50, $tickets);              // one page
        $this->assertSame(2, $tickets->lastPage());
        $page->assertSee(\App\Support\Tickets\Barcode::make('D', 1, 10)); // newest batch first
        $page->assertSee('Published: 67')->assertSee('Sold: 1')->assertSee('Supplied: 1')->assertSee('Restricted: 1'); // counts per state

        $second = $this->get('/tickets/lookup?page=2')->assertOk()->viewData('tickets');
        $this->assertCount(20, $second);
    }

    public function testTheTableCanBeFiltered()
    {
        $this->publishSome();

        $by = function ($query) {
            return $this->get('/tickets/lookup?' . $query)->assertOk()->viewData('tickets');
        };

        $this->assertSame(1, $by('status=' . config('ticket_statuses.sale'))->total());
        $this->assertSame(1, $by('status=' . config('ticket_statuses.restrict'))->total());
        $this->assertSame(67, $by('status=' . config('ticket_statuses.publish'))->total());
        $this->assertSame(10, $by('spec=1')->total());
        $this->assertSame(70, $by('fuel_type=D')->total());
        $this->assertSame(0, $by('fuel_type=A')->total());
        $this->assertSame(60, $by('batch=' . $this->batchIds[0])->total());
        $this->assertSame(10, $by('batch=' . $this->batchIds[1])->total());
        $this->assertSame(10, $by('published=2026-09-20')->total());                  // published on that day
        $this->assertSame(60, $by('published=2026-09-10')->total());
        $this->assertSame(0, $by('published=2026-09-11')->total());
        $this->assertSame(1, $by('sold=2026-09-11')->total());                        // sold on that day
        $this->assertSame(0, $by('sold=2026-09-12')->total());
        $this->assertSame(1, $by('supplied=2026-09-12')->total());                    // supplied on that day
        $this->assertSame(1, $by('published=2026-09-10&supplied=2026-09-12')->total());
        $this->assertSame(0, $by('published=2026-09-20&supplied=2026-09-12')->total());
        $this->assertSame(1, $by('q=' . \App\Support\Tickets\Barcode::make('D', 1, 5))->total());   // part of a barcode (here the whole of it)
        $this->assertSame(1, $by('status=' . config('ticket_statuses.supply') . '&batch=' . $this->batchIds[0])->total()); // together

        // the counts per state follow the other filters, not the state filter
        $page = $this->get('/tickets/lookup?batch=' . $this->batchIds[1] . '&status=' . config('ticket_statuses.publish'))->assertOk();
        $page->assertSee('Published: 9')->assertSee('Restricted: 1');
        $this->assertSame(9, $page->viewData('tickets')->total());

        // the filters stay on the next page and in the links to a ticket
        $page = $this->get('/tickets/lookup?fuel_type=D&batch=' . $this->batchIds[0])->assertOk();
        $this->assertStringContainsString('batch=' . $this->batchIds[0], $page->viewData('tickets')->nextPageUrl());
        $page->assertSee('barcode=' . \App\Support\Tickets\Barcode::make('D', 0, 60), false);
    }

    public function testTheDateColumnsShowTheDayOnly()
    {
        $this->publishSome();

        $page = $this->get('/tickets/lookup?batch=' . $this->batchIds[0] . '&status=' . config('ticket_statuses.sale'))->assertOk();
        $page->assertSee('2026-09-11')->assertDontSee('2026-09-11 09:00:00'); // sold: the day, not the time
    }

    public function testBadFiltersAreRefused()
    {
        $this->publishSome();

        $this->get('/tickets/lookup?status=9')->assertSessionHasErrors('status');
        $this->get('/tickets/lookup?fuel_type=XX')->assertSessionHasErrors('fuel_type');
        $this->get('/tickets/lookup?spec=7')->assertSessionHasErrors('spec');
        $this->get('/tickets/lookup?batch=abc')->assertSessionHasErrors('batch');
        $this->get('/tickets/lookup?published=nope')->assertSessionHasErrors('published');
        $this->get('/tickets/lookup?sold=2026-13-45')->assertSessionHasErrors('sold');
        $this->get('/tickets/lookup?supplied=x')->assertSessionHasErrors('supplied');
    }

    public function testACashierSeesTheTableToo()
    {
        $this->publishSome();

        $page = $this->as('cashier')->get('/tickets/lookup?batch=' . $this->batchIds[1])->assertOk();
        $this->assertSame(10, $page->viewData('tickets')->total());
        $page->assertSee(\App\Support\Tickets\Barcode::make('D', 1, 1))->assertDontSee(route('ticket-publish.show', $this->batchIds[1])); // no link to the batch page
    }

    public function testTheTableHasAnActionsColumnWithDetailsAndRestrictOrLift()
    {
        $this->publishSome();
        $restricted = \App\Support\Tickets\Barcode::make('D', 1, 2);
        $blank = \App\Support\Tickets\Barcode::make('D', 0, 5);

        $page = $this->get('/tickets/lookup?batch=' . $this->batchIds[1])->assertOk();
        $page->assertSee(__('cards.actions'))->assertSee(__('tickets.ticket_details'));
        $page->assertSee(route('tickets.restrict', \App\Support\Tickets\Barcode::make('D', 1, 3)))       // a blank ticket can be restricted
            ->assertSee(route('tickets.lift-restriction', $restricted))                        // a restricted one can be lifted
            ->assertDontSee(route('tickets.lift-restriction', \App\Support\Tickets\Barcode::make('D', 1, 3)));

        // restricting from the popup comes back to the same filtered list, with the message
        $filters = 'batch=' . $this->batchIds[0] . '&fuel_type=D';
        $back = $this->post('/tickets/' . $blank . '/restrict', ['back' => $filters]);
        $back->assertRedirect(route('tickets.lookup', ['batch' => $this->batchIds[0], 'fuel_type' => 'D']));
        $this->assertSame(config('ticket_statuses.restrict'), (int) \DB::table('tickets')->where('barcode', $blank)->value('status'));

        $this->post('/tickets/' . $blank . '/lift-restriction', ['back' => $filters . '&page=2&evil=1'])
            ->assertRedirect(route('tickets.lookup', ['batch' => $this->batchIds[0], 'fuel_type' => 'D', 'page' => 2])); // only the known filters come back
        $this->assertSame(config('ticket_statuses.publish'), (int) \DB::table('tickets')->where('barcode', $blank)->value('status'));

        // without the back field it goes to the list
        $this->post('/tickets/' . $blank . '/restrict')->assertRedirect(route('tickets.lookup'));
    }

    public function testACashierSeesDetailsButNoRestrictOrLift()
    {
        $this->publishSome();
        $page = $this->as('cashier')->get('/tickets/lookup?batch=' . $this->batchIds[1])->assertOk();

        $page->assertSee(__('tickets.ticket_details'))
            ->assertDontSee(route('tickets.restrict', \App\Support\Tickets\Barcode::make('D', 1, 3)))
            ->assertDontSee(route('tickets.lift-restriction', \App\Support\Tickets\Barcode::make('D', 1, 2)));
    }

    public function testThePageHasNoBarcodeSearchFormAndDetailsOpenInAPopup()
    {
        $this->publishSome();
        $barcode = \App\Support\Tickets\Barcode::make('D', 0, 3);

        $page = $this->get('/tickets/lookup?batch=' . $this->batchIds[0])->assertOk();
        $page->assertDontSee('id="f_barcode"', false);                       // the separate barcode search form is gone
        $page->assertSee('id="ticket-modal"', false);                        // the popup
        $page->assertSee('class="btn btn-sm btn-outline-secondary ticket-detail"', false)->assertSee('data-barcode="' . \App\Support\Tickets\Barcode::make('D', 0, 60) . '"', false); // the newest ticket is on the first page

        // the popup asks for the detail alone (no layout), with the forms that come back to the page it was opened from
        $detail = $this->get('/tickets/lookup?modal=1&barcode=' . $barcode . '&back=' . urlencode('batch=1&fuel_type=D'))->assertOk();
        $detail->assertSee($barcode)->assertSee(__('initialize.ticket_status_sale'))->assertSee('name="back" value="batch=1&amp;fuel_type=D"', false);
        $detail->assertDontSee('<html', false)->assertDontSee('id="ticket-modal"', false);

        // a ticket that does not exist
        $this->get('/tickets/lookup?modal=1&barcode=9999999999999999')->assertOk()->assertSee(__('tickets.ticket_not_found'));

        // a barcode in the address (a link from another screen) opens the popup when the page loads
        $this->get('/tickets/lookup?barcode=' . $barcode)->assertOk()->assertSee('var openBarcode = "' . $barcode . '"', false);
    }
}
