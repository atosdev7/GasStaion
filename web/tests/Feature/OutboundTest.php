<?php

namespace Tests\Feature;

use App\Models\Admin\Device;
use App\Models\Fuel\Nozzle;
use App\Models\Supplements\Supplement;
use App\Models\Fuel\Tank;
use App\Models\Admin\User;
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
        $this->assertRegExp('/<th>Nozzle<\/th>\s*<th>Mode<\/th>\s*<th>Fuel type<\/th>/', $html);
        $this->assertSame(1, substr_count($html, '<td>Manual</td>'));
        $this->assertSame(1, substr_count($html, '<td>Auto</td>'));
    }

    public function testATankCanBeChosen()
    {
        $this->supply($this->nozzleA, 10, '2026-09-10 10:00:00');
        $this->supply($this->nozzleB, 20, '2026-09-12 10:00:00');

        $response = $this->as('accountant')->get('/outbound' . self::PERIOD . '&tank_id=' . $this->tankB->id)->assertOk();

        $this->assertCount(1, $response->viewData('rows'));
        $this->assertEquals(20, $response->viewData('rows')->first()->out_amount_kg);
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
        $this->assertEquals(10, $rows->first()->out_amount_kg);
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
}
