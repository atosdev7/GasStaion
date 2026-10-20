<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Admin\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RegistersDevice;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 * Rows are only acked when they were last changed before the page was served, so tests move the clock forward first.
 */
class PullApiTest extends TestCase
{
    use RefreshDatabase, RegistersDevice;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 09:00:00'); // before seeding, so seeded rows are not stamped with the real clock
        $this->seed();

        User::forceCreate([
            'name' => 'Cash', 'username' => 'cash', 'password' => bcrypt('secret1'), 'role' => 'cashier', 'active' => true,
            'pos_salt' => 'aa11', 'pos_hash' => hash('sha256', 'aa11' . 'secret1'),
        ]);
        $this->registerDevice();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload($response)
    {
        return json_decode($response->getContent(), true);
    }

    private function pull($table, $query = '')
    {
        return $this->payload($this->get('/api/pull/' . $table . $query)->assertOk());
    }

    private function ack($table, array $page)
    {
        $keys = array_map(function ($row) use ($table) {
            return $table === 'tickets' ? $row['barcode'] : $row['id'];
        }, $page['rows']);

        return $this->payload($this->postJson('/api/pull/' . $table . '/ack', ['keys' => $keys, 'pulled_at' => $page['server_time']])->assertOk());
    }

    private function later($seconds = 10)
    {
        Carbon::setTestNow(Carbon::now()->addSeconds($seconds));
    }

    private function ticket($barcode, $status, $extra = [])
    {
        return Ticket::forceCreate($extra + [
            'barcode' => $barcode, 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => (int) substr($barcode, -3),
            'status' => $status,
        ]);
    }

    public function testPullEndpointsNeedARegisteredAddress()
    {
        $this->fromAStranger('GET', '/api/pull/config')->assertStatus(403);
        $this->fromAStranger('GET', '/api/pull/customers')->assertStatus(403);
        $this->fromAStranger('POST', '/api/pull/customers/ack')->assertStatus(403);
        $this->fromAStranger('POST', '/api/sync/initial-done')->assertStatus(403);
        $this->get('/api/pull/nothing')->assertStatus(404);
    }

    public function testTheTerminalLearnsWhichOfItsRecordsAreConfirmed()
    {
        $row = function ($id, $confirmed) {
            return ['id' => $id, 'sale_type' => 'spot', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 1, 'payment_method' => 'cash', 'total_amount' => 2,
                'paid_amount' => 2, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => '2026-10-04', 'user_id' => 1, 'confirmed_at' => $confirmed ? now() : null];
        };
        $done = \App\Models\Sales\Sale::forceCreate($row('aaaaaaaa-0000-0000-0000-000000000001', true));
        $open = \App\Models\Sales\Sale::forceCreate($row('aaaaaaaa-0000-0000-0000-000000000002', false));
        $nozzle = Nozzle::forceCreate(['device_id' => Device::first()->id, 'number' => 1, 'tank_id' => Tank::forceCreate(['name' => 'T', 'fuel_type' => 'D'])->id, 'fuel_type' => 'D']);
        $supplement = function ($id, $confirmed) use ($nozzle) {
            return \App\Models\Supplements\Supplement::forceCreate(['id' => $id, 'fuel_type' => 'D', 'nozzle_id' => $nozzle->id, 'out_amount_lt' => 1, 'out_amount_kg' => 1, 'preset_kg' => 1,
                'supplied_at' => '2026-10-04 10:00:00', 'supply_date' => '2026-10-04', 'confirmed_at' => $confirmed ? now() : null]);
        };
        $supplement('bbbbbbbb-0000-0000-0000-000000000001', true);
        $supplement('bbbbbbbb-0000-0000-0000-000000000002', false);

        $answer = $this->payload($this->postJson('/api/confirmed', [
            'sales' => [$done->id, $open->id, 'cccccccc-0000-0000-0000-000000000009'],
            'supplements' => ['bbbbbbbb-0000-0000-0000-000000000001', 'bbbbbbbb-0000-0000-0000-000000000002'],
        ])->assertOk());

        $this->assertSame('ok', $answer['status']);
        $this->assertSame([$done->id], $answer['sales']);                           // the unconfirmed and the unknown one are not named
        $this->assertSame(['bbbbbbbb-0000-0000-0000-000000000001'], $answer['supplements']);

        $this->assertSame(['status' => 'ok', 'sales' => [], 'supplements' => []], $this->payload($this->postJson('/api/confirmed', [])->assertOk()));
        $this->postJson('/api/confirmed', ['sales' => ['not an id!']])->assertStatus(422);
        $this->fromAStranger('POST', '/api/confirmed')->assertStatus(403);
    }

