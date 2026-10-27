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
