<?php

namespace Tests\Feature;

use App\Models\Admin\Device;
use App\Models\Admin\User;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\TankBalance;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use App\Models\Supplements\Supplement;
use App\Support\Fuel\TankStockSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TankStockTest extends TestCase
{
    use RefreshDatabase;

    private $diesel;
    private $gasoline;
    private $dieselNozzle;
    private $gasolineNozzle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->diesel = Tank::forceCreate(['name' => 'T-Diesel', 'fuel_type' => 'D', 'capacity_kg' => 10000]);
        $this->gasoline = Tank::forceCreate(['name' => 'T-Gas', 'fuel_type' => 'A', 'capacity_kg' => 2000]);
        $device = Device::forceCreate(['name' => 'Pump 1']);
        $this->dieselNozzle = Nozzle::forceCreate(['device_id' => $device->id, 'number' => 1, 'tank_id' => $this->diesel->id, 'fuel_type' => 'D']);
        $this->gasolineNozzle = Nozzle::forceCreate(['device_id' => $device->id, 'number' => 2, 'tank_id' => $this->gasoline->id, 'fuel_type' => 'A']);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function inbound($tank, $kg, $cost, $date, $default = false)
    {
        $inbound = FuelInbound::forceCreate([
            'tank_id' => $tank->id, 'fuel_type' => $tank->fuel_type, 'amount_kg' => $kg, 'cost' => $cost, 'inbound_date' => $date, 'user_id' => 1,
        ]);
        if ($default) {
            \App\Support\Fuel\DefaultCost::set($tank->fuel_type, $cost);      // the button of the record: the default cost is the cost of this one
        }

        return $inbound;
    }

    private function supply($nozzle, $kg)
    {
        return Supplement::forceCreate([
            'fuel_type' => $nozzle->fuel_type, 'nozzle_id' => $nozzle->id, 'device_id' => $nozzle->device_id, 'out_amount_lt' => $kg, 'out_amount_kg' => $kg,
            'preset_kg' => $kg, 'supplied_at' => '2026-10-06 10:00:00', 'supply_date' => '2026-10-06',
        ]);
    }

    public function testTheBalanceOfAFuelTypeIsCostedByTheNewestInboundsFirst()
    {
        TankBalance::create(['tank_id' => $this->diesel->id, 'balance' => 1000]);
        $this->inbound($this->diesel, 5000, 6.0, '2026-10-01');
        $this->inbound($this->diesel, 3000, 7.0, '2026-10-05', true);
        $this->supply($this->dieselNozzle, 4000);                          // 1000 + 8000 - 4000 = 5000 kg in the tank

        $fuel = TankStockSummary::build()['fuels']['D'];

        $this->assertSame(5000.0, $fuel['balance']);
        $this->assertCount(2, $fuel['layers']);
        // the newest inbound is what is left of it all: 3000 kg at 7.00, then 2000 of the 5000 kg at 6.00
        $this->assertEquals(['2026-10-05', 7.0, 3000.0, 21000.0], [$fuel['layers'][0]['inbound_date'], $fuel['layers'][0]['cost'], $fuel['layers'][0]['kg'], $fuel['layers'][0]['value']]);
        $this->assertEquals(['2026-10-01', 6.0, 2000.0, 12000.0], [$fuel['layers'][1]['inbound_date'], $fuel['layers'][1]['cost'], $fuel['layers'][1]['kg'], $fuel['layers'][1]['value']]);
        $this->assertSame(5000.0, $fuel['valued_kg']);
        $this->assertSame(0.0, $fuel['unknown_kg']);
        $this->assertSame(33000.0, $fuel['value']);
        $this->assertSame(6.6, $fuel['average']);                          // 33000 / 5000
        $this->assertEquals(7.0, $fuel['default_cost']);                   // what a sale is costed at now
    }

    // a supply takes its fuel from the layer of its cost, so the stock is known per cost; one with no cost takes the oldest fuel first
    public function testASupplyTakesItsFuelFromTheLayerOfItsCost()
    {
        $this->inbound($this->diesel, 1000, 6.0, '2026-10-01');
        $this->inbound($this->diesel, 1000, 7.0, '2026-10-05');
        $withCost = function ($kg, $cost) {
            $supply = $this->supply($this->dieselNozzle, $kg);
            $supply->forceFill(['cost' => $cost])->save();
        };
        $withCost(300, 7.0);                                              // from the newer layer
        $withCost(200, 6.0);

        $fuel = TankStockSummary::build()['fuels']['D'];
        $this->assertSame(1500.0, $fuel['balance']);
        $layers = array_column($fuel['layers'], 'kg', 'cost');
        $this->assertEquals([7.0 => 700.0, 6.0 => 800.0], $layers);       // oldest-first would have emptied the cheaper one
        $this->assertSame(0.0, $fuel['unknown_kg']);
        $this->assertSame(700 * 7.0 + 800 * 6.0, $fuel['value']);

        // one with no cost takes the oldest fuel first
        $this->supply($this->dieselNozzle, 500);
        $fuel = TankStockSummary::build()['fuels']['D'];
        $this->assertEquals([7.0 => 700.0, 6.0 => 300.0], array_column($fuel['layers'], 'kg', 'cost'));

        // more left at a cost than came in at it: that layer is below zero, and the stock is right in total
        $withCost(400, 8.0);
        $fuel = TankStockSummary::build()['fuels']['D'];
        $this->assertSame(600.0, $fuel['balance']);
        $this->assertEquals([7.0 => 700.0, 6.0 => 300.0, 8.0 => -400.0], array_column($fuel['layers'], 'kg', 'cost'));
        $this->assertSame(600.0, $fuel['valued_kg']);
    }

    public function testWhatTheInboundsDoNotCoverHasNoKnownCostAndIsNotValued()
    {
        TankBalance::create(['tank_id' => $this->gasoline->id, 'balance' => 500]);
        $this->inbound($this->gasoline, 1000, 8.0, '2026-10-02', true);
        $this->supply($this->gasolineNozzle, 200);                         // 500 + 1000 - 200 = 1300 kg: 1000 at 8.00 and 300 of the opening stock

        $fuel = TankStockSummary::build()['fuels']['A'];

        $this->assertSame(1300.0, $fuel['balance']);
        $this->assertSame(1000.0, $fuel['valued_kg']);
        $this->assertSame(300.0, $fuel['unknown_kg']);
        $this->assertSame(8000.0, $fuel['value']);
        $this->assertSame(8.0, $fuel['average']);

        // a deleted inbound does not count, and a fuel type with nothing in the tanks has no layers and no average
        FuelInbound::query()->delete();
        $fuel = TankStockSummary::build()['fuels']['A'];
        $this->assertSame(500.0 - 200.0, $fuel['balance']);
        $this->assertSame([], $fuel['layers']);
        $this->assertSame(300.0, $fuel['unknown_kg']);
        $this->assertNull($fuel['average']);
        $this->assertEquals(8.0, $fuel['default_cost']);                   // the default cost is a value of its own: the inbound it was copied from is gone, it is not
    }

    public function testThePageShowsTheFuelTypesTheLayersAndTheTanks()
    {
        TankBalance::create(['tank_id' => $this->diesel->id, 'balance' => 1000]);
        $this->inbound($this->diesel, 5000, 6.0, '2026-10-01');
        $this->inbound($this->diesel, 3000, 7.0, '2026-10-05', true);
        $this->inbound($this->gasoline, 1000, 8.0, '2026-10-02', true);
        $this->supply($this->dieselNozzle, 4000);

        $page = $this->as('accountant')->get('/tank-stock')->assertOk();

        $page->assertSee('Tank Stock Summary')->assertSee('Stock by fuel type')->assertSee('Balance by cost: Diesel')->assertSee('Stock by tank');
        $page->assertSee('5,000.00')->assertSee('33,000.00')->assertSee('6.6000')->assertSee('7.0000')->assertSee('21,000.00');
        $page->assertSee('T-Diesel')->assertSee('T-Gas');
        $this->assertSame(41000.0, $page->viewData('value'));               // 33000 for the diesel + 1000 kg at 8.00 for the gasoline
        $page->assertSee('41,000.00');
        $this->assertSame(['A', 'D'], array_keys($page->viewData('fuels')));
        $this->assertStringContainsString('id="stock-layers-D"', $page->getContent());
    }

    public function testAnEmptyStationShowsAnEmptyPage()
    {
        Tank::query()->delete();

        $this->as('manager')->get('/tank-stock')->assertOk()->assertSee('No data for this period.');
    }

    public function testWhoMayLookAndTheMenuItem()
    {
        foreach (['manager', 'accountant', 'admin'] as $role) {
            $this->as($role)->get('/tank-stock')->assertOk();
        }
        $this->as('manager')->get('/')->assertSee(route('tank-stock.index'));
        foreach (['assistant', 'cashier'] as $role) {
            $this->as($role)->get('/tank-stock')->assertForbidden();
        }
    }
}
