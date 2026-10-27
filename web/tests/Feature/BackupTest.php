<?php

namespace Tests\Feature;

use App\Models\Admin\Backup;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\TankBalance;
use App\Models\Fuel\Nozzle;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Fuel\Tank;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Admin\User;
use App\Support\Dashboard\TankLevels;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private const UP_TO = '2026-09-15';

    private $customer;
    private $tank;
    private $nozzle;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'backup.dump_before_clear' => false,
            'backup.export_dir' => 'backups/test-exports',
            'backup.dump_dir' => 'backups/test-daily',
        ]);
        $this->customer = Customer::forceCreate(['name' => 'ACME']);
        $this->tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => 5000]);
        $device = Device::forceCreate(['name' => 'Pump 1']);
        $this->nozzle = Nozzle::forceCreate(['device_id' => $device->id, 'number' => 1, 'tank_id' => $this->tank->id, 'fuel_type' => 'D']);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('app/backups/test-exports'));
        (new Filesystem)->deleteDirectory(storage_path('app/backups/test-daily'));
        parent::tearDown();
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    // The records of the tests are confirmed unless a test says otherwise: a backup refuses to clear what nobody confirmed.
    private function sale($checkoutDate, $confirmed = true)
    {
        return Sale::forceCreate([
            'customer_id' => $this->customer->id, 'sale_type' => 'ticket',
            'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 10, 'payment_method' => 'cash', 'total_amount' => 20,
            'paid_amount' => 0, 'unpaid_amount' => 20, 'usage' => 'sale', 'sale_date' => $checkoutDate, 'user_id' => 1,
            'confirmed_at' => $confirmed ? now() : null, 'confirmed_by' => $confirmed ? 1 : null,
        ]);
    }

    private function supplement($kg, $checkoutDate, $confirmed = true)
    {
        return Supplement::forceCreate([
            'fuel_type' => 'D', 'nozzle_id' => $this->nozzle->id, 'device_id' => $this->nozzle->device_id,
            'out_amount_lt' => $kg, 'out_amount_kg' => $kg, 'preset_kg' => $kg, 'supplied_at' => ($checkoutDate ?: '2026-09-28') . ' 11:00:00',
            'supply_date' => $checkoutDate, 'confirmed_at' => $confirmed ? now() : null, 'confirmed_by' => $confirmed ? 1 : null,
        ]);
    }

    private function inbound($kg, $date)
    {
        return FuelInbound::forceCreate([
            'tank_id' => $this->tank->id, 'fuel_type' => 'D', 'amount_kg' => $kg, 'cost' => 1, 'inbound_date' => $date, 'user_id' => 1,
            'confirmed_at' => now(), 'confirmed_by' => 1, // (confirmed: an unconfirmed one stops a backup, see the test of the blockers)
        ]);
    }

    private function ticket($serial, $status, $date)
    {
        return Ticket::forceCreate([
            'barcode' => sprintf('000000600%07d', $serial), 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => $serial,
            'status' => config('ticket_statuses.' . $status), 'supply_date' => $status === 'supply' ? $date : null,
            'created_at' => $date, 'updated_at' => $date,
        ]);
    }

    // Two of each kind inside the cut-off, and rows that must survive.
    private function scenario()
    {
        $old = $this->sale('2026-09-10');
        $this->sale('2026-09-15');
        $this->sale('2026-09-20');       // after the cut-off
        $this->sale('2026-09-12')->delete(); // soft-deleted, still cleared

        TankBalance::create(['tank_id' => $this->tank->id, 'balance' => 1000]);
        $this->inbound(500, '2026-09-05');
        $this->inbound(300, '2026-09-20');
        $this->supplement(100, '2026-09-10');
        $this->supplement(40, '2026-09-20');
        $this->supplement(7, null);

        $this->ticket(1, 'supply', '2026-09-10');
        $this->ticket(2, 'restrict', '2026-09-11');
        $this->ticket(3, 'supply', '2026-09-20');
        $this->ticket(4, 'sale', '2026-09-10');

        $debt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => $old->id, 'amount' => 20, 'payment_method' => 'cash', 'user_id' => 1]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 5, 'payment_method' => 'cash',
            'paid_date' => '2026-09-11', 'date' => '2026-09-11', 'user_id' => 1, 'confirmed_at' => now(), 'confirmed_by' => 1,
        ]);
        $loan = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 10, 'settled_amount_kg' => 5,
            'date' => '2026-09-10', 'user_id' => 1,
        ]);
        TicketLoanSettlement::forceCreate([
            'loan_id' => $loan->id, 'ticket_barcode' => '0000006000000001', 'amount_kg' => 5, 'settled_at' => '2026-09-10',
            'date' => '2026-09-10', 'user_id' => 1, 'confirmed_at' => now(), 'confirmed_by' => 1,
        ]);
    }

    public function testPreviewCountsWhatWouldBeCleared()
    {
        $this->scenario();

        $response = $this->as('admin')->get('/backup?up_to=' . self::UP_TO)->assertOk();

        $this->assertSame(['sale_items' => 0, 'sales' => 3, 'fuel_outbounds' => 1, 'supplements' => 1, 'fuel_inbounds' => 1, 'tickets' => 2], $response->viewData('preview'));
        $this->assertEquals([$this->tank->id => 400.0], $response->viewData('carry')); // 500 in - 100 out
        $this->assertSame(0, Backup::count());
    }

    public function testRemaindersAreNeverCleared()
    {
        $this->scenario();
        $supply = Supplement::whereNotNull('supply_date')->where('supply_date', '<=', self::UP_TO)->first();   // a supply that the backup clears
        $fromSupply = \App\Models\Supplements\Remainder::forceCreate([
            'supplement_id' => $supply->id, 'ticket_barcode' => '0000021000000001', 'fuel_type' => 'D', 'amount_kg' => 2, 'remainder_date' => '2026-09-10', 'created_at' => '2026-09-10 10:00:00',
        ]);
        $opening = \App\Models\Supplements\Remainder::forceCreate([
            'supplement_id' => null, 'ticket_barcode' => '0000021000000002', 'fuel_type' => 'D', 'amount_kg' => 3, 'remainder_date' => '2026-09-01', 'created_at' => '2026-09-01 10:00:00',
        ]);

        $preview = $this->as('admin')->get('/backup?up_to=' . self::UP_TO)->assertOk()->viewData('preview');
        $this->assertArrayNotHasKey('remainders', $preview);

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup');

        $this->assertNull(Supplement::find($supply->id));                                           // the supply is gone...
        $this->assertNotNull(\App\Models\Supplements\Remainder::find($fromSupply->id));                        // ...its remainder stays
        $this->assertNotNull(\App\Models\Supplements\Remainder::find($opening->id));
        $this->as('admin')->get('/remainders')->assertOk()->assertSee(__('supplements.remainder_supply_cleared'))->assertSee(__('supplements.remainder_opening'));
    }

    public function testRunExportsClearsAndKeepsTankLevelsAndBalances()
    {
        $this->scenario();
        $before = TankLevels::all()->first()->current; // 1000 + 800 - 147

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup')->assertSessionHas('status');

        $this->assertSame(1, Sale::withTrashed()->count());
        $this->assertSame(2, Supplement::count());
        $this->assertSame(1, FuelInbound::count());
        $this->assertSame(['0000006000000003', '0000006000000004'], Ticket::orderBy('barcode')->pluck('barcode')->all());

        // the cleared inbound (500 at 1.00) and outbound (100) went into the balances of the tank, one row per cost; the oldest fuel goes first, which is the 1000
        // the tank started with (no cost): 900 of it and the 500 are left
        $rows = TankBalance::where('tank_id', $this->tank->id)->get();
        $this->assertCount(2, $rows);
        $none = $rows->first(function ($row) { return $row->cost === null; });
        $this->assertEquals(900, $none->balance);
        $this->assertSame('backup', $none->source);
        $this->assertSame(Backup::first()->id, (int) $none->backup_id);
        $priced = $rows->first(function ($row) { return $row->cost !== null; });
        $this->assertEquals(1, $priced->cost);
        $this->assertEquals(500, $priced->balance);
        $this->assertSame('2026-09-05', substr((string) $priced->balance_date, 0, 10));
        $this->assertEquals($before, TankLevels::all()->first()->current);

        $this->assertSame(1, Debt::count());
        $this->assertSame(1, DebtPayment::count());
        $this->assertSame(1, TicketLoan::count());
        $this->assertSame(1, TicketLoanSettlement::count());

        $backup = Backup::first();
        $this->assertSame(3, $backup->cleared['sales']);
        $this->assertEquals([$this->tank->id => 400], $backup->tank_carry);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/backups/test-exports/' . $backup->file)));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(3, $manifest['rows']['sales']); // exported before clearing; the deleted sale is not backed up
        $this->assertCount(3, array_filter(explode("\n", $zip->getFromName('sales.jsonl'))));
        $this->assertFalse($zip->getFromName('tank_balances.jsonl'));              // (the backup server enters its own opening balances)
        $this->assertFalse($zip->getFromName('settings.jsonl'));                   // (nor the settings and terminals of the live server)
        $this->assertFalse($zip->getFromName('devices.jsonl'));
        $this->assertFalse($zip->getFromName('backups.jsonl'));
        $zip->close();
    }

    public function testTheOutboundRowsOfClearedSuppliesAndTheOnesByHandGoWithTheBackupAndTheStockStays()
    {
        $this->scenario();
        foreach (['2026-09-12' => 20, '2026-09-20' => 15] as $date => $kg) {   // by hand: one inside the cut-off, one after it
            $row = new \App\Models\Fuel\FuelOutbound;
            $row->forceFill(['tank_id' => $this->tank->id, 'outbound_date' => $date, 'amount_kg' => $kg, 'user_id' => 1, 'confirmed_at' => now(), 'confirmed_by' => 1])->save();
        }
        $before = TankLevels::all()->first()->current;                           // 1000 + 800 - (100 + 40 + 7) - (20 + 15) = 1618
        $this->assertEquals(1618, $before);
        $this->assertSame(5, \App\Models\Fuel\FuelOutbound::count());

        $preview = $this->as('admin')->get('/backup?up_to=' . self::UP_TO)->assertOk()->viewData('preview');
        $this->assertSame(2, $preview['fuel_outbounds']);                          // the row of the supply of 09-10 and the one by hand of 09-12

        $this->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup')->assertSessionHas('status');

        // what is left: the supply of 09-20, the supply that has no date yet, and the outbound by hand of 09-20
        $this->assertSame([7.0, 15.0, 40.0], \App\Models\Fuel\FuelOutbound::orderBy('amount_kg')->pluck('amount_kg')->map(function ($kg) { return (float) $kg; })->all());
        $this->assertEquals($before, TankLevels::all()->first()->current);        // 500 in, 120 out went into the carry-forward
        $backup = Backup::first();
        $this->assertEquals([$this->tank->id => 380], $backup->tank_carry);
        $this->assertSame(2, $backup->cleared['fuel_outbounds']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/backups/test-exports/' . $backup->file)));
        $this->assertCount(5, array_filter(explode("\n", $zip->getFromName('fuel_outbounds.jsonl'))));  // everything is exported before it is cleared
        $zip->close();
    }
    // what the cleared fuel inbounds and outbounds leave is written to tank_balances, one row per cost: the supplies take the oldest fuel first
    public function testTheClearedInboundsAndOutboundsBecomeTheBalancesOfTheTankPerCost()
    {
        TankBalance::create(['tank_id' => $this->tank->id, 'balance' => 200, 'cost' => 5, 'balance_date' => '2026-09-01']);
        foreach ([[300, 6, '2026-09-05'], [400, 7, '2026-09-08'], [100, 6, '2026-09-09'], [50, 8, '2026-09-20']] as $in) {
            FuelInbound::forceCreate([
                'tank_id' => $this->tank->id, 'fuel_type' => 'D', 'amount_kg' => $in[0], 'cost' => $in[1], 'inbound_date' => $in[2], 'user_id' => 1,
                'confirmed_at' => now(), 'confirmed_by' => 1,
            ]);
        }
        $this->supplement(450, '2026-09-10');                                      // cleared: 200 at 5.00 and 250 of the 300 at 6.00
        $this->supplement(30, '2026-09-20');                                       // after the cut-off: stays
        $before = TankLevels::all()->first()->current;                             // 200 + 850 - 480

        $preview = $this->as('admin')->get('/backup?up_to=' . self::UP_TO)->assertOk()->viewData('balancesAfter');
        $this->assertEquals([['cost' => 6.0, 'balance' => 150.0, 'balance_date' => '2026-09-09'], ['cost' => 7.0, 'balance' => 400.0, 'balance_date' => '2026-09-08']],
            array_map(function ($row) { return array_intersect_key($row, array_flip(['cost', 'balance', 'balance_date'])); }, $preview[$this->tank->id]));

        $this->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup')->assertSessionHas('status');

        $rows = TankBalance::where('tank_id', $this->tank->id)->orderBy('cost')->get();
        $this->assertCount(2, $rows);                                              // the 200 at 5.00 is used up and gone; the two lots at 6.00 are one row
        $this->assertEquals([6, 150], [$rows[0]->cost, $rows[0]->balance]);
        $this->assertEquals([7, 400], [$rows[1]->cost, $rows[1]->balance]);
        $this->assertSame('2026-09-09', substr((string) $rows[0]->balance_date, 0, 10));
        $this->assertStringContainsString('balance after the backup up to ' . self::UP_TO, $rows[0]->note);
        $this->assertSame('D', $rows[0]->fuel_type);
        $this->assertSame(1, FuelInbound::count());                                // only the one after the cut-off is left
        $this->assertEquals($before, TankLevels::all()->first()->current);         // the tank holds what it held

        // the stock is valued by what is left: the inbound after the cut-off first, then the balances, newest first
        $fuel = \App\Support\Fuel\TankStockSummary::build()['fuels']['D'];
        $this->assertEquals(570, $fuel['balance']);                                // 150 + 400 + 50 - 30
        $this->assertSame(0.0, $fuel['unknown_kg']);
        $this->assertEquals(8 * 50 + 6 * 150 + 7 * 370, $fuel['value']);           // 50 at 8.00, then the balances newest first: 150 at 6.00 (09-09) and 370 of the 400 at 7.00 (09-08)
    }

    // the supplies and the outbounds by hand that are cleared take their fuel from the layer of their cost
    public function testTheClearedOutboundsTakeTheirFuelFromTheLayerOfTheirCost()
    {
        TankBalance::create(['tank_id' => $this->tank->id, 'balance' => 200, 'cost' => 5, 'balance_date' => '2026-09-01']);
        foreach ([[300, 6, '2026-09-05'], [400, 7, '2026-09-08']] as $in) {
            FuelInbound::forceCreate([
                'tank_id' => $this->tank->id, 'fuel_type' => 'D', 'amount_kg' => $in[0], 'cost' => $in[1], 'inbound_date' => $in[2], 'user_id' => 1, 'confirmed_at' => now(), 'confirmed_by' => 1,
            ]);
        }
        $this->supplement(150, '2026-09-10')->forceFill(['cost' => 6])->save();         // from the layer of 6.00
        $this->supplement(100, '2026-09-11')->forceFill(['cost' => 7])->save();
        $this->supplement(120, '2026-09-12');                                            // no cost: the oldest fuel first (the 200 at 5.00)
        $this->supplement(20, '2026-09-13')->forceFill(['cost' => 8])->save();           // a cost the tank never had: a row below zero
        $byHand = new \App\Models\Fuel\FuelOutbound;
        $byHand->forceFill(['tank_id' => $this->tank->id, 'outbound_date' => '2026-09-14', 'amount_kg' => 10, 'cost' => 7, 'user_id' => 1, 'confirmed_at' => now(), 'confirmed_by' => 1])->save();
        $before = TankLevels::all()->first()->current;                                   // 200 + 700 - 400

        $this->as('admin')->post('/backup', ['up_to' => '2026-09-15'])->assertRedirect('/backup')->assertSessionHas('status');

        $rows = TankBalance::where('tank_id', $this->tank->id)->orderBy('cost')->get()->keyBy(function ($row) {
            return number_format($row->cost, 4, '.', '');
        });
        $this->assertEquals(['5.0000' => 80, '6.0000' => 150, '7.0000' => 290, '8.0000' => -20], $rows->map(function ($row) { return (float) $row->balance; })->all());
        $this->assertEquals($before, TankLevels::all()->first()->current);
    }

    public function testFuelThatLeftMoreThanTheRecordsSayIsANegativeBalanceAndTheLevelStays()
    {
        $other = \App\Models\Fuel\Tank::forceCreate(['name' => 'T2', 'fuel_type' => 'D']);
        $nozzle = \App\Models\Fuel\Nozzle::forceCreate(['device_id' => $this->nozzle->device_id, 'number' => 2, 'tank_id' => $other->id, 'fuel_type' => 'D']);
        FuelInbound::forceCreate(['tank_id' => $other->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 4, 'inbound_date' => '2026-09-05', 'user_id' => 1, 'confirmed_at' => now(), 'confirmed_by' => 1]);
        Supplement::forceCreate([
            'fuel_type' => 'D', 'nozzle_id' => $nozzle->id, 'device_id' => $nozzle->device_id, 'out_amount_lt' => 130, 'out_amount_kg' => 130, 'preset_kg' => 130,
            'supplied_at' => '2026-09-10 11:00:00', 'supply_date' => '2026-09-10', 'confirmed_at' => now(), 'confirmed_by' => 1,
        ]);
        $before = TankLevels::all()->firstWhere('tank.id', $other->id)->current;                // -30
        $this->assertEquals(-30, $before);

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup')->assertSessionHas('status');

        $rows = TankBalance::where('tank_id', $other->id)->get();
        $this->assertCount(1, $rows);
        $this->assertEquals(-30, $rows[0]->balance);
        $this->assertNull($rows[0]->cost);                                                       // it left with no cost: no cost is known for what is missing
        $this->assertEquals($before, TankLevels::all()->firstWhere('tank.id', $other->id)->current);

        // a second backup finds the negative row and takes it from the layers it gets: nothing is lost or counted twice
        FuelInbound::forceCreate(['tank_id' => $other->id, 'fuel_type' => 'D', 'amount_kg' => 80, 'cost' => 5, 'inbound_date' => '2026-09-12', 'user_id' => 1, 'confirmed_at' => now(), 'confirmed_by' => 1]);
        $this->assertEquals(50, TankLevels::all()->firstWhere('tank.id', $other->id)->current);
        $this->post('/backup', ['up_to' => '2026-09-14'])->assertRedirect('/backup')->assertSessionHas('status');
        $rows = TankBalance::where('tank_id', $other->id)->get();
        $this->assertCount(1, $rows);
        $this->assertEquals([5, 50], [$rows[0]->cost, $rows[0]->balance]);
        $this->assertEquals(50, TankLevels::all()->firstWhere('tank.id', $other->id)->current);
    }

    public function testDeletedSalesAndWhatWasCancelledWithThemAreNotExported()
    {
        $kept = $this->sale('2026-09-10');
        $gone = $this->sale('2026-09-10');
        $gone->delete();

        $cancelledDebt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => $gone->id, 'amount' => 20, 'payment_method' => 'cash', 'user_id' => 1]);
        $cancelledDebt->delete();
        $openingDebt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => null, 'amount' => 7, 'payment_method' => 'cash', 'user_id' => 1]);
        $openingDebt->delete(); // a deleted opening balance has no sale: it is kept like any other row
        $liveDebt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => $kept->id, 'amount' => 20, 'payment_method' => 'cash', 'user_id' => 1]);

        $cancelledLoan = TicketLoan::forceCreate(['loaned_at' => now(), 'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 5, 'sale_id' => $gone->id, 'user_id' => 1]);
        $cancelledLoan->delete();

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/backups/test-exports/' . Backup::first()->file)));
        $lines = function ($table) use ($zip) {
            return array_filter(explode("\n", $zip->getFromName($table . '.jsonl')));
        };

        $sales = implode("\n", $lines('sales'));
        $this->assertStringContainsString($kept->id, $sales);
        $this->assertStringNotContainsString($gone->id, $sales);

        $debts = implode("\n", $lines('debts'));
        $this->assertStringNotContainsString($cancelledDebt->id, $debts);
        $this->assertStringContainsString($openingDebt->id, $debts);
        $this->assertStringContainsString($liveDebt->id, $debts);

        $this->assertSame([], $lines('ticket_loans')); // the only loan was cancelled with its sale
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(1, $manifest['rows']['sales']);
        $this->assertSame(2, $manifest['rows']['debts']);
        $zip->close();
    }

    public function testSyncIsOnlyBusyWhileTheBackupRuns()
    {
        $this->assertFalse(\App\Support\Admin\BackupRun::isRunning());

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup');
        $this->assertFalse(\App\Support\Admin\BackupRun::isRunning()); // cleared when it finished

        config(['backup.dump_before_clear' => true, 'backup.mysqldump' => 'Z:\\no\\such\\mysqldump.exe']);
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');
        $this->assertFalse(\App\Support\Admin\BackupRun::isRunning()); // and also when it failed
    }

    public function testDebtOfAClearedSaleKeepsItsDateOnThePaymentsPage()
    {
        $this->scenario();
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);

        $this->get('/payments')->assertOk()->assertDontSee(__('report.opening_balance'));
    }

    public function testTodayCannotBeCleared()
    {
        $this->as('admin')->post('/backup', ['up_to' => today()->toDateString()])->assertSessionHasErrors('up_to');
        $this->assertSame(0, Backup::count());
    }

    public function testAFailedSafetyDumpClearsNothing()
    {
        config(['backup.dump_before_clear' => true, 'backup.mysqldump' => 'Z:\\no\\such\\mysqldump.exe']);
        $this->sale('2026-09-10');

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');

        $this->assertSame(1, Sale::count());
        $this->assertSame(0, Backup::count());
    }

    public function testDownloadReturnsTheZip()
    {
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);

        $this->get('/backup/' . Backup::first()->id . '/download')->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=' . Backup::first()->file);
    }

    public function testOnlyAdminCanBackUp()
    {
        foreach (['manager', 'accountant', 'assistant', 'cashier'] as $role) {
            $this->as($role)->get('/backup')->assertForbidden();
            $this->as($role)->post('/backup', ['up_to' => self::UP_TO])->assertForbidden();
        }
    }

    public function testImportRefusesOutsideTheBackupServer()
    {
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);

        $this->artisan('backup:import', ['file' => storage_path('app/backups/test-exports/' . Backup::first()->file)])
            ->assertExitCode(1);
    }

    public function testTheItemsOfAClearedSaleAreExportedAndClearedWithIt()
    {
        $old = $this->sale('2026-09-10');
        $late = $this->sale('2026-09-20');       // after the cut-off
        $deleted = $this->sale('2026-09-12');
        foreach ([$old, $late, $deleted] as $sale) {
            \App\Models\Sales\SaleItem::forceCreate(['sale_id' => $sale->id, 'spec' => 0, 'start_serial' => 1, 'end_serial' => 2, 'quantity' => 2, 'amount_kg' => 10]);
        }
        $deleted->delete();

        $this->assertSame(2, $this->as('admin')->get('/backup?up_to=' . self::UP_TO)->viewData('preview')['sale_items']); // the old sale's and the deleted one's

        $this->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup')->assertSessionHas('status');

        $this->assertSame([$late->id], \App\Models\Sales\SaleItem::pluck('sale_id')->all()); // the cleared sales took their items along
        $backup = Backup::first();
        $this->assertSame(2, $backup->cleared['sale_items']); // the old sale's item and the deleted sale's
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/backups/test-exports/' . $backup->file)));
        $this->assertSame(2, (int) json_decode($zip->getFromName('manifest.json'), true)['rows']['sale_items']); // old and late; a deleted sale's are not backed up
    }

    public function testAnExportOfSuppliesMadeBeforeTheSupplyDateRenameStillImports()
    {
        $file = tempnam(sys_get_temp_dir(), 'bk') . '.zip';
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('supplements.jsonl', json_encode([
            'id' => '22222222-2222-2222-2222-222222222222', 'fuel_type' => 'D', 'nozzle_id' => $this->nozzle->id, 'liters_dispensed' => '10.00', 'amount_kg' => '8.00',
            'preset_kg' => '10.00', 'manual_reading' => 0, 'supplied_at' => '2026-10-01 10:00:00', 'date' => '2026-10-01',
        ]) . "
");
        $zip->addFromString('manifest.json', json_encode(['rows' => ['supplements' => 1], 'up_to_date' => '2026-10-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $supplement = Supplement::find('22222222-2222-2222-2222-222222222222');
        $this->assertSame('2026-10-01', substr((string) $supplement->supply_date, 0, 10)); // its old `date`
        $this->assertSame('auto', $supplement->mode);                                         // and the default for what the export did not have
    }

    public function testAnExportOfRemaindersMadeBeforeTheirFieldsChangedStillImports()
    {
        $supplement = $this->supplement(10, '2026-10-01');
        $file = tempnam(sys_get_temp_dir(), 'bk') . '.zip';
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('remainders.jsonl', json_encode([
            'id' => '33333333-3333-3333-3333-333333333333', 'supplement_id' => $supplement->id, 'ticket_barcode' => '0000025500000021', 'fuel_type' => 'D',
            'amount_kg' => '2.00', 'remainder_at' => '2026-10-01 11:05:00', 'date' => '2026-10-01', 'created_at' => '2026-10-02 00:00:00',
        ]) . "
");
        $zip->addFromString('manifest.json', json_encode(['rows' => ['remainders' => 1], 'up_to_date' => '2026-10-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $remainder = \App\Models\Supplements\Remainder::find('33333333-3333-3333-3333-333333333333');
        $this->assertSame('2026-10-01', substr((string) $remainder->remainder_date, 0, 10));   // its old `date`
        $this->assertNull($remainder->complete_date);
    }

    public function testImportMergesExportsWithoutDuplicates()
    {
        $this->scenario();
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);
        $this->assertSame(1, Sale::withTrashed()->count());
        $file = storage_path('app/backups/test-exports/' . Backup::first()->file);

        // Same database standing in for the backup server: the cleared rows come back from the
        // export, the rows still here are updated in place, and a second import changes nothing.
        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $this->assertSame(3, Sale::withTrashed()->count()); // the deleted sale was cleared but, being deleted, not exported
        $this->assertSame(0, Sale::onlyTrashed()->count());
        $this->assertSame(4, Ticket::count());
        $this->assertSame(1, Debt::count());
    }

    public function testBackupServerTankLevelsMatchLiveAfterImport()
    {
        $this->scenario();
        $before = TankLevels::all()->first()->current;
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);
        $file = storage_path('app/backups/test-exports/' . Backup::first()->file);

        // Backup server: has every row and the go-live opening, and no backups history of its own.
        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);
        Backup::query()->delete();
        // (this test is one database, the backup server is another: the tank balances are not in the export, the server has the inbounds and outbounds themselves and its
        // opening balance of the tank is entered there once by hand)
        \App\Models\Fuel\TankBalance::query()->delete();
        \App\Models\Fuel\TankBalance::create(['tank_id' => $this->tank->id, 'balance' => 1000, 'cost' => null, 'balance_date' => '2026-09-01']);

        $this->assertEquals($before, TankLevels::all()->first()->current);
    }

    public function testTheTankBalancesAreNeverInTheExport()
    {
        $this->scenario();
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHas('status');
        $this->assertSame(2, \App\Models\Fuel\TankBalance::where('source', 'backup')->count());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/backups/test-exports/' . Backup::first()->file)));
        $this->assertFalse($zip->getFromName('tank_balances.jsonl'));              // neither the one entered by hand nor the ones the backup wrote
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();
        $this->assertArrayNotHasKey('tank_balances', $manifest['rows']);
    }

    public function testClearingIsRefusedOnTheBackupServer()
    {
        config(['backup.is_backup_server' => true]);
        $this->sale('2026-09-10');

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');
        $this->assertSame(1, Sale::count());
    }

    public function testSpotSerialsAreNotReusedAfterUsedTicketsAreCleared()
    {
        Ticket::forceCreate([
            'barcode' => '0000021000000005', 'fuel_type' => 'D', 'spec' => null, 'code' => '21', 'serial' => 5, 'amount_kg' => 10,
            'status' => config('ticket_statuses.supply'), 'supply_date' => '2026-09-10',
        ]);
        $this->as('admin')->post('/ticket-sales', [
            'sale_type' => 'spot', 'fuel_type' => 'D', 'amount_kg' => 3, 'customer_id' => $this->customer->id,
            'price' => 2, 'usage' => 'sale', 'paid_amount' => 6,
        ]); // serial 6

        Ticket::where('serial', 6)->update(['status' => config('ticket_statuses.supply'), 'supply_date' => '2026-09-11']);
        $this->post('/backup', ['up_to' => self::UP_TO]);
        $this->assertSame(0, Ticket::count());

        $this->post('/ticket-sales', [
            'sale_type' => 'spot', 'fuel_type' => 'D', 'amount_kg' => 3, 'customer_id' => $this->customer->id,
            'price' => 2, 'usage' => 'sale', 'paid_amount' => 6,
        ]);
        $this->assertSame('0000021000000007', Ticket::first()->barcode);
    }

    // the tickets made for a card are what its balance (and the card summary, and a rebuilt ledger) are worked out from: a backup keeps them, like the card's sales
    // the sale of a debt goes with the backup: the debt keeps the day of the sale (the sale was dated back, so its own time is another day)
    public function testADebtKeepsTheDayOfItsClearedSale()
    {
        $sale = $this->sale('2026-09-03');
        $debt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => $sale->id, 'amount' => 50, 'payment_method' => 'cash', 'user_id' => 1]);
        $debt->forceFill(['created_at' => '2026-09-10 09:00:00'])->save();      // entered a week after the day of the sale
        $this->assertSame('2026-09-03', substr((string) $debt->fresh()->display_date, 0, 10));

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasNoErrors();

        $this->assertNull(Sale::find($sale->id));
        $this->assertSame('2026-09-03', substr((string) $debt->fresh()->display_date, 0, 10));      // not the 10th
    }

    public function testTheTicketsMadeForACardAreKeptByTheBackup()
    {
        $card = \App\Models\Cards\Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + \App\Support\Cards\CardPin::make('1234'));
        Sale::forceCreate([
            'card_id' => $card->id, 'sale_type' => 'card', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 100, 'payment_method' => 'cash', 'total_amount' => 200,
            'paid_amount' => 200, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => '2026-09-01', 'user_id' => 1, 'confirmed_at' => now(),
        ]);
        Ticket::forceCreate([
            'barcode' => '0000025000000001', 'fuel_type' => 'D', 'spec' => null, 'code' => '25', 'serial' => 1, 'amount_kg' => 30, 'card_id' => $card->id,
            'status' => config('ticket_statuses.supply'), 'sale_date' => '2026-09-05 10:00:00', 'supply_date' => '2026-09-05 10:05:00',
        ]);
        // a spot ticket of the same day, which is cleared
        Ticket::forceCreate([
            'barcode' => '0000021000000002', 'fuel_type' => 'D', 'spec' => null, 'code' => '21', 'serial' => 2, 'amount_kg' => 10,
            'status' => config('ticket_statuses.supply'), 'supply_date' => '2026-09-05 11:00:00',
        ]);
        $this->assertEquals(70, $card->fresh()->balance());
        $this->assertEquals(70, $card->fresh()->balanceFromRecords());

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasNoErrors();

        $this->assertNotNull(Ticket::find('0000025000000001'));        // the card's ticket stays
        $this->assertNull(Ticket::find('0000021000000002'));           // the spot ticket goes
        $this->assertEquals(70, $card->fresh()->balance());
        $this->assertEquals(70, $card->fresh()->balanceFromRecords());      // the records still agree with the ledger
    }

    public function testDailyDumpWritesAFileAndPrunesOldOnes()
    {
        $dir = storage_path('app/backups/test-daily');
        mkdir($dir, 0755, true);
        touch($dir . '/old.sql.gz', now()->subDays(10)->getTimestamp());

        $this->artisan('backup:dump')->assertExitCode(0);

        $files = glob($dir . '/*.sql.gz');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('CREATE TABLE', gzdecode(file_get_contents($files[0])));
    }

    public function testTheNightlySafetyNetOnlyDumpsWhenThereIsNoRecentDump()
    {
        $dir = storage_path('app/backups/test-daily');
        mkdir($dir, 0755, true);
        touch($dir . '/recent.sql.gz', now()->subHours(3)->getTimestamp()); // the dump of the confirmation, this morning

        $this->artisan('backup:dump --if-older-than=20 --note=safety-net')->assertExitCode(0);
        $this->assertCount(1, glob($dir . '/*.sql.gz')); // nothing new

        touch($dir . '/recent.sql.gz', now()->subHours(30)->getTimestamp()); // nobody confirmed yesterday
        $this->artisan('backup:dump --if-older-than=20 --note=safety-net')->assertExitCode(0);
        $this->assertCount(2, glob($dir . '/*.sql.gz')); // the old one is still within keep_days, a new one was written
    }

    public function testTheJobAfterAConfirmationWritesTheDump()
    {
        $dir = storage_path('app/backups/test-daily');

        (new \App\Jobs\RunBackupDump('confirmed 2026-10-01'))->handle();

        $files = glob($dir . '/*.sql.gz');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('CREATE TABLE', gzdecode(file_get_contents($files[0])));
    }

    public function testAnExportMadeBeforeTheRenameStillImports()
    {
        // an old export: the column is still called checkout_date in its rows
        $dir = storage_path('app/backups/test-exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/old-export.zip';
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('sales.jsonl', json_encode([
            'id' => '11111111-1111-1111-1111-111111111111', 'sold_at' => '2026-10-01 10:00:00', 'customer_id' => null, 'sale_type' => 'spot',
            'fuel_type' => 'D', 'price' => '2.00', 'amount_kg' => '10.00', 'payment_method' => 'cash', 'total_amount' => '20.00',
            'paid_amount' => '20.00', 'unpaid_amount' => '0.00', 'usage' => 'sale', 'checkout_date' => '2026-10-01', 'user_id' => 1,
        ]) . "
");
        $zip->addFromString('manifest.json', json_encode(['rows' => ['sales' => 1], 'up_to_date' => '2026-10-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $this->assertSame('2026-10-01', substr((string) Sale::find('11111111-1111-1111-1111-111111111111')->sale_date, 0, 10));
    }

    // tank balances in an export (an older one has them) are not imported: the backup server's opening balances are entered by hand
    public function testTankBalancesInAnExportAreNotImported()
    {
        $tank = \App\Models\Fuel\Tank::forceCreate(['name' => 'T9', 'fuel_type' => 'D']);
        $dir = storage_path('app/backups/test-exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/old-tank-export.zip';
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (['initial_tank_balances', 'tank_balances'] as $name) {
            $zip->addFromString($name . '.jsonl', json_encode([
                'id' => 1, 'tank_id' => $tank->id, 'balance' => '750.00', 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00',
            ]) . "\n");
        }
        $zip->addFromString('manifest.json', json_encode(['rows' => ['initial_tank_balances' => 1, 'tank_balances' => 1], 'up_to_date' => '2026-10-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $this->assertSame(0, \App\Models\Fuel\TankBalance::count());
    }

    public function testAnExportOfFuelInboundsMadeWhenTheyHadTheDefaultFlagStillImports()
    {
        $dir = storage_path('app/backups/test-exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/old-inbound-export.zip';
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('fuel_inbounds.jsonl', json_encode([
            'id' => 77, 'tank_id' => $this->tank->id, 'fuel_type' => 'D', 'amount_kg' => '500.00', 'cost' => '6.5000', 'is_default' => 1, 'default_from' => '2026-09-01 08:00:00',
            'default_until' => null, 'inbound_date' => '2026-09-01', 'user_id' => 1, 'created_at' => '2026-09-01 08:00:00', 'updated_at' => '2026-09-01 08:00:00',
        ]) . "\n");
        $zip->addFromString('manifest.json', json_encode(['rows' => ['fuel_inbounds' => 1], 'up_to_date' => '2026-09-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $this->assertEquals(500, FuelInbound::find(77)->amount_kg);
    }

    public function testAnExportWithTheUserOfASupplyStillImports()
    {
        // an export made while supplies still had a user_id
        $dir = storage_path('app/backups/test-exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/old-supply-export.zip';
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('supplements.jsonl', json_encode([
            'id' => '22222222-2222-2222-2222-222222222222', 'fuel_type' => 'D', 'nozzle_id' => $this->nozzle->id, 'device_id' => $this->nozzle->device_id,
            'liters_dispensed' => '12.00', 'amount_kg' => '10.00', 'preset_kg' => '10.00', 'supplied_at' => '2026-10-01 11:00:00',
            'date' => '2026-10-01', 'user_id' => 1,
        ]) . "
");
        $zip->addFromString('manifest.json', json_encode(['rows' => ['supplements' => 1], 'up_to_date' => '2026-10-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $this->assertEquals(10, Supplement::find('22222222-2222-2222-2222-222222222222')->out_amount_kg);
    }

    public function testNothingIsClearedWhileASaleOrSupplementIsNotConfirmed()
    {
        $this->sale('2026-09-10');
        $unconfirmedSale = $this->sale('2026-09-11', false);
        $this->supplement(30, '2026-09-12', false);

        $response = $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);

        $response->assertSessionHasErrors('up_to');
        $this->assertStringContainsString('1 sales and 1 supplements', session('errors')->first('up_to'));
        $this->assertStringContainsString('2026-09-11', session('errors')->first('up_to'));
        $this->assertSame(2, Sale::count());
        $this->assertSame(1, Supplement::count());
        $this->assertSame(0, Backup::count());
        $this->get('/backup?up_to=' . self::UP_TO)->assertOk()->assertSee('not confirmed yet');

        // confirmed, the backup runs
        $unconfirmedSale->forceFill(['confirmed_at' => now()])->save();
        Supplement::query()->update(['confirmed_at' => now()]);
        $this->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasNoErrors();
        $this->assertSame(0, Sale::count());
        $this->assertSame(1, Backup::count());
    }

    public function testADeletedOrLaterRecordNeverBlocksABackup()
    {
        $this->sale('2026-09-10');
        $this->sale('2026-09-12', false)->delete();   // deleted: nothing waits for it
        $this->sale('2026-09-20', false);             // after the date
        $this->supplement(30, '2026-09-20', false);

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasNoErrors();

        $this->assertSame(1, Backup::count());
        $this->assertSame(1, Sale::count());          // only the later one is left
    }

    public function testNothingIsClearedWhileATerminalHasNotUploadedSinceThatBusinessDay()
    {
        $this->sale('2026-09-10');
        $device = Device::where('name', 'Pump 1')->first();
        $device->forceFill(['initial_sync_done' => true, 'last_synced_at' => '2026-09-16 08:59:00'])->save();   // the day of 2026-09-15 ends at 09:00 of the 16th

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');
        $this->assertStringContainsString('Pump 1 has not uploaded since 2026-09-16 09:00', session('errors')->first('up_to'));
        $this->assertSame(0, Backup::count());

        $device->forceFill(['last_synced_at' => '2026-09-16 09:01:00'])->save();
        $this->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasNoErrors();
        $this->assertSame(1, Backup::count());
    }

    public function testNothingIsClearedWhileTheTicketStockOfThoseDaysIsNotConfirmed()
    {
        $this->sale('2026-09-10');
        $inbound = \App\Models\Tickets\TicketInbound::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'quantity' => 10, 'inbound_date' => '2026-09-08', 'user_id' => 1]);
        $batch = \App\Models\Tickets\TicketPublish::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'start_serial' => 1, 'end_serial' => 5, 'count' => 5, 'date' => '2026-09-12', 'user_id' => 1]);
        \App\Models\Tickets\TicketInbound::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'quantity' => 4, 'inbound_date' => '2026-09-20', 'user_id' => 1]);   // after the date: no matter

        $response = $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);

        $response->assertSessionHasErrors('up_to');
        $this->assertStringContainsString('2 ticket inbounds and publishes', session('errors')->first('up_to'));
        $this->assertStringContainsString('2026-09-08', session('errors')->first('up_to'));
        $this->assertSame(1, Sale::count());
        $this->assertSame(0, Backup::count());

        // one confirmed, one still waits
        $inbound->forceFill(['confirmed_at' => now(), 'confirmed_by' => 1])->save();
        $this->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');
        $this->assertStringContainsString('1 ticket inbounds and publishes', session('errors')->first('up_to'));

        // confirmed, the backup runs; the ticket stock is not cleared (its balance is computed from it)
        $batch->forceFill(['confirmed_at' => now(), 'confirmed_by' => 1])->save();
        $this->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasNoErrors();
        $this->assertSame(0, Sale::count());
        $this->assertSame(1, Backup::count());
        $this->assertSame(2, \App\Models\Tickets\TicketInbound::count());
        $this->assertSame(1, \App\Models\Tickets\TicketPublish::count());
    }

    public function testNothingIsClearedWhileTheDebtPaymentsAndFuelInboundsOfThoseDaysAreNotConfirmed()
    {
        $this->sale('2026-09-10');
        $payment = DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => null, 'amount' => 5, 'payment_method' => 'cash', 'paid_date' => '2026-09-11', 'date' => '2026-09-11', 'user_id' => 1,
        ]);
        $inbound = FuelInbound::forceCreate(['tank_id' => $this->tank->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 1, 'inbound_date' => '2026-09-08', 'user_id' => 1]);
        FuelInbound::forceCreate(['tank_id' => $this->tank->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 1, 'inbound_date' => '2026-09-20', 'user_id' => 1]);   // after the date: no matter

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');
        $this->assertStringContainsString('2 fuel inbounds and outbounds, debt payments and ticket settlements', session('errors')->first('up_to'));
        $this->assertStringContainsString('2026-09-08', session('errors')->first('up_to'));
        $this->assertSame(1, Sale::count());
        $this->assertSame(0, Backup::count());

        $inbound->forceFill(['confirmed_at' => now(), 'confirmed_by' => 1])->save();
        $this->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');
        $this->assertStringContainsString('1 fuel inbounds and outbounds, debt payments and ticket settlements', session('errors')->first('up_to'));

        $payment->forceFill(['confirmed_at' => now(), 'confirmed_by' => 1])->save();

        // the outbound made by hand and the settlement of a ticket loan wait too (the outbound of a supply follows the supply)
        $outbound = new \App\Models\Fuel\FuelOutbound;
        $outbound->forceFill(['tank_id' => $this->tank->id, 'outbound_date' => '2026-09-09', 'amount_kg' => 10, 'user_id' => 1])->save();
        $loan = TicketLoan::forceCreate(['loaned_at' => '2026-09-09 09:00:00', 'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 10, 'user_id' => 1]);
        $settlement = TicketLoanSettlement::forceCreate(['loan_id' => $loan->id, 'ticket_barcode' => '0000006000000009', 'amount_kg' => 5, 'settled_at' => '2026-09-09 10:00:00', 'date' => '2026-09-09', 'user_id' => 1]);
        $this->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');
        $this->assertStringContainsString('2 fuel inbounds and outbounds', session('errors')->first('up_to'));
        $this->assertStringContainsString('2026-09-09', session('errors')->first('up_to'));
        $outbound->forceFill(['confirmed_at' => now(), 'confirmed_by' => 1])->save();
        $settlement->forceFill(['confirmed_at' => now(), 'confirmed_by' => 1])->save();

        $this->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasNoErrors();
        $this->assertSame(0, Sale::count());
        $this->assertSame(1, Backup::count());
    }

}
