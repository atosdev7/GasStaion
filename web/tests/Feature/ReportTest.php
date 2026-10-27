<?php

namespace Tests\Feature;

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
use App\Models\Tickets\TicketInbound;
use App\Models\Debts\TicketLoan;
use App\Models\Tickets\TicketPublish;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::forceCreate(['name' => 'ACME']);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function sale(array $attrs)
    {
        return Sale::forceCreate($attrs + [
            'sale_date' => '2026-09-10', 'customer_id' => $this->customer->id, 'sale_type' => 'ticket',
            'fuel_type' => 'D', 'price' => 2, 'cost' => 1.5, 'amount_kg' => 10, 'payment_method' => 'cash',
            'total_amount' => 20, 'paid_amount' => 20, 'unpaid_amount' => 0, 'usage' => 'sale', 'user_id' => 1,
        ]);
    }

    private function nozzle(Tank $tank)
    {
        $device = Device::forceCreate(['name' => 'Pump ' . $tank->id]);

        return Nozzle::forceCreate(['device_id' => $device->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => $tank->fuel_type]);
    }

    private function supplement(Nozzle $nozzle, $kg, $at = '2026-09-10 12:00:00')
    {
        return Supplement::forceCreate([
            'fuel_type' => $nozzle->fuel_type, 'nozzle_id' => $nozzle->id, 'device_id' => $nozzle->device_id,
            'out_amount_lt' => $kg / 0.89, 'out_amount_kg' => $kg, 'preset_kg' => $kg, 'supplied_at' => $at,
        ]);
    }

    private const PERIOD = '?from=2026-09-01&to=2026-09-30';

    public function testSalesReportSumsOnlyThePeriodAndSkipsDeleted()
    {
        $this->sale([]);
        $this->sale(['amount_kg' => 5, 'total_amount' => 10, 'paid_amount' => 4, 'unpaid_amount' => 6]);
        $this->sale(['sale_date' => '2026-08-31']); // before the period
        $this->sale([])->delete();

        $response = $this->as('accountant')->get('/reports/sales' . self::PERIOD)->assertOk();
        $row = $response->viewData('summary')->first();

        $this->assertCount(1, $response->viewData('summary'));
        $this->assertEquals(2, $row->sales);
        $this->assertEquals(15, $row->kg);
        $this->assertEquals(30, $row->total);
        $this->assertEquals(24, $row->paid);
        $this->assertEquals(6, $row->unpaid);
        $this->assertEquals('2026-09-10', $response->viewData('daily')->first()->day);
    }

    public function testCashReportCombinesSalePaymentsAndDebtPaymentsPerDayAndMethod()
    {
        $this->sale(['paid_amount' => 20]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'amount' => 30, 'payment_method' => 'bank', 'paid_date' => '2026-09-10', 'user_id' => 1,
        ]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'amount' => 5, 'payment_method' => 'cash', 'paid_date' => '2026-09-10', 'user_id' => 1,
        ]);

        $rows = $this->as('accountant')->get('/reports/cash' . self::PERIOD)->assertOk()->viewData('rows');

        $bank = $rows->firstWhere('method', 'bank');
        $cash = $rows->firstWhere('method', 'cash');
        $this->assertEquals(0, $bank->sales);
        $this->assertEquals(30, $bank->payments);
        $this->assertEquals(20, $cash->sales);
        $this->assertEquals(5, $cash->payments);
    }

    public function testProfitIsRevenueMinusCostAndFlagsMissingCost()
    {
        $this->sale([]); // 10 kg, revenue 20, cost 1.5/kg -> 15
        $this->sale(['cost' => null]); // revenue 20, cost unknown

        $row = $this->as('accountant')->get('/reports/profit' . self::PERIOD)->assertOk()
            ->assertSee(__('report.missing_cost_hint'))->viewData('rows')->first();

        $this->assertEquals(40, $row->revenue);
        $this->assertEquals(15, $row->cost_total);
        $this->assertEquals(25, $row->profit);
        $this->assertEquals(1, $row->missing_cost);
    }

    public function testCustomerStatementBalances()
    {
        $debt = Debt::forceCreate(['customer_id' => $this->customer->id, 'amount' => 100, 'payment_method' => 'cash', 'user_id' => 1]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 40, 'payment_method' => 'cash', 'paid_date' => '2026-09-12', 'user_id' => 1,
        ]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'amount' => 10, 'payment_method' => 'cash', 'paid_date' => '2026-09-12', 'user_id' => 1,
        ]);
        TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 50, 'settled_amount_kg' => 20, 'user_id' => 1,
        ]);
        $this->sale([]);

        $response = $this->as('accountant')->get('/reports/customer' . self::PERIOD . '&customer_id=' . $this->customer->id)
            ->assertOk()->assertSee('ACME');

        $this->assertEquals(60, $response->viewData('debts')->sum('balance'));
        $this->assertEquals(10, $response->viewData('unallocated'));
        $this->assertEquals(30, $response->viewData('loans')->sum('balance_kg'));
        $this->assertCount(1, $response->viewData('sales'));
        $this->assertCount(2, $response->viewData('payments'));
    }

    public function testCustomerStatementWithoutACustomerShowsOnlyThePicker()
    {
        $this->as('accountant')->get('/reports/customer')->assertOk()
            ->assertSee(__('report.choose_customer'))->assertDontSee(__('report.debts'));
    }

    public function testTicketStockCountsEachStatusAndSpotTicketsSeparately()
    {
        TicketInbound::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'quantity' => 10, 'inbound_date' => '2026-09-01', 'user_id' => 1]);
        TicketPublish::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'start_serial' => 1, 'end_serial' => 4, 'count' => 4, 'user_id' => 1]);
        foreach (['publish', 'sale', 'supply', 'restrict'] as $i => $status) {
            Ticket::forceCreate([
                'barcode' => '000000600000000' . ($i + 1), 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => $i + 1,
                'status' => config('ticket_statuses.' . $status),
            ]);
        }
        Ticket::forceCreate([
            'barcode' => '0000021000000001', 'fuel_type' => 'D', 'spec' => null, 'code' => '21', 'serial' => 1,
            'amount_kg' => 12, 'status' => config('ticket_statuses.sale'),
        ]);

        $rows = $this->as('accountant')->get('/reports/tickets')->assertOk()->assertSee(__('report.direct'))->viewData('rows');

        $press = $rows->first(function ($r) { return $r->spec === 0; });
        $this->assertSame(10, $press->received);
        $this->assertSame(6, $press->blank);
        $this->assertSame([1, 1, 1, 1], [$press->publish, $press->sale, $press->supply, $press->restrict]);

        $direct = $rows->first(function ($r) { return $r->spec === null; });
        $this->assertSame(1, $direct->sale);
        $this->assertNull($direct->received);
    }

    // the old paper tickets nobody has used have no row: they are counted in their own column, so the columns add up to what was published
    public function testTicketStockCountsTheOldPaperTicketsThatHaveNoRow()
    {
        \App\Models\Admin\Setting::updateOrCreate(['key' => 'go_live_date'], ['value' => '2026-10-01']);
        TicketInbound::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'quantity' => 100, 'inbound_date' => '2026-09-01', 'user_id' => 1]);
        $old = TicketPublish::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'start_serial' => 1, 'end_serial' => 20, 'count' => 20, 'date' => '2026-09-05', 'user_id' => 1]);
        TicketPublish::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'start_serial' => 21, 'end_serial' => 30, 'count' => 10, 'date' => '2026-10-05', 'user_id' => 1]);
        foreach ([1, 2] as $serial) {          // two old tickets were used: their rows exist
            Ticket::forceCreate(['barcode' => '00000060000000' . sprintf('%02d', $serial), 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => $serial,
                'publish_id' => $old->id, 'status' => config('ticket_statuses.supply')]);
        }

        $press = $this->as('accountant')->get('/reports/tickets')->assertOk()->viewData('rows')->first(function ($r) { return $r->spec === 0; });
        $this->assertSame(18, $press->old);          // 20 old, 2 used
        $this->assertSame(70, $press->blank);        // 100 received, 30 published
    }

    public function testTankStockIsOpeningPlusInboundMinusSupplied()
    {
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => 2000]);
        TankBalance::create(['tank_id' => $tank->id, 'balance' => 1000]);
        FuelInbound::forceCreate(['tank_id' => $tank->id, 'fuel_type' => 'D', 'amount_kg' => 500, 'cost' => 1, 'inbound_date' => '2026-09-02', 'user_id' => 1]);
        $this->supplement($this->nozzle($tank), 100);

        $row = $this->as('accountant')->get('/reports/tanks')->assertOk()->viewData('rows')->first();

        $this->assertEquals(1400, $row->current);
        $this->assertEquals(70.0, $row->percent);
    }

    // what a tank holds at each cost it was bought at, for those who may see costs
    public function testTankStockShowsTheFuelOfEachTankByCost()
    {
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => 2000]);
        TankBalance::create(['tank_id' => $tank->id, 'balance' => 1000, 'cost' => 5]);
        FuelInbound::forceCreate(['tank_id' => $tank->id, 'fuel_type' => 'D', 'amount_kg' => 500, 'cost' => 6, 'inbound_date' => '2026-09-02', 'user_id' => 1]);
        $this->supplement($this->nozzle($tank), 100)->forceFill(['cost' => 5])->save();

        $page = $this->as('accountant')->get('/reports/tanks')->assertOk()->assertSee('tank-layers', false);
        $layers = collect($page->viewData('layers')[$tank->id])->keyBy(function ($layer) {
            return number_format($layer['cost'], 4, '.', '');
        });
        $this->assertEquals(900, $layers['5.0000']['kg']);          // 1000 - 100 at its cost
        $this->assertEquals(500, $layers['6.0000']['kg']);

        // the export has the layers as a second sheet
        $file = $this->get('/reports/tanks?export=xls')->assertOk();
        $zip = new \ZipArchive();
        $zip->open($file->baseResponse->getFile()->getPathname());
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet2.xml'));
        $zip->close();

        // a user who may not see costs has no layers (nobody with the reports does today, so the permission is taken away for the test)
        config(['permissions.roles.accountant' => array_values(array_diff(config('permissions.roles.accountant'), ['costs.view']))]);
        $this->as('accountant')->get('/reports/tanks')->assertOk()->assertDontSee('tank-layers', false);
    }

    public function testReportsGroupByTheDateField()
    {
        // the supply (02:00 / 03:00 of the 11th) belongs to the business day of the 10th, which its date field says
        $this->sale(['sale_date' => '2026-09-10']);
        $this->sale(['sale_date' => '2026-09-12']);
        $this->sale(['sale_date' => '2026-08-31']); // belongs to the day before the period
        $nozzle = $this->nozzle(Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']));
        $this->supplement($nozzle, 10, '2026-09-11 03:00:00')->forceFill(['supply_date' => '2026-09-10'])->save();

        $daily = $this->as('accountant')->get('/reports/sales' . self::PERIOD)->assertOk()->viewData('daily');
        $this->assertEquals(['2026-09-10', '2026-09-12'], $daily->pluck('day')->all());

        $cash = $this->as('accountant')->get('/reports/cash' . self::PERIOD)->assertOk()->viewData('rows');
        $this->assertEquals(['2026-09-10', '2026-09-12'], $cash->pluck('day')->all());

        $supply = $this->as('accountant')->get('/reports/supply' . self::PERIOD)->assertOk()->viewData('rows');
        $this->assertEquals('2026-09-10', $supply->first()->day);
    }

    public function testSupplyReportGroupsByDayAndNozzle()
    {
        $nozzle = $this->nozzle(Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']));
        $this->supplement($nozzle, 10);
        $this->supplement($nozzle, 5);
        $this->supplement($nozzle, 99, '2026-10-01 08:00:00'); // after the period

        $rows = $this->as('accountant')->get('/reports/supply' . self::PERIOD)->assertOk()->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertEquals(2, $rows->first()->supplies);
        $this->assertEquals(15, $rows->first()->kg);
    }

    // the total of each fuel type under the table and as a second sheet of the export
    public function testSupplyReportTotalsEachFuelType()
    {
        $diesel = $this->nozzle(Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']));
        $gasoline = $this->nozzle(Tank::forceCreate(['name' => 'T2', 'fuel_type' => 'A']));
        $this->supplement($diesel, 10);
        $this->supplement($diesel, 5);
        $this->supplement($gasoline, 7);
        $this->supplement($gasoline, 99, '2026-10-01 08:00:00');      // after the period

        $page = $this->as('accountant')->get('/reports/supply' . self::PERIOD)->assertOk()->assertSee('supply-by-fuel', false);
        $byFuel = $page->viewData('byFuel')->keyBy('fuel_type');
        $this->assertEquals([2, 15], [$byFuel['D']->supplies, $byFuel['D']->kg]);
        $this->assertEquals([1, 7], [$byFuel['A']->supplies, $byFuel['A']->kg]);

        $file = $this->get('/reports/supply' . self::PERIOD . '&export=xls')->assertOk();
        $zip = new \ZipArchive();
        $zip->open($file->baseResponse->getFile()->getPathname());
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet2.xml'));
        $zip->close();
    }

    // the numbers to decide an order: the tanks, what is owed on tickets sold and not supplied (here and on paper), the free fuel, the speed of supply and sales
    public function testStockAndOrderingShowsTheTanksTheFuelOwedAndTheSpeedOfSupply()
    {
        \Carbon\Carbon::setTestNow('2026-10-20 10:00:00');
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        TankBalance::create(['tank_id' => $tank->id, 'balance' => 1000]);
        $nozzle = $this->nozzle($tank);
        $this->supplement($nozzle, 140, '2026-10-15 12:00:00');          // inside the last 7 days
        $this->supplement($nozzle, 70, '2026-10-01 12:00:00');           // only inside the last 30
        $this->sale(['sale_date' => '2026-10-18', 'amount_kg' => 100]);
        $this->sale(['sale_date' => '2026-10-18', 'amount_kg' => 50, 'usage' => 'self']);      // self use is not a sale

        $ticket = function ($barcode, $status, $attrs) {
            Ticket::forceCreate($attrs + ['barcode' => $barcode, 'fuel_type' => 'D', 'code' => '06', 'serial' => (int) substr($barcode, -3), 'status' => $status]);
        };
        $ticket('0000106000000001', config('ticket_statuses.sale'), ['spec' => 2]);                       // 15 kg sold here, not supplied
        $ticket('0000122000000002', config('ticket_statuses.sale'), ['spec' => null, 'amount_kg' => 7]);   // a spot ticket of 7 kg
        $ticket('0000106000000003', config('ticket_statuses.publish'), ['spec' => 2]);                    // still in stock: owed to nobody
        $ticket('0000106000000004', config('ticket_statuses.supply'), ['spec' => 2]);                     // supplied already
        \App\Models\Tickets\OpeningTicket::create(['fuel_type' => 'D', 'spec' => 0, 'quantity' => 3, 'as_of' => '2026-10-01']);          // 3 old tickets of 5 kg

        $page = $this->as('accountant')->get('/reports/ordering')->assertOk()->assertSee('Stock and ordering');
        $row = $page->viewData('rows')['D'];
        $this->assertEquals(790, $row->tanks);
        $this->assertEquals(22, $row->system_owed);
        $this->assertEquals(15, $row->old_owed);
        $this->assertEquals(37, $row->owed);
        $this->assertEquals(753, $row->free);
        $this->assertEquals(20, $row->supplied[7]);
        $this->assertEquals(7, $row->supplied[30]);
        $this->assertEqualsWithDelta(2.33, $row->supplied[90], 0.01);
        $this->assertEqualsWithDelta(100 / 7, $row->sold[7], 0.001);
        $this->assertEqualsWithDelta(37.65, $row->cover[7], 0.001);

        // a fuel type that was never supplied has no days of cover
        $this->assertNull($page->viewData('rows')['A']->cover[7]);

        $this->get('/reports/ordering?export=xls')->assertOk();
        $this->get('/reports')->assertOk()->assertSee('Stock and ordering');
        \Carbon\Carbon::setTestNow();
    }

    // the ticket report counts what a backup cleared, so that its columns add up to what was published
    public function testTicketReportCountsWhatABackupCleared()
    {
        TicketInbound::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'quantity' => 100, 'inbound_date' => '2026-09-01', 'user_id' => 1]);
        TicketPublish::forceCreate(['fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'start_serial' => 1, 'end_serial' => 10, 'count' => 10, 'date' => '2026-09-02', 'user_id' => 1]);
        foreach ([1 => 'publish', 2 => 'sale'] as $serial => $status) {      // 8 of the 10 are gone: used tickets that the backup cleared
            Ticket::forceCreate(['barcode' => sprintf('0000006%09d', $serial), 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => $serial, 'status' => config('ticket_statuses.' . $status)]);
        }

        $press = $this->as('accountant')->get('/reports/tickets')->assertOk()->viewData('rows')->first(function ($r) { return $r->spec === 0; });
        $this->assertSame(8, $press->moved);
    }

    public function testEndDateBeforeStartIsRejected()
    {
        $this->as('accountant')->from('/reports/sales')->get('/reports/sales?from=2026-09-10&to=2026-09-01')
            ->assertSessionHasErrors('to');
    }

    public function testPermissions()
    {
        foreach (['manager', 'accountant'] as $role) {
            $this->as($role)->get('/reports')->assertOk();
            $this->as($role)->get('/reports/tanks')->assertOk();
        }
        foreach (['assistant', 'cashier'] as $role) {
            $this->as($role)->get('/reports')->assertForbidden();
            $this->as($role)->get('/reports/sales')->assertForbidden();
        }
    }
}
