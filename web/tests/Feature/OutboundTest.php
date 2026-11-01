<?php

namespace Tests\Feature;

use App\Models\Admin\Device;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\Nozzle;
use App\Models\Supplements\Supplement;
use App\Models\Fuel\Tank;
use App\Models\Admin\User;
use App\Support\Dashboard\TankLevels;
use App\Support\Fuel\TankStockSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class OutboundTest extends TestCase
{
    use RefreshDatabase;

    private $tankA;
    private $tankB;
    private $nozzleA;
    private $nozzleB;

    protected function setUp(): void
    {
        parent::setUp();
        $device = Device::forceCreate(['name' => 'POS1']);
        $this->tankA = Tank::forceCreate(['name' => 'Tank A', 'fuel_type' => 'D']);
        $this->tankB = Tank::forceCreate(['name' => 'Tank B', 'fuel_type' => 'D']);
        $this->nozzleA = Nozzle::forceCreate(['device_id' => $device->id, 'number' => 1, 'tank_id' => $this->tankA->id, 'fuel_type' => 'D']);
        $this->nozzleB = Nozzle::forceCreate(['device_id' => $device->id, 'number' => 2, 'tank_id' => $this->tankB->id, 'fuel_type' => 'D']);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function supply(Nozzle $nozzle, $kg, $at, $date = null)
    {
        return Supplement::forceCreate([
            'fuel_type' => 'D', 'nozzle_id' => $nozzle->id, 'device_id' => $nozzle->device_id,
            'out_amount_lt' => $kg / 0.83, 'out_amount_kg' => $kg, 'preset_kg' => $kg, 'supplied_at' => $at, 'supply_date' => $date,
        ]);
    }

    private function current(Tank $tank)
    {
        return TankLevels::all()->firstWhere('tank.id', $tank->id)->current;
    }

    private const PERIOD = '?from=2026-09-01&to=2026-09-30';

    public function testOutboundsListsTheSuppliesOfThePeriodWithTotalsPerTank()
    {
        $this->supply($this->nozzleA, 10, '2026-09-10 10:00:00');
        $this->supply($this->nozzleA, 5, '2026-09-11 10:00:00');
        $this->supply($this->nozzleB, 20, '2026-09-12 10:00:00');
        $this->supply($this->nozzleA, 99, '2026-10-01 10:00:00'); // after the period
        $this->supply($this->nozzleA, 77, '2026-08-31 10:00:00'); // before it
        $this->supply($this->nozzleA, 50, '2026-09-13 10:00:00')->delete(); // deleted

        $response = $this->as('accountant')->get('/outbound' . self::PERIOD)->assertOk();

        $this->assertCount(3, $response->viewData('rows'));
        $perTank = $response->viewData('perTank')->keyBy('tank_id');
        $this->assertEquals(15, $perTank[$this->tankA->id]->kg);
        $this->assertEquals(2, $perTank[$this->tankA->id]->supplies);
        $this->assertEquals(20, $perTank[$this->tankB->id]->kg);
        $response->assertSee('Tank A')->assertSee('Tank B');
    }

    public function testTheListShowsTheModeOfEachSupply()
    {
        $this->supply($this->nozzleA, 10, '2026-09-13 10:00:00')->forceFill(['mode' => 'manual'])->save();
        $this->supply($this->nozzleA, 20, '2026-09-14 10:00:00');

        $html = $this->as('accountant')->get('/outbound' . self::PERIOD)->assertOk()->getContent();
        $this->assertRegExp('/<th>Nozzle<\/th>\s*<th>Mode<\/th>\s*<th>To<\/th>/', $html);
        $this->assertSame(1, substr_count($html, '<td>Manual</td>'));
        $this->assertSame(1, substr_count($html, '<td>Auto</td>'));
    }

    public function testATankCanBeChosen()
    {
        $this->supply($this->nozzleA, 10, '2026-09-10 10:00:00');
        $this->supply($this->nozzleB, 20, '2026-09-12 10:00:00');

        $response = $this->as('accountant')->get('/outbound' . self::PERIOD . '&tank_id=' . $this->tankB->id)->assertOk();

        $this->assertCount(1, $response->viewData('rows'));
        $this->assertEquals(20, $response->viewData('rows')->first()->amount_kg);
        $this->assertCount(1, $response->viewData('perTank'));
    }

    public function testASupplyCountsOnItsDateNotOnItsTimeStamp()
    {
        // 02:00 on 1 October belongs to the business day of 30 September
        $this->supply($this->nozzleA, 10, '2026-10-01 02:00:00', '2026-09-30');
        // 02:00 on 1 September belongs to the day before the period
        $this->supply($this->nozzleA, 7, '2026-09-01 02:00:00', '2026-08-31');

        $rows = $this->as('accountant')->get('/outbound' . self::PERIOD)->assertOk()->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertEquals(10, $rows->first()->amount_kg);
    }

    public function testASupplyOfADeletedNozzleStillShowsItsTank()
    {
        $this->supply($this->nozzleA, 10, '2026-09-10 10:00:00');
        $this->nozzleA->delete();

        $response = $this->as('accountant')->get('/outbound' . self::PERIOD)->assertOk();

        $this->assertCount(1, $response->viewData('rows'));
        $this->assertEquals(10, $response->viewData('perTank')->first()->kg);
        $response->assertSee('Tank A');
    }

    public function testADeletedTankIsNotOfferedButItsOldSuppliesKeepItsName()
    {
        $this->supply($this->nozzleB, 20, '2026-09-12 10:00:00');
        $this->tankB->delete();

        $response = $this->as('accountant')->get('/outbound' . self::PERIOD)->assertOk();

        $this->assertSame([$this->tankA->id], $response->viewData('tanks')->keys()->all()); // the filter lists the tanks in use
        $response->assertSee('Tank B');                                                     // the supply still names its tank
        $this->assertCount(1, $response->viewData('rows'));
        $this->assertEquals(20, $response->viewData('perTank')->first()->kg);
        $this->assertStringNotContainsString('<option value="' . $this->tankB->id . '"', $response->getContent());
    }

    public function testAnEmptyPeriodAndABadPeriod()
    {
        $this->as('accountant')->get('/outbound' . self::PERIOD)->assertOk();
        $this->as('accountant')->get('/outbound?from=2026-09-30&to=2026-09-01')->assertSessionHasErrors('to');
    }

    public function testWhoMaySeeIt()
    {
        $this->as('admin')->get('/outbound')->assertOk();
        $this->as('manager')->get('/outbound')->assertOk();
        $this->as('accountant')->get('/outbound')->assertOk();
        $this->as('assistant')->get('/outbound')->assertForbidden();
        $this->as('cashier')->get('/outbound')->assertForbidden();
    }

    public function testTheFuelMenuHasInboundsAndOutbounds()
    {
        $page = $this->as('accountant')->get('/outbound')->assertOk();

        $page->assertSee('Fuel Inbound/Outbound');
        $page->assertSee('Inbounds');
        $page->assertSee('Outbounds');
        $page->assertSee(route('inbound.index'));
        $page->assertSee(route('outbound.index'));
    }

    // ---- the row that follows a supply ----

    public function testASupplyMakesItsOutboundRowAndChangesAndRemovesItWithTheSupply()
    {
        $supply = $this->supply($this->nozzleA, 10, '2026-09-10 10:00:00');
        $row = FuelOutbound::where('supplement_id', $supply->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($this->tankA->id, (int) $row->tank_id);
        $this->assertEquals(10, $row->amount_kg);
        $this->assertSame('2026-09-10', substr((string) $row->outbound_date, 0, 10));
        $this->assertEquals(-10, $this->current($this->tankA));

        // the supply is changed: its amount and its day follow
        $supply->forceFill(['out_amount_kg' => 12, 'supply_date' => '2026-09-09'])->save();
        $row = FuelOutbound::where('supplement_id', $supply->id)->first();
        $this->assertEquals(12, $row->amount_kg);
        $this->assertSame('2026-09-09', substr((string) $row->outbound_date, 0, 10));
        $this->assertSame(1, FuelOutbound::count());

        // deleted: the row no longer counts; restored: it does again
        $supply->delete();
        $this->assertSame(0, FuelOutbound::count());
        $this->assertEquals(0, $this->current($this->tankA));
        $supply->restore();
        $this->assertSame(1, FuelOutbound::count());
        $this->assertEquals(-12, $this->current($this->tankA));

        // gone for good: the row goes too
        $supply->forceDelete();
        $this->assertSame(0, FuelOutbound::withTrashed()->count());
    }

    // an outbound by hand can take its fuel from the layer of a cost (a user who may see costs chooses it); with none, the oldest fuel goes first
    public function testAnOutboundByHandCanHaveTheCostOfTheLayerItTakesItsFuelFrom()
    {
        \App\Models\Fuel\TankBalance::create(['tank_id' => $this->tankA->id, 'balance' => 400, 'cost' => 6, 'balance_date' => '2026-09-01']);
        FuelInbound::forceCreate(['tank_id' => $this->tankA->id, 'fuel_type' => $this->tankA->fuel_type, 'amount_kg' => 100, 'cost' => 9, 'inbound_date' => '2026-09-05', 'user_id' => 1]);

        $page = $this->as('accountant')->get('/outbound/create')->assertOk();
        $this->assertSame([9.0, 6.0], array_column($page->viewData('costChoices')[$this->tankA->id], 'cost'));
        $this->assertStringContainsString('<select id="f_cost" name="cost"', $page->getContent());

        $good = ['tank_id' => $this->tankA->id, 'outbound_date' => '2026-09-10', 'amount_kg' => 25, 'to_name' => 'sample'];
        $this->post('/outbound', ['cost' => '9'] + $good)->assertSessionHasNoErrors();
        $this->post('/outbound', $good)->assertSessionHasNoErrors();                                  // none: the oldest fuel first
        $this->post('/outbound', ['cost' => '-1'] + $good)->assertSessionHasErrors('cost');
        $rows = FuelOutbound::orderBy('id')->get();
        $this->assertEquals(9, $rows[0]->cost);
        $this->assertNull($rows[1]->cost);
        $layers = collect(\App\Support\Fuel\TankCostLayers::forTank($this->tankA->id))->mapWithKeys(function ($l) { return [number_format($l['cost'], 4, '.', '') => $l['kg']]; })->all();
        $this->assertEquals(['6.0000' => 375.0, '9.0000' => 75.0], $layers);

        // changed: the cost can be chosen again; the list shows it
        $this->put('/outbound/' . $rows[1]->id, ['cost' => '6'] + $good)->assertSessionHasNoErrors();
        $this->assertEquals(6, $rows[1]->fresh()->cost);
        $this->get('/outbound' . self::PERIOD)->assertOk()->assertSee('Cost per kg')->assertSee('9.0000');
    }

    public function testTheTankOfASupplyStaysWhenTheNozzleIsConnectedToAnotherTankLater()
    {
        $supply = $this->supply($this->nozzleA, 10, '2026-09-10 10:00:00');

        // the nozzle is removed and added again on the other tank: what it supplied before stays with the tank it drew from
        $this->nozzleA->forceFill(['tank_id' => $this->tankB->id])->save();
        $supply->forceFill(['out_amount_kg' => 11])->save();

        $this->assertEquals(-11, $this->current($this->tankA));
        $this->assertEquals(0, $this->current($this->tankB));
        $this->assertSame($this->tankA->id, (int) FuelOutbound::where('supplement_id', $supply->id)->value('tank_id'));

        // a new supply of the nozzle is taken from the new tank
        $this->supply($this->nozzleA, 4, '2026-09-11 10:00:00');
        $this->assertEquals(-4, $this->current($this->tankB));
    }

    public function testTheRowsOfSuppliesAreMadeForTheSuppliesThatHaveNone()
    {
        $supply = $this->supply($this->nozzleA, 10, '2026-09-10 10:00:00');
        FuelOutbound::query()->forceDelete();                     // e.g. what an import of an older export leaves

        FuelOutbound::syncMissing();

        $this->assertSame(1, FuelOutbound::where('supplement_id', $supply->id)->count());
        FuelOutbound::syncMissing();                              // and not twice
        $this->assertSame(1, FuelOutbound::count());
    }

    // ---- the rows made by hand ----

    private function byHand($kg = 30, $extra = [])
    {
        return $extra + ['tank_id' => $this->tankA->id, 'outbound_date' => '2026-09-15', 'amount_kg' => $kg, 'to_name' => 'Depot 2', 'note' => 'transfer'];
    }

    public function testAnOutboundCanBeAddedChangedAndRemovedByHandAndTheStockFollows()
    {
        FuelInbound::forceCreate(['tank_id' => $this->tankA->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 5, 'inbound_date' => '2026-09-01', 'user_id' => 1]);
        $this->assertEquals(100, $this->current($this->tankA));

        $this->as('accountant')->post('/outbound', $this->byHand(30))->assertRedirect('/outbound');
        $row = FuelOutbound::first();
        $this->assertFalse($row->isSupply());
        $this->assertEquals(30, $row->amount_kg);
        $this->assertSame('Depot 2', $row->to_name);
        $this->assertEquals(70, $this->current($this->tankA));

        $this->put('/outbound/' . $row->id, $this->byHand(25, ['tank_id' => $this->tankB->id]))->assertRedirect('/outbound');
        $this->assertEquals(100, $this->current($this->tankA));                    // the 30 kg went back
        $this->assertEquals(-25, $this->current($this->tankB));

        $this->delete('/outbound/' . $row->id)->assertRedirect('/outbound');
        $this->assertSame(0, FuelOutbound::count());
        $this->assertEquals(0, $this->current($this->tankB));
        $this->assertSame(1, FuelOutbound::withTrashed()->count());                // kept as deleted
    }

    public function testAnOutboundByHandCannotBeDatedInTheFutureAndTellsWhenItTakesMoreThanTheTankHolds()
    {
        $this->as('manager');
        $this->post('/outbound', $this->byHand(10, ['outbound_date' => now()->addDays(2)->toDateString()]))->assertSessionHasErrors('outbound_date');
        $this->assertSame(0, FuelOutbound::count());

        FuelInbound::forceCreate(['tank_id' => $this->tankA->id, 'fuel_type' => 'D', 'amount_kg' => 20, 'cost' => 5, 'inbound_date' => '2026-09-01', 'user_id' => 1]);
        $this->post('/outbound', $this->byHand(10))->assertSessionMissing('warning');
        $this->post('/outbound', $this->byHand(30))->assertSessionHas('warning');     // saved all the same: the tank is at -20 now
        $this->assertSame(2, FuelOutbound::count());
        $this->assertEquals(-20, $this->current($this->tankA));
    }

    public function testTheFormChecksWhatIsEntered()
    {
        $this->as('manager');
        $this->post('/outbound', $this->byHand(0))->assertSessionHasErrors('amount_kg');
        $this->post('/outbound', $this->byHand(-5))->assertSessionHasErrors('amount_kg');
        $this->post('/outbound', $this->byHand(10, ['tank_id' => 999]))->assertSessionHasErrors('tank_id');
        $this->post('/outbound', $this->byHand(10, ['outbound_date' => 'x']))->assertSessionHasErrors('outbound_date');
        $this->post('/outbound', ['tank_id' => $this->tankA->id])->assertSessionHasErrors(['outbound_date', 'amount_kg']);
        $this->assertSame(0, FuelOutbound::count());

        $this->tankB->delete();
        $this->post('/outbound', $this->byHand(10, ['tank_id' => $this->tankB->id]))->assertSessionHasErrors('tank_id'); // a deleted tank
    }

    public function testTheRowOfASupplyCannotBeChangedByHand()
    {
        $supply = $this->supply($this->nozzleA, 10, '2026-09-10 10:00:00');
        $row = FuelOutbound::where('supplement_id', $supply->id)->first();

        $this->as('manager');
        $this->get('/outbound/' . $row->id . '/edit')->assertForbidden();
        $this->put('/outbound/' . $row->id, $this->byHand(1))->assertForbidden();
        $this->delete('/outbound/' . $row->id)->assertForbidden();
        $this->assertEquals(10, FuelOutbound::find($row->id)->amount_kg);

        $html = $this->get('/outbound' . self::PERIOD)->getContent();
        $this->assertStringNotContainsString('/outbound/' . $row->id . '/edit', $html);   // no buttons on it
    }

    public function testWhoMayAddAnOutbound()
    {
        $this->as('admin')->get('/outbound/create')->assertOk();
        $this->as('manager')->get('/outbound/create')->assertOk();
        $this->as('accountant')->get('/outbound/create')->assertOk();
        $this->as('assistant')->get('/outbound/create')->assertForbidden();
        $this->as('assistant')->post('/outbound', $this->byHand())->assertForbidden();
        $this->as('cashier')->post('/outbound', $this->byHand())->assertForbidden();
        $this->assertSame(0, FuelOutbound::count());

        $html = $this->as('accountant')->get('/outbound')->getContent();
        $this->assertStringContainsString(route('outbound.create'), $html);
    }

    // ---- what the stock counts ----

    public function testDeletedRecordsHaveNoEffectOnTheStockAndItsSummary()
    {
        $inbound = FuelInbound::forceCreate(['tank_id' => $this->tankA->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 5, 'inbound_date' => '2026-09-01', 'user_id' => 1]);
        $gone = FuelInbound::forceCreate(['tank_id' => $this->tankA->id, 'fuel_type' => 'D', 'amount_kg' => 40, 'cost' => 9, 'inbound_date' => '2026-09-02', 'user_id' => 1]);
        $supply = $this->supply($this->nozzleA, 10, '2026-09-10 10:00:00');
        $hand = new FuelOutbound;
        $hand->forceFill($this->byHand(20) + ['user_id' => 1])->save();
        $this->assertEquals(110, $this->current($this->tankA));

        $gone->delete();
        $supply->delete();
        $hand->delete();

        $this->assertEquals(100, $this->current($this->tankA));
        $summary = TankStockSummary::build();
        $this->assertEquals(100, $summary['fuels']['D']['balance']);
        $this->assertEquals(100, $summary['fuels']['D']['valued_kg']);              // only the inbound that is left is valued
        $this->assertEquals(500, $summary['fuels']['D']['value']);
        $this->assertEquals(5, $summary['fuels']['D']['layers'][0]['cost']);
    }
}