    public function testTheCardLedgerIsPulledWithItsRunningBalances()
    {
        $card = \App\Models\Cards\Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + \App\Support\Cards\CardPin::make('1234'));
        $sale = \App\Models\Sales\Sale::forceCreate([
            'card_id' => $card->id, 'sale_type' => 'card', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 100, 'payment_method' => 'cash', 'total_amount' => 200,
            'paid_amount' => 200, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => '2026-10-04', 'user_id' => 1, 'created_at' => '2026-10-04 09:00:00',
        ]);
        $this->later();

        $page = $this->pull('card_transactions');
        $this->assertCount(1, $page['rows']);
        $row = $page['rows'][0];
        $this->assertSame(['id', 'card_id', 'type', 'kg', 'balance_kg', 'price', 'total_amount', 'source_id', 'occurred_at', 'seq', 'comment'], array_keys($row));
        $this->assertSame([$card->id, 'topup', '100.00', '100.00', '2.00', '200.00', $sale->id], [$row['card_id'], $row['type'], $row['kg'], $row['balance_kg'], $row['price'], $row['total_amount'], $row['source_id']]);
        $this->assertSame('2026-10-04 09:00:00', $row['occurred_at']);
        $this->assertSame(1, $this->ack('card_transactions', $page)['acked']);
        $this->assertSame([], $this->pull('card_transactions')['rows']);

