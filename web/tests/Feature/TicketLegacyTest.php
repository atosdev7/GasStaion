<?php

namespace Tests\Feature;

use App\Models\Admin\Device;
use App\Models\Admin\Setting;
use App\Models\Admin\SyncError;
use App\Models\Admin\User;
use App\Models\Debts\TicketLoan;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use App\Models\Sales\Customer;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketPublish;
use App\Support\Admin\BackupRun;
use App\Support\Tickets\Barcode;
use App\Support\Tickets\LegacyTickets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\RegistersDevice;
use Tests\TestCase;

/**
 * The press tickets published before the go-live date and sold on paper: no row until one is used (LegacyTickets), and the bulk page that takes the sold ones
 * out of the stock. Diesel 5 kg is code 06. Runs against the gasstation_test database (see phpunit.xml).
 */
class TicketLegacyTest extends TestCase
{
    use RefreshDatabase, RegistersDevice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['sales.backdate_days' => 100000]);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function goLive($date = '2026-10-01')
    {
        Setting::updateOrCreate(['key' => 'go_live_date'], ['value' => $date]);
    }

    // A publish of diesel 5 kg tickets with a row for every ticket of it, as the publish page makes them.
    private function publish($from, $to, $date, $rows = true)
    {
        $publish = new TicketPublish;
        $publish->forceFill([
            'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'start_serial' => $from, 'end_serial' => $to, 'count' => $to - $from + 1, 'date' => $date, 'user_id' => 1,
        ])->save();
        if ($rows) {
            for ($serial = $from; $serial <= $to; ++$serial) {
                Ticket::forceCreate([
                    'barcode' => Barcode::make('D', 0, $serial), 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => $serial, 'publish_id' => $publish->id,
                    'publish_date' => $date . ' 10:00:00', 'status' => config('ticket_statuses.publish'),
                ]);
            }
        }

        return $publish;
    }

    private $nozzleId;

    private function nozzle()
    {
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 1, 'density' => 1]);
        $device = Device::first() ?: $this->registerDevice();
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);

        $nozzle = Nozzle::forceCreate(['device_id' => $device->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => 'D']);
        $this->nozzleId = $nozzle->id;

        return $nozzle;
    }

    private function code($serial)
    {
        return Barcode::make('D', 0, $serial);
    }

    public function testTheGoLiveDateIsSetOnTheInitializePage()
    {
        $page = $this->as('admin')->get('/balances/go-live')->assertOk()->assertSee('Not set')->getContent();
        // the page is an item of the Config for Admin menu, not of Initialize
        $link = strpos($page, 'href="' . url('/balances/go-live') . '"');
        $this->assertNotFalse($link);
        $this->assertGreaterThan(strpos($page, 'Config for Admin'), $link);        $this->post('/balances/go-live', ['go_live_date' => 'soon'])->assertSessionHasErrors('go_live_date');
        $this->post('/balances/go-live', ['go_live_date' => '2026-10-01'])->assertRedirect('/balances/go-live')->assertSessionHas('status');
        $this->assertSame('2026-10-01', LegacyTickets::goLive());
        $this->get('/balances/go-live')->assertOk()->assertSee('value="2026-10-01"', false);

        // the admin only: not the manager, nor the accountant
        foreach (['manager', 'accountant', 'assistant', 'cashier'] as $role) {
            $this->as($role)->get('/balances/go-live')->assertForbidden();
            $this->post('/balances/go-live', ['go_live_date' => '2026-11-01'])->assertForbidden();
        }
        $this->assertSame('2026-10-01', LegacyTickets::goLive());
    }

    // the old paper tickets customers still hold are entered by fuel type and spec; each old ticket supplied afterwards takes one off, against the oldest entry
    public function testTheOldTicketsAtCustomersAreEnteredAndShrinkAsTheyAreSupplied()
    {
        $this->goLive('2026-10-01');
        $this->publish(1, 20, '2026-09-20', false);
        $this->as('manager')->get('/balances/ticket-sales')->assertOk()->assertSee('Old tickets at customers');
        $this->post('/balances/ticket-sales', ['fuel_type' => 'D', 'spec' => 0, 'quantity' => 10, 'as_of' => '2026-10-01', 'comment' => ' first count '])
            ->assertRedirect('/balances/ticket-sales')->assertSessionHasNoErrors();
        $this->post('/balances/ticket-sales', ['fuel_type' => 'D', 'spec' => 0, 'quantity' => 2, 'as_of' => '2026-10-05'])->assertSessionHasNoErrors();
        $this->post('/balances/ticket-sales', ['fuel_type' => 'D', 'spec' => 9, 'quantity' => 1])->assertSessionHasErrors('spec');
        $this->post('/balances/ticket-sales', ['fuel_type' => 'X', 'spec' => 0, 'quantity' => 1])->assertSessionHasErrors('fuel_type');
        $this->post('/balances/ticket-sales', ['fuel_type' => 'D', 'spec' => 0, 'quantity' => 0])->assertSessionHasErrors('quantity');
        $this->assertSame('first count', \App\Models\Tickets\OpeningTicket::orderBy('id')->first()->comment);

        $this->assertEquals(['D' => ['count' => 12, 'kg' => 60.0]], \App\Support\Tickets\OpeningTickets::owed());

        // three old tickets were supplied on the 2nd, 3rd and 6th, one before the first count (it does not count), one a new ticket (not an old one)
        foreach ([[1, '2026-10-02'], [2, '2026-10-03'], [3, '2026-10-06'], [4, '2026-09-30']] as list($serial, $day)) {
            Ticket::forceCreate([
                'barcode' => $this->code($serial), 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => $serial,
                'publish_id' => TicketPublish::first()->id, 'status' => config('ticket_statuses.supply'), 'supply_date' => $day . ' 09:00:00',
            ]);
        }
        $this->publish(30, 31, '2026-10-02');
        Ticket::where('serial', 30)->update(['status' => config('ticket_statuses.supply'), 'supply_date' => '2026-10-04 09:00:00']);

        $entries = \App\Support\Tickets\OpeningTickets::rows();
        $this->assertSame([3, 0], [$entries[0]['supplied'], $entries[1]['supplied']]);      // the three go against the oldest entry
        $this->assertSame([7, 2], [$entries[0]['left'], $entries[1]['left']]);
        $this->assertEquals(['D' => ['count' => 9, 'kg' => 45.0]], \App\Support\Tickets\OpeningTickets::owed());
        $this->get('/balances/ticket-sales')->assertOk()->assertSee('ticket-owed-table', false);

        // a cancelled ticket is taken off by editing the count
        $row = $entries[0]['row'];
        $this->get('/balances/ticket-sales/' . $row->id . '/edit')->assertOk();
        $this->put('/balances/ticket-sales/' . $row->id, ['fuel_type' => 'D', 'spec' => 0, 'quantity' => 8, 'as_of' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->assertSame(5, \App\Support\Tickets\OpeningTickets::rows()[0]['left']);
        $this->delete('/balances/ticket-sales/' . $row->id)->assertRedirect('/balances/ticket-sales');
        $this->assertSame(1, \App\Models\Tickets\OpeningTicket::count());

        $this->as('cashier')->get('/balances/ticket-sales')->assertForbidden();
    }

    public function testATicketWithNoRowInAPublishBeforeGoLiveIsOldAndOneAfterItIsNot()
    {
        $this->publish(1, 3, '2026-09-20', false);          // before go-live, no rows at all
        $this->publish(4, 6, '2026-10-05', false);          // after go-live

        $this->assertNull(LegacyTickets::publishFor($this->code(2)));       // go-live is not set yet: nothing is old
        $this->goLive();
        $this->assertNotNull(LegacyTickets::publishFor($this->code(2)));
        $this->assertNull(LegacyTickets::publishFor($this->code(5)));       // published after go-live
        $this->assertNull(LegacyTickets::publishFor($this->code(9)));       // in no publish
        $this->assertNull(LegacyTickets::publishFor('1234506000000002'));   // another business's sign
        $this->assertNull(LegacyTickets::publishFor('123'));
    }

    public function testAnOldTicketIsMadeSoldOnTheDayOfItsPublishWhenItIsUsed()
    {
        $this->nozzle();
        $this->goLive();
        $this->publish(1, 150, '2026-09-20', false);
        $barcode = $this->code(77);
        $this->assertSame(0, Ticket::count());

        // the lookup only looks
        $this->as('admin')->get('/tickets/lookup?barcode=' . $barcode . '&modal=1')->assertOk()->assertSee('sold on paper before go-live');
        $this->assertSame(0, Ticket::count());

        $this->post('/supplements', ['ticket_barcodes' => $barcode, 'nozzle_id' => $this->nozzleId, 'out_amount_lt' => 5, 'supplied_at' => '2026-10-10 08:30:00'])
            ->assertSessionHasNoErrors();

        $ticket = Ticket::find($barcode);
        $this->assertSame(config('ticket_statuses.supply'), (int) $ticket->status);
        $this->assertSame('2026-09-20', substr((string) $ticket->publish_date, 0, 10));         // the day of its publish
        $this->assertSame('2026-09-20', substr((string) $ticket->sale_date, 0, 10));            // and it was sold on the same day
        $this->assertSame('D', $ticket->fuel_type);
        $this->assertSame(0, (int) $ticket->spec);
        $this->assertSame('06', $ticket->code);
        $this->assertSame(77, (int) $ticket->serial);
        $this->assertNotNull($ticket->supplement_id);
        $this->assertSame(1, Ticket::count());                                                  // only this one has a row

        // used once: the second use is refused
        $this->post('/supplements', ['ticket_barcodes' => $barcode, 'nozzle_id' => $this->nozzleId, 'out_amount_lt' => 5, 'supplied_at' => '2026-10-10 09:30:00'])
            ->assertSessionHasErrors('ticket_barcodes');
        $this->assertSame(1, Supplement::count());

        // a ticket of the publish after go-live that has no row is not a ticket
        $this->publish(200, 210, '2026-10-05', false);
        $this->post('/supplements', ['ticket_barcodes' => $this->code(205), 'nozzle_id' => $this->nozzleId, 'out_amount_lt' => 5, 'supplied_at' => '2026-10-10 10:30:00'])
            ->assertSessionHasErrors('ticket_barcodes');
        $this->assertNull(Ticket::find($this->code(205)));
    }

    public function testATicketThatIsStillInTheStockRoomIsNeverSoldAndFollowsItsRow()
    {
        $this->nozzle();
        $this->goLive();
        $this->publish(1, 3, '2026-09-20');                                                     // rows, status published

        $this->as('admin')->post('/supplements', ['ticket_barcodes' => $this->code(2), 'nozzle_id' => $this->nozzleId, 'out_amount_lt' => 5, 'supplied_at' => '2026-10-10 08:30:00'])
            ->assertSessionHasErrors('ticket_barcodes');
        $this->assertSame(config('ticket_statuses.publish'), (int) Ticket::find($this->code(2))->status);
    }

    public function testRestrictingAnOldTicketMakesItsRowSoItCanNeverBeUsed()
    {
        $this->nozzle();
        $this->goLive();
        $this->publish(1, 150, '2026-09-20', false);
        $barcode = $this->code(40);

        $this->as('admin')->post('/tickets/' . $barcode . '/restrict')->assertSessionHas('status');
        $this->assertSame(config('ticket_statuses.restrict'), (int) Ticket::find($barcode)->status);

        $this->post('/supplements', ['ticket_barcodes' => $barcode, 'nozzle_id' => $this->nozzleId, 'out_amount_lt' => 5, 'supplied_at' => '2026-10-10 08:30:00'])
            ->assertSessionHasErrors('ticket_barcodes');
        $this->post('/tickets/' . $this->code(5000) . '/restrict')->assertNotFound();           // in no publish: still no ticket
    }

    public function testAnOldTicketSettlesATicketLoan()
    {
        $this->goLive();
        $this->publish(1, 150, '2026-09-20', false);
        $customer = Customer::forceCreate(['name' => 'ACME']);
        $loan = TicketLoan::forceCreate([
            'customer_id' => $customer->id, 'fuel_type' => 'D', 'amount_kg' => 5, 'loaned_at' => now(), 'date' => today()->toDateString(), 'sale_id' => null, 'user_id' => 1,
        ]);

        $this->as('admin')->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => $this->code(9)])->assertSessionHasNoErrors();

        $this->assertSame(config('ticket_statuses.supply'), (int) Ticket::find($this->code(9))->status);
        $this->assertEquals(5, $loan->fresh()->settled_amount_kg);
    }

    public function testATerminalReportsTheUseOfAnOldTicket()
    {
        $this->registerDevice();
        $this->goLive();
        $this->publish(1, 150, '2026-09-20', false);
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        Nozzle::forceCreate(['device_id' => Device::first()->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => 'D']);
        $supplementId = (string) Str::uuid();
        $push = function ($table, array $rows) {
            return json_decode($this->postJson('/api/push/' . $table, ['rows' => $rows])->assertOk()->getContent(), true);
        };

        $answer = $push('supplements', [[
            'id' => $supplementId, 'fuel_type' => 'D', 'nozzle_number' => 1, 'out_amount_lt' => '5.00', 'out_amount_kg' => '5.00', 'preset_kg' => '5.00',
            'supplied_at' => '2026-10-06 09:30:00', 'supply_date' => '2026-10-06',
        ]]);
        $this->assertEmpty($answer['rejected']);

        $ticket = ['barcode' => $this->code(11), 'fuel_type' => 'D', 'amount_kg' => '5.00', 'status' => config('ticket_statuses.supply'), 'supply_date' => '2026-10-06 09:30:00', 'supplement_id' => $supplementId];
        $answer = $push('tickets', [$ticket]);
        $this->assertEmpty($answer['rejected']);
        $made = Ticket::find($this->code(11));
        $this->assertSame(config('ticket_statuses.supply'), (int) $made->status);
        $this->assertSame($supplementId, $made->supplement_id);
        $this->assertSame('2026-09-20', substr((string) $made->sale_date, 0, 10));

        // a barcode in no old publish is still refused
        $answer = $push('tickets', [['barcode' => $this->code(9000)] + $ticket]);
        $this->assertCount(1, $answer['rejected']);
        $this->assertNull(Ticket::find($this->code(9000)));
    }

    // Selling needs a ticket that is published: an old ticket with no row was sold long ago and is never made by a sale (only a use makes it)
    public function testASaleNeverMakesAnOldTicket()
    {
        $this->goLive();
        $this->publish(1, 150, '2026-09-20', false);                       // no rows at all
        $customer = Customer::forceCreate(['name' => 'ACME']);

        $this->as('admin')->post('/ticket-sales', [
            'sale_type' => 'ticket', 'fuel_type' => 'D', 'lines' => [['spec' => 0, 'quantity' => 3, 'start_serial' => 10]],
            'customer_id' => $customer->id, 'price' => 2, 'usage' => 'sale', 'payment_method' => 'cash', 'paid_amount' => 30,
        ])->assertSessionHasErrors();

        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, \App\Models\Sales\Sale::count());
        $this->assertNull(Ticket::find($this->code(10)));
    }

    public function testTheConfigGivesTheTerminalsTheRangesOfTheOldPublishes()
    {
        $this->registerDevice();
        $x = json_decode($this->get('/api/pull/config')->assertOk()->getContent(), true);
        $this->assertSame([], $x['ticket_legacy']);                                             // no go-live date

        $this->goLive();
        $this->publish(1, 150, '2026-09-20', false);
        $this->publish(151, 200, '2026-10-05', false);                                          // after go-live: no range
        $x = json_decode($this->get('/api/pull/config')->assertOk()->getContent(), true);

        $this->assertSame([['code' => '06', 'from' => 1, 'to' => 150, 'fuel_type' => 'D', 'spec' => 0, 'kg' => '5.00', 'date' => '2026-09-20']], $x['ticket_legacy']);
    }

    public function testABackupKeepsTheUsedAndRestrictedRowsOfTheOldPublishes()
    {
        $this->goLive();
        $old = $this->publish(1, 3, '2026-09-20');
        $new = $this->publish(4, 6, '2026-10-05');
        $used = config('ticket_statuses.supply');
        foreach ([1, 4] as $serial) {
            Ticket::where('barcode', $this->code($serial))->update(['status' => $used, 'supply_date' => '2026-10-06 09:00:00']);
        }
        Ticket::where('barcode', $this->code(2))->update(['status' => config('ticket_statuses.restrict'), 'updated_at' => '2026-10-06 09:00:00']);
        Ticket::where('barcode', $this->code(5))->update(['status' => config('ticket_statuses.restrict'), 'updated_at' => '2026-10-06 09:00:00']);

        // the used and the restricted ticket of the publish after go-live are cleared, those of the old publish are not
        $this->assertSame(2, BackupRun::preview('2026-10-10')['tickets']);

        $this->assertSame(0, Setting::where('key', 'go_live_date')->delete() - 1);
        $this->assertSame(4, BackupRun::preview('2026-10-10')['tickets']);                      // without a go-live date nothing is old
    }

    // ---- the bulk page ----

    private function bulk($action, $ranges, $confirm = false, $role = 'admin')
    {
        return $this->as($role)->post('/ticket-bulk', ['stock_item' => 'D:0', 'ranges' => $ranges, 'action' => $action] + ($confirm ? ['confirm' => 1] : []));
    }

    public function testTheBulkPageIsForTheAdminOnly()
    {
        $this->goLive();
        $this->as('admin')->get('/ticket-bulk')->assertOk()->assertSee('Bulk Update');
        foreach (['manager', 'accountant', 'assistant', 'cashier'] as $role) {
            $this->as($role)->get('/ticket-bulk')->assertForbidden();
            $this->bulk('delete', '1-5', true, $role)->assertForbidden();
        }
    }

    public function testBulkDeleteShowsHowManyFirstThenRemovesOnlyTheNeverSoldTicketsOfTheOldPublishesInTheRanges()
    {
        $this->goLive();
        $old = $this->publish(1, 10, '2026-09-20');
        $new = $this->publish(11, 20, '2026-10-05');
        Ticket::where('barcode', $this->code(3))->update(['status' => config('ticket_statuses.sale')]);       // sold already (a real sale): kept
        Ticket::where('barcode', $this->code(4))->update(['status' => config('ticket_statuses.supply')]);     // used: kept

        // the preview changes nothing and counts what would go: 1-6 without 3 and 4, and 15 is of the publish after go-live
        $page = $this->bulk('delete', '1-6, 15', false)->assertOk();
        $this->assertSame(20, Ticket::count());
        $this->assertSame(4, $page->viewData('preview')['count']);
        $page->assertSee('4 tickets will be deleted');

        $this->bulk('delete', '1-6, 15', true)->assertRedirect('/ticket-bulk')->assertSessionHas('status');
        $this->assertSame(16, Ticket::count());
        $this->assertNull(Ticket::find($this->code(1)));
        $this->assertNotNull(Ticket::find($this->code(3)));
        $this->assertNotNull(Ticket::find($this->code(4)));
        $this->assertNotNull(Ticket::find($this->code(7)));          // outside the ranges
        $this->assertNotNull(Ticket::find($this->code(15)));         // published after go-live

        // a deleted ticket counts as sold on paper again
        $this->assertNotNull(LegacyTickets::publishFor($this->code(1)));
    }

    public function testBulkMarkAsSoldAndBulkRestrict()
    {
        $this->goLive();
        $this->publish(1, 6, '2026-09-20');
        $this->publish(7, 9, '2026-10-05');

        $this->bulk('sold', '1-3, 8', true)->assertSessionHas('status');
        $sold = Ticket::find($this->code(2));
        $this->assertSame(config('ticket_statuses.sale'), (int) $sold->status);
        $this->assertSame('2026-09-20', substr((string) $sold->sale_date, 0, 10));                              // the day of its publish
        $this->assertNull($sold->sale_id);
        $this->assertSame(config('ticket_statuses.publish'), (int) Ticket::find($this->code(8))->status);       // after go-live: not an old ticket, kept as it was
        $this->assertSame(0, (int) $sold->synced);

        // restrict works on published and sold tickets of any publish
        $this->bulk('restrict', '2-4, 8', true)->assertSessionHas('status');
        foreach ([2, 3, 4, 8] as $serial) {
            $this->assertSame(config('ticket_statuses.restrict'), (int) Ticket::find($this->code($serial))->status);
        }
        $this->assertSame(config('ticket_statuses.sale'), (int) Ticket::find($this->code(1))->status);
        $this->assertSame(config('ticket_statuses.publish'), (int) Ticket::find($this->code(5))->status);
    }

    public function testTheBulkPageNeedsGoLiveForDeleteAndSoldAndChecksTheRanges()
    {
        $this->publish(1, 3, '2026-09-20');

        $this->bulk('delete', '1-3', true)->assertSessionHasErrors('action');
        $this->bulk('sold', '1-3', true)->assertSessionHasErrors('action');
        $this->assertSame(3, Ticket::count());
        $this->bulk('restrict', '1-3', true)->assertSessionHasNoErrors();                                       // no go-live needed

        $this->goLive();
        foreach (['5-2', 'abc', '0-3', '1-2-3'] as $bad) {
            $this->bulk('delete', $bad)->assertSessionHasErrors('ranges');
        }
        $this->bulk('delete', '')->assertSessionHasErrors('ranges');
        $this->post('/ticket-bulk', ['stock_item' => 'D:9', 'ranges' => '1-3', 'action' => 'delete'])->assertSessionHasErrors('stock_item');
        $this->post('/ticket-bulk', ['stock_item' => 'D:0', 'ranges' => '1-3', 'action' => 'burn'])->assertSessionHasErrors('action');
    }
}
