<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\InitialTankBalance;
use App\Models\Fuel\Nozzle;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Fuel\Tank;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketInbound;
use App\Models\Tickets\TicketPublish;
use App\Models\Admin\User;
use App\Support\Dashboard\DashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    public function testDashboardShowsTodayOpenDebtAndLowTank()
    {
        $customer = Customer::forceCreate(['name' => 'ACME']);
        Sale::forceCreate([
            'sale_date' => today()->toDateString(), 'customer_id' => $customer->id, 'sale_type' => 'ticket', 'fuel_type' => 'D',
            'price' => 2, 'amount_kg' => 10, 'payment_method' => 'cash', 'total_amount' => 20,
            'paid_amount' => 20, 'unpaid_amount' => 0, 'usage' => 'sale', 'user_id' => 1,
        ]);
        $debt = Debt::forceCreate(['customer_id' => $customer->id, 'amount' => 100, 'payment_method' => 'cash', 'user_id' => 1]);
        DebtPayment::forceCreate([
            'customer_id' => $customer->id, 'debt_id' => $debt->id, 'amount' => 30, 'payment_method' => 'cash', 'paid_date' => today(), 'user_id' => 1,
        ]);
        $tank = Tank::forceCreate(['name' => 'Low Tank', 'fuel_type' => 'D', 'capacity_kg' => 1000]);
        InitialTankBalance::create(['tank_id' => $tank->id, 'amount_kg' => 150]);

        $response = $this->as('manager')->get('/')->assertOk()
            ->assertSee(__('report.low_tank', ['tank' => 'Low Tank', 'kg' => '150.00']));

        $this->assertEquals(20, $response->viewData('todayTotal'));
        $this->assertEquals(70, $response->viewData('openDebt'));
    }

    public function testRolesWithoutDashboardPermissionSeeOnlyTheWelcome()
    {
        $this->as('cashier')->get('/')->assertOk()
            ->assertDontSee(__('report.open_debt_balance'));
    }

    private function sale($type, $kg, $date, $confirmed = false)
    {
        return Sale::forceCreate([
            'sale_date' => $date, 'sale_type' => $type, 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => $kg, 'payment_method' => 'cash',
            'total_amount' => $kg * 2, 'paid_amount' => $kg * 2, 'unpaid_amount' => 0, 'usage' => 'sale', 'user_id' => 1,
            'confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    private function supplement($nozzle, $kg, $date, $confirmed = false)
    {
        return Supplement::forceCreate([
            'fuel_type' => 'D', 'nozzle_id' => $nozzle->id, 'out_amount_lt' => $kg, 'out_amount_kg' => $kg, 'preset_kg' => $kg,
            'supplied_at' => $date . ' 10:00:00', 'supply_date' => $date, 'confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    private function nozzle()
    {
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => 1000]);

        return Nozzle::forceCreate(['device_id' => Device::forceCreate(['name' => 'P1'])->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => 'D']);
    }

    public function testTodayAgainstYesterdayShowsTheChangeAndTheSuppliedKg()
    {
        $nozzle = $this->nozzle();
        $this->sale('ticket', 30, today()->toDateString());
        $this->sale('spot', 10, today()->subDay()->toDateString());
        $this->sale('ticket', 10, today()->subDay()->toDateString());
        $this->supplement($nozzle, 12, today()->toDateString());

        $compare = DashboardData::compare();
        $this->assertEquals(30, $compare->today->kg);
        $this->assertEquals(20, $compare->yesterday->kg);
        $this->assertEquals(50.0, $compare->kgChange);
        $this->assertEquals(12, $compare->today->supplied);
        $this->assertNull($compare->suppliedChange);   // nothing yesterday

        $this->as('accountant')->get('/')->assertOk()->assertSee('dash-compare')->assertSee('&#9650; 50%', false);
    }

    public function testSalesOfTheLastDaysAreSplitByTypeWithTheConfirmationOfTheDay()
    {
        $nozzle = $this->nozzle();
        $d1 = today()->subDays(2)->toDateString();
        $d2 = today()->subDay()->toDateString();
        $this->sale('ticket', 5, $d1, true);
        $this->sale('spot', 7, $d1, true);
        $this->supplement($nozzle, 12, $d1, true);
        $this->sale('card', 3, $d2, true);
        $this->sale('ticket', 4, $d2, false);   // one record of the day is not confirmed
        $this->sale('ticket', 99, today()->subDays(30)->toDateString());   // outside the 14 days

        $rows = collect(DashboardData::salesByDay())->keyBy('day');
        $this->assertCount(14, $rows);
        $this->assertEquals([5, 7, 0, 'confirmed'], [$rows[$d1]->ticket, $rows[$d1]->spot, $rows[$d1]->card, $rows[$d1]->status]);
        $this->assertEquals([4, 0, 3, 'open'], [$rows[$d2]->ticket, $rows[$d2]->spot, $rows[$d2]->card, $rows[$d2]->status]);
        $this->assertSame('none', $rows[today()->toDateString()]->status);

        $this->as('manager')->get('/')->assertOk()->assertSee('dash-sales')->assertSee('dash-status-open');
    }

    public function testTankTrendGoesBackFromTheCurrentLevel()
    {
        $nozzle = $this->nozzle();
        $tank = Tank::first();
        InitialTankBalance::create(['tank_id' => $tank->id, 'amount_kg' => 100]);
        $d3 = today()->subDays(3)->toDateString();
        $d1 = today()->subDay()->toDateString();
        FuelInbound::forceCreate(['tank_id' => $tank->id, 'fuel_type' => 'D', 'amount_kg' => 500, 'cost' => 1, 'inbound_date' => $d3, 'user_id' => 1]);
        $this->supplement($nozzle, 40, $d1);   // current = 100 + 500 - 40 = 560

        $trend = DashboardData::tankTrend(\App\Support\Dashboard\TankLevels::all());
        $levels = array_combine($trend->days, $trend->lines[0]->levels);
        $this->assertEquals(560, $levels[today()->toDateString()]);
        $this->assertEquals(600, $levels[today()->subDays(2)->toDateString()]);   // before the 40 kg went out
        $this->assertEquals(600, $levels[$d3]);
        $this->assertEquals(100, $levels[today()->subDays(4)->toDateString()]);   // before the 500 kg came in
        $this->assertEquals(100, $levels[$trend->days[0]]);

        $this->as('admin')->get('/')->assertOk()->assertSee('dash-trend')->assertSee('dash-lowline', false);
    }

    public function testTicketStockCountsBlankAndPublishedTicketsBySpec()
    {
        TicketInbound::forceCreate(['fuel_type' => 'D', 'spec' => 1, 'quantity' => 100, 'inbound_date' => today(), 'user_id' => 1]);
        TicketPublish::forceCreate(['fuel_type' => 'D', 'spec' => 1, 'code' => '07', 'start_serial' => 1, 'end_serial' => 30, 'count' => 30, 'date' => today(), 'user_id' => 1]);
        foreach ([1, 2, 3] as $serial) {
            Ticket::forceCreate([
                'barcode' => sprintf('000000700%07d', $serial), 'fuel_type' => 'D', 'spec' => 1, 'code' => '07', 'serial' => $serial,
                'status' => config('ticket_statuses.' . ($serial === 3 ? 'sale' : 'publish')),
            ]);
        }

        $row = collect(DashboardData::ticketStock())->first(function ($s) { return $s->fuel_type === 'D' && $s->spec === 1; });
        $this->assertSame(70, $row->blank);   // 100 received, 30 published
        $this->assertSame(2, $row->ready);    // two of the published ones are not sold yet
        $this->assertEquals(10, $row->kg);

        $this->as('accountant')->get('/')->assertOk()->assertSee('dash-tickets');
    }

    public function testTheChartsAreNotShownToRolesWithoutTheDashboard()
    {
        $this->as('cashier')->get('/')->assertOk()->assertDontSee('dash-compare')->assertDontSee('dash-tanks');
    }
}