        // a fill that happened earlier arrives later: the balance after the top-up changes, and the entry is sent again
        $this->later();
        \App\Models\Supplements\Supplement::forceCreate(['card_id' => $card->id, 'fuel_type' => 'D', 'nozzle_id' => 1, 'preset_kg' => 30, 'out_amount_lt' => 30, 'out_amount_kg' => 30, 'supplied_at' => '2026-10-03 09:00:00']);
        $this->later();
        $again = $this->pull('card_transactions');
        $this->assertCount(2, $again['rows']);
        $balances = collect($again['rows'])->pluck('balance_kg', 'type')->all();
        $this->assertEquals(['fill' => '-30.00', 'topup' => '70.00'], $balances);   // the rows come in the order of their ids: compare without the order
        $this->assertSame('70.00', $this->pull('cards')['rows'][0]['balance_kg']);                       // the card says it too
    }

    public function testConfigIsASnapshotOfThisDevice()
    {
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7.5, 'density' => 0.83]);
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => 1000]);
        $device = Device::where('name', 'POS1')->first();
        Nozzle::forceCreate(['device_id' => $device->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => 'D', 'board_address' => 3]);

        $x = $this->payload($this->get('/api/pull/config')->assertOk());

        $this->assertSame('ok', $x['status']);
        $this->assertSame(500000000, $x['ticket_serial_base']);
        $this->assertSame('00000', $x['ticket_sign']); // the sign of the tests (TICKET_SIGN in phpunit.xml)
        $this->assertSame(['direct' => '21', 'self' => '22', 'other' => '23', 'ticket_loan' => '24', 'remainder' => '25'], $x['reserved_codes']);
        $this->assertSame(500000000, $x['ticket_serial_floor']['21']); // nothing minted yet: the floor is the base itself
        $fuels = array_column($x['fuel_types'], null, 'fuel_type');
        $this->assertSame('Diesel', $fuels['D']['name']);
        $this->assertSame('7.50', $fuels['D']['price']);
        $this->assertSame('0.8300', $fuels['D']['density']);
        $this->assertSame('0.00', $fuels['A']['price']); // a fuel type nobody priced yet
        $this->assertNull($fuels['A']['density']);

        $this->assertCount(1, $x['tanks']);
        $this->assertSame('T1', $x['tanks'][0]['name']);
        $this->assertSame('1000.00', $x['tanks'][0]['capacity_kg']);
        $this->assertSame('0.00', $x['tanks'][0]['stock_kg']);

        $this->assertArrayNotHasKey('nozzles', $x); // the nozzles are set up on the Smart210, never sent to it

        $this->assertSame('POS1', $x['device']['name']);
        $this->assertSame(3, $x['device']['purge_days']);
        $this->assertSame(64, strlen($x['device']['pin_hash']));
        $this->assertFalse($x['device']['initial_sync_done']);
    }

    public function testTheConfigTellsADeviceWhereItsOwnTicketNumbersStand()
    {
        foreach ([['21', 500000007], ['21', 500000003], ['21', 12], ['22', 500000002]] as $i => $t) {
            \Illuminate\Support\Facades\DB::table('tickets')->insert([
                'barcode' => sprintf('00000%2s%09d', $t[0], $t[1]), 'fuel_type' => 'D', 'code' => $t[0], 'serial' => $t[1],
                'status' => 1, 'synced' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $floor = $this->payload($this->get('/api/pull/config')->assertOk())['ticket_serial_floor'];

        $this->assertSame(500000007, $floor['21']); // the highest in the device range; the web's own serial 12 does not count
        $this->assertSame(500000002, $floor['22']);
        $this->assertSame(500000000, $floor['23']); // none yet
    }

    public function testUsersCarryLoginHashesAndTheDeletedFlag()
    {
        User::forceCreate(['name' => 'Acc', 'username' => 'acc', 'password' => 'x', 'role' => 'accountant', 'active' => true]);
        User::where('username', 'acc')->first()->delete();

        $rows = array_column($this->pull('users')['rows'], null, 'username');

        $this->assertTrue($rows['cash']['pos']);
        $this->assertTrue($rows['cash']['active']);
        $this->assertSame('aa11', $rows['cash']['pos_salt']);
        $this->assertSame(hash('sha256', 'aa11' . 'secret1'), $rows['cash']['pos_hash']);
        $this->assertFalse($rows['cash']['deleted']);
        $this->assertFalse($rows['acc']['pos']); // an accountant has no Smart210 login
        $this->assertTrue($rows['acc']['deleted']);
    }

    public function testPagesAreAckedOneByOneUntilNothingIsLeft()
    {
        foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
            Customer::forceCreate(['name' => $name]);
        }
        $this->later(); // 5 new customers + the seeded Self = 6

        $one = $this->pull('customers', '?limit=4');
        $this->assertCount(4, $one['rows']);
        $this->assertTrue($one['more']);
        $this->assertSame(4, $this->ack('customers', $one)['acked']);

        $two = $this->pull('customers', '?limit=4');
        $this->assertCount(2, $two['rows']);
        $this->assertFalse($two['more']);
        $this->assertSame(2, $this->ack('customers', $two)['acked']);

        $three = $this->pull('customers', '?limit=4');
        $this->assertSame([], $three['rows']);
        $this->assertFalse($three['more']);
        $ids = array_merge(array_column($one['rows'], 'id'), array_column($two['rows'], 'id'));
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids); // in key order
        $this->assertCount(6, array_unique($ids)); // every row exactly once
    }

    public function testAPageIsRepeatedUntilItIsAcked()
    {
        Customer::forceCreate(['name' => 'A']);
        $this->later();

        $first = $this->pull('customers');
        $again = $this->pull('customers');

        $this->assertSame($first['rows'], $again['rows']); // nothing was acked, so nothing is lost
    }

    public function testARowChangedAfterThePageWasServedIsNotMarkedSynced()
    {
        $a = Customer::forceCreate(['name' => 'Before']);
        $this->later();

        $page = $this->pull('customers');
        $a->forceFill(['name' => 'After'])->save(); // the web edits it between the pull and the ack
        $result = $this->ack('customers', $page);

        $this->assertSame(count($page['rows']) - 1, $result['acked']); // everything but the edited row
        $this->later();
        $names = array_column($this->pull('customers')['rows'], 'name');
        $this->assertSame(['After'], $names);
    }

    public function testWebChangesPutAnAckedRowBackToUnsynced()
    {
        $c = Customer::forceCreate(['name' => 'Old']);
        $this->later();
        $this->ack('customers', $this->pull('customers'));
        $this->assertSame([], $this->pull('customers')['rows']);

        $this->later();
        $c->forceFill(['name' => 'New'])->save();
        $this->later();
        $rows = $this->pull('customers')['rows'];
        $this->assertSame(['New'], array_column($rows, 'name'));
        $this->ack('customers', $this->pull('customers'));

        $this->later();
        $c->delete(); // soft delete
        $this->later();
        $rows = $this->pull('customers')['rows'];
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['deleted']);
    }

    public function testTicketsStatusChangesOnTheWebReachTheDevice()
    {
        $t = $this->ticket('0000000000000001', 1);
        $this->later();
        $this->ack('tickets', $this->pull('tickets'));

        $this->later();
        $t->forceFill(['status' => 3])->save(); // restricted on the web (accountant)
        $this->later();

        $rows = $this->pull('tickets')['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['status']);
    }

    public function testSellingPublishedTicketsOnTheWebReachesTheDevice()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->post('/ticket-inbound', ['fuel_type' => 'D', 'spec' => 0, 'quantity' => 3, 'inbound_date' => '2026-10-05']);
        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 1, 'end_serial' => 3]);
        $this->later();

        $published = $this->pull('tickets');
        $this->assertSame([0, 0, 0], array_column($published['rows'], 'status'));
        $this->assertSame('5.00', $published['rows'][0]['amount_kg']); // press ticket: amount comes from its spec
        $this->ack('tickets', $published);

        $customer = Customer::forceCreate(['name' => 'ACME']);
        $this->post('/ticket-sales', [
            'sale_type' => 'ticket', 'fuel_type' => 'D', 'lines' => [['spec' => 0, 'quantity' => 2]],
            'customer_id' => $customer->id, 'price' => 2, 'usage' => 'sale', 'payment_method' => 'cash', 'paid_amount' => 20,
        ])->assertRedirect();
        $this->later();

        $sold = $this->pull('tickets')['rows'];
        $this->assertSame([1, 1], array_column($sold, 'status'));
        $this->assertNotNull($sold[0]['sale_id']);
        $this->assertNotNull($sold[0]['sale_date']);
    }

    public function testADirectTicketKeepsItsOwnAmount()
    {
        $this->ticket('2100000000000001', 1, ['spec' => null, 'amount_kg' => 7.5, 'code' => '21']);

        $row = $this->pull('tickets')['rows'][0];

        $this->assertNull($row['spec']);
        $this->assertSame('7.50', $row['amount_kg']);
    }

    public function testBeginSettlesFinishedTicketsButLaterChangesStillArrive()
    {
        $this->ticket('0000000000000001', 0);
        $sold = $this->ticket('0000000000000002', 1);
        $this->ticket('0000000000000003', 2);
        $restricted = $this->ticket('0000000000000004', 3);
        $this->later();
        $this->postJson('/api/sync/begin')->assertOk();

        $page = $this->pull('tickets');
        $this->assertSame([0, 1], array_column($page['rows'], 'status')); // the supplied and restricted ones were never sent
        $this->ack('tickets', $page);

        // Still during the first sync: the web restricts a ticket the device already has. It must not be skipped.
        $this->later();
        $sold->forceFill(['status' => 3])->save();
        $this->later();
        $this->assertSame([3], array_column($this->pull('tickets')['rows'], 'status'));
        $this->ack('tickets', $this->pull('tickets'));

        // Lifting a restriction on a ticket the device never had makes it usable, so it is sent.
        $this->later();
        $restricted->forceFill(['status' => 1])->save();
        $this->later();
        $rows = $this->pull('tickets')['rows'];
        $this->assertSame(['0000000000000004'], array_column($rows, 'barcode'));
        $this->assertSame([1], array_column($rows, 'status'));
    }

    public function testInitialDoneMarksTheDeviceAsSetUp()
    {
        $this->assertFalse((bool) Device::where('name', 'POS1')->value('initial_sync_done'));

        $this->postJson('/api/sync/initial-done', [])->assertOk();

        $this->assertTrue((bool) Device::where('name', 'POS1')->value('initial_sync_done'));
    }

    public function testTicketLoansPage()
    {
        $customer = Customer::forceCreate(['name' => 'Loaner']);
        $loan = TicketLoan::forceCreate([
            'customer_id' => $customer->id, 'fuel_type' => 'D', 'amount_kg' => 30, 'user_id' => 1, 'loaned_at' => '2026-10-04 08:00:00',
        ]);
        $this->later();

        $rows = $this->pull('ticket_loans')['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame($loan->id, $rows[0]['id']);
        $this->assertSame((int) $customer->id, $rows[0]['customer_id']);
        $this->assertSame('30.00', $rows[0]['amount_kg']);
        $this->assertSame('0.00', $rows[0]['settled_amount_kg']);
        $this->assertFalse($rows[0]['deleted']);
    }

    public function testBeginStartsTheDeviceFromNothing()
    {
        Customer::forceCreate(['name' => 'A']);
        $this->later();
        $this->ack('customers', $this->pull('customers'));
        $this->postJson('/api/sync/initial-done', [])->assertOk();
        $this->assertSame([], $this->pull('customers')['rows']);

        $this->postJson('/api/sync/begin')->assertOk();

        $this->assertFalse((bool) Device::where('name', 'POS1')->value('initial_sync_done'));
        $this->assertCount(2, $this->pull('customers')['rows']); // A and the seeded Self, all again
    }

    public function testBadAcksAreRejected()
    {
        $bad = [
            [['pulled_at' => '2026-10-05 09:00:00']], // no keys
            [['keys' => 'x', 'pulled_at' => '2026-10-05 09:00:00']], // keys not a list
            [['keys' => [[1]], 'pulled_at' => '2026-10-05 09:00:00']], // nested key
            [['keys' => [1]]], // no pulled_at
            [['keys' => [1], 'pulled_at' => 'yesterday']],
        ];

        foreach ($bad as $case) {
            $res = $this->postJson('/api/pull/customers/ack', $case[0])->assertStatus(422);
            $this->assertSame('error', $this->payload($res)['status']);
        }
    }

    public function testLoginBookkeepingDoesNotReflagAUser()
    {
        $cash = User::where('username', 'cash')->first();
        $this->later();
        $this->ack('users', $this->pull('users'));
        $this->assertSame([], $this->pull('users')['rows']);

        $this->later();
        $cash->forceFill(['remember_token' => 'abc123'])->save(); // what a "remember me" sign-in writes
        $this->later();
        $this->assertSame([], $this->pull('users')['rows']);

        $cash->forceFill(['name' => 'Cash Two'])->save(); // a real change is sent
        $this->later();
        $this->assertSame(['Cash Two'], array_column($this->pull('users')['rows'], 'name'));
    }

    public function testPullingDoesNotCountAsAnUpload()
    {
        $this->pull('customers');

        // Downloading is automatic; only an upload (push) records the device's last-upload time.
        $this->assertNull(Device::where('name', 'POS1')->value('last_synced_at'));
    }

    public function testTheConfigSaysNothingAboutARestoreUntilOneHappened()
    {
        $x = $this->payload($this->get('/api/pull/config')->assertOk());

        $this->assertArrayHasKey('restore', $x);
        $this->assertNull($x['restore']);
    }

    public function testAfterARestoreTheConfigTellsTheTerminalsUpToWhenTheDataIsValid()
    {
        $this->artisan('backup:restored', ['--since' => '2026-10-04 08:30:00'])->assertExitCode(0);

        $x = $this->payload($this->get('/api/pull/config')->assertOk());

        $this->assertSame('2026-10-04 08:30:00', $x['restore']['since']);
        $this->assertRegExp('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $x['restore']['restored_at']);
    }

    public function testTheRestoreTimeMustBeARealTimeInThePast()
    {
        $this->artisan('backup:restored', ['--since' => 'yesterday'])->assertExitCode(1);
        $this->artisan('backup:restored', ['--since' => '2026-13-40 08:30:00'])->assertExitCode(1);
        $this->artisan('backup:restored', ['--since' => '2999-01-01 00:00:00'])->assertExitCode(1);

        $this->assertNull($this->payload($this->get('/api/pull/config')->assertOk())['restore']);
    }

    public function testWithoutATimeTheNewestDumpDecides()
    {
        config(['backup.dump_dir' => 'backups/test-restored']);
        $dir = storage_path('app/backups/test-restored');
        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
        $this->artisan('backup:restored')->assertExitCode(1); // no dump, no time

        mkdir($dir, 0755, true);
        touch($dir . '/old.sql.gz', strtotime('2026-10-03 02:00:00'));
        touch($dir . '/new.sql.gz', strtotime('2026-10-04 08:31:00'));
        $this->artisan('backup:restored')->assertExitCode(0);
        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);

        $this->assertSame('2026-10-04 08:31:00', $this->payload($this->get('/api/pull/config')->assertOk())['restore']['since']);
    }
}
