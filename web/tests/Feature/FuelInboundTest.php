<?php

namespace Tests\Feature;

use App\Models\Fuel\FuelInbound;
use App\Support\Dashboard\TankLevels;
use App\Support\Fuel\DefaultCost;
use App\Models\Fuel\Tank;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelInboundTest extends TestCase
{
    use RefreshDatabase;

    private $diesel;
    private $gas;
    private $tankD;
    private $tankG;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->diesel = 'D';
        $this->gas = 'A';
        $this->tankD = Tank::forceCreate(['name' => 'TD', 'fuel_type' => $this->diesel]);
        $this->tankG = Tank::forceCreate(['name' => 'TG', 'fuel_type' => $this->gas]);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function inbound($tank, $cost, $extra = [])
    {
        return $this->post('/inbound', $extra + [
            'tank_id' => $tank->id, 'inbound_date' => '2026-09-20', 'amount_kg' => '10000', 'cost' => $cost,
        ]);
    }

    // the default cost is a value of its own (App\Support\Fuel\DefaultCost): the first cost of a fuel type, or the button of a record, sets it
    public function testFirstInboundOfAFuelTypeSetsTheDefaultCost()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1000')->assertRedirect('/inbound');

        $row = FuelInbound::first();
        $this->assertSame($this->diesel, $row->fuel_type); // taken from the tank
        $this->assertEquals(6.1, DefaultCost::get($this->diesel));
        $this->assertTrue(DefaultCost::is($this->diesel, '6.1'));
        $this->assertSame('6.1000', \App\Models\Admin\Setting::find('default_cost_D')->value);
    }

    public function testLaterInboundKeepsTheDefaultCostUnlessAskedToChange()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');
        $this->inbound($this->tankD, '6.5');

        $this->assertEquals(6.1, DefaultCost::get($this->diesel));

        $this->inbound($this->tankD, '6.9', ['is_default' => 1]);
        $this->assertEquals(6.9, DefaultCost::get($this->diesel));
        $this->assertSame(3, FuelInbound::count());                                       // the records are not changed by it
    }

    public function testTheDefaultCostIsCopiedAndSoItDoesNotFollowTheRecordItCameFrom()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');
        $this->inbound($this->tankD, '6.9');
        $second = FuelInbound::where('cost', 6.9)->first();

        $this->post('/inbound/' . $second->id . '/default')->assertRedirect('/inbound')->assertSessionHas('status');
        $this->assertStringContainsString('6.9000', session('status'));
        $this->assertEquals(6.9, DefaultCost::get($this->diesel));

        // the record is changed, even deleted: the default stays what it was copied as
        $this->put('/inbound/' . $second->id, ['tank_id' => $this->tankD->id, 'inbound_date' => '2026-09-20', 'amount_kg' => '10000', 'cost' => '7.5'])->assertRedirect('/inbound');
        $this->assertEquals(6.9, DefaultCost::get($this->diesel));
        $this->delete('/inbound/' . $second->id)->assertRedirect('/inbound');
        $this->assertEquals(6.9, DefaultCost::get($this->diesel));
        $this->assertFalse(DefaultCost::is($this->diesel, '7.5'));
    }

    public function testDefaultCostIsPerFuelType()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');
        $this->inbound($this->tankG, '7.2');

        $this->assertEquals(6.1, DefaultCost::get($this->diesel));
        $this->assertEquals(7.2, DefaultCost::get($this->gas));
    }

    public function testMakeDefaultCostChangesTheCostOfTheFuelTypeOnly()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');
        $this->inbound($this->tankG, '7.2');
        $this->inbound($this->tankD, '6.5');
        $second = FuelInbound::where('cost', 6.5)->first();

        $this->post('/inbound/' . $second->id . '/default')->assertRedirect('/inbound');

        $this->assertEquals(6.5, DefaultCost::get($this->diesel));
        $this->assertEquals(7.2, DefaultCost::get($this->gas));
    }

    public function testNoDefaultCostWhenNothingWasReceived()
    {
        $this->assertNull(DefaultCost::get($this->diesel));
        $this->assertFalse(DefaultCost::is($this->diesel, 6));
    }

    public function testValidation()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '-1')->assertSessionHasErrors('cost');
        $this->inbound($this->tankD, '6', ['amount_kg' => '0'])->assertSessionHasErrors('amount_kg');
        $this->inbound($this->tankD, '6', ['tank_id' => 999])->assertSessionHasErrors('tank_id');
        $this->inbound($this->tankD, '6', ['inbound_date' => 'nope'])->assertSessionHasErrors('inbound_date');
        $this->assertSame(0, FuelInbound::count());
    }

    public function testPermissions()
    {
        $this->as('accountant')->get('/inbound')->assertOk();           // the accountant adds inbounds too (inbound.manage)
        $this->get('/inbound/create')->assertOk();
        $this->inbound($this->tankD, '6')->assertRedirect('/inbound');
        $this->assertSame(1, FuelInbound::count());

        $this->as('cashier')->get('/inbound')->assertForbidden();
        $this->as('cashier')->get('/inbound/create')->assertForbidden();
        $this->as('assistant')->get('/inbound')->assertForbidden();
        $this->as('assistant')->get('/inbound/create')->assertForbidden();
        $this->as('manager')->get('/inbound/create')->assertOk();
        $this->assertSame(1, FuelInbound::count());
    }

    public function testListShowsTheDefaultBadge()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');

        $this->get('/inbound')->assertOk()->assertSee('Diesel')->assertSee('Default');
    }

    public function testEditChangesTheRecordAndTheTankStockFollows()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1', ['amount_kg' => '10000']);
        $row = FuelInbound::first();

        $this->get('/inbound/' . $row->id . '/edit')->assertOk()->assertSee('10000.00');
        $this->put('/inbound/' . $row->id, [
            'tank_id' => $this->tankD->id, 'inbound_date' => '2026-09-22', 'amount_kg' => '8000', 'cost' => '6.5', 'note' => 'corrected',
        ])->assertRedirect('/inbound');

        $row = $row->fresh();
        $this->assertEquals(8000, $row->amount_kg);
        $this->assertEquals(6.5, $row->cost);
        $this->assertSame('corrected', $row->note);
        $this->assertSame('2026-09-22', substr((string) $row->inbound_date, 0, 10));
        $this->assertEquals(8000, TankLevels::all()->firstWhere('tank.id', $this->tankD->id)->inbound);
        $this->assertEquals(6.1, DefaultCost::get($this->diesel)); // the default cost is a value of its own: a change of the record does not change it
    }

    public function testEditOffersAndAcceptsOnlyTanksOfTheSameFuel()
    {
        $other = Tank::forceCreate(['name' => 'TD2', 'fuel_type' => $this->diesel]);
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');
        $row = FuelInbound::first();

        $page = $this->get('/inbound/' . $row->id . '/edit')->assertOk();
        $this->assertSame(['TD', 'TD2'], $page->viewData('tanks')->values()->all());

        $this->put('/inbound/' . $row->id, ['tank_id' => $other->id, 'inbound_date' => '2026-09-20', 'amount_kg' => '10000', 'cost' => '6.1'])
            ->assertRedirect('/inbound');
        $this->assertSame($other->id, (int) $row->fresh()->tank_id);

        $this->put('/inbound/' . $row->id, ['tank_id' => $this->tankG->id, 'inbound_date' => '2026-09-20', 'amount_kg' => '10000', 'cost' => '6.1'])
            ->assertSessionHasErrors('tank_id');
        $this->assertSame($other->id, (int) $row->fresh()->tank_id);
    }

    public function testEditIsValidated()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');
        $row = FuelInbound::first();
        $good = ['tank_id' => $this->tankD->id, 'inbound_date' => '2026-09-20', 'amount_kg' => '10000', 'cost' => '6.1'];

        $this->put('/inbound/' . $row->id, ['amount_kg' => '0'] + $good)->assertSessionHasErrors('amount_kg');
        $this->put('/inbound/' . $row->id, ['cost' => '-1'] + $good)->assertSessionHasErrors('cost');
        $this->put('/inbound/' . $row->id, ['inbound_date' => 'nope'] + $good)->assertSessionHasErrors('inbound_date');
        $this->put('/inbound/' . $row->id, ['tank_id' => 999] + $good)->assertSessionHasErrors('tank_id');
        $this->put('/inbound/999999', $good)->assertNotFound();
    }

    public function testDeleteRemovesTheRecordAndTheStockGoesDown()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1', ['amount_kg' => '10000']);
        $this->inbound($this->tankD, '6.5', ['amount_kg' => '4000']);
        $second = FuelInbound::where('cost', 6.5)->first();
        $this->assertEquals(14000, TankLevels::all()->firstWhere('tank.id', $this->tankD->id)->inbound);

        $this->delete('/inbound/' . $second->id)->assertRedirect('/inbound');

        $this->assertNull(FuelInbound::find($second->id));
        $this->assertNotNull(FuelInbound::onlyTrashed()->find($second->id)); // kept as deleted
        $this->assertEquals(10000, TankLevels::all()->firstWhere('tank.id', $this->tankD->id)->inbound);
        $this->get('/inbound')->assertOk()->assertDontSee('6.5000');
    }

    public function testAnyRecordCanBeDeletedEvenOneWhoseCostIsTheDefaultCost()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');
        $this->inbound($this->tankD, '6.5');
        $first = FuelInbound::where('cost', 6.1)->first();

        $this->delete('/inbound/' . $first->id)->assertRedirect('/inbound')->assertSessionHasNoErrors();
        $this->assertNull(FuelInbound::find($first->id));
        $this->assertEquals(6.1, DefaultCost::get($this->diesel));                         // the default cost is not a flag of the record
    }

    public function testTheListShowsTheDefaultBadgeOnTheRecordsWhoseCostIsTheDefaultCost()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');
        $this->inbound($this->tankD, '6.5');
        $this->inbound($this->tankD, '6.1000', ['inbound_date' => '2026-09-21']);          // the same cost as the default: shown as default too

        $html = $this->get('/inbound')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, '<span class="badge badge-success">Default</span>'));
        $this->assertSame(1, substr_count($html, 'Make default cost</button>'));              // only the record that is not the default one has the button
    }

    public function testTheListHasAnActionsColumnForTheManagerAndTheAccountantOnly()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1');
        $this->inbound($this->tankD, '6.5');
        $row = FuelInbound::first();

        $page = $this->get('/inbound')->assertOk();
        $page->assertSee('Actions')->assertSee(route('inbound.edit', $row->id))->assertSee('Make default');
        $this->assertSame(2, substr_count($page->getContent(), '>Delete</button>')); // one for each record

        $page = $this->as('accountant')->get('/inbound')->assertOk();
        $page->assertSee('Actions')->assertSee(route('inbound.edit', $row->id));
        $this->get('/inbound/' . $row->id . '/edit')->assertOk();

        // the assistant and the cashier see nothing of the fuel inbounds, and so nothing of their cost
        $this->as('assistant')->get('/inbound')->assertForbidden();
        $this->get('/inbound/' . $row->id . '/edit')->assertForbidden();
        $this->put('/inbound/' . $row->id, ['tank_id' => $this->tankD->id, 'inbound_date' => '2026-09-20', 'amount_kg' => '1', 'cost' => '1'])->assertForbidden();
        $this->delete('/inbound/' . $row->id)->assertForbidden();
        $this->as('cashier')->get('/inbound')->assertForbidden();
        $this->assertNotNull(FuelInbound::find($row->id));
    }

    public function testInboundRemembersWhereTheFuelCameFrom()
    {
        $this->as('manager');
        $this->inbound($this->tankD, '6.1000', ['from_name' => 'Refinery No. 1'])->assertRedirect('/inbound');
        $row = FuelInbound::first();
        $this->assertSame('Refinery No. 1', $row->from_name);

        $this->get('/inbound')->assertSee('Refinery No. 1');
        $this->get('/inbound/' . $row->id . '/edit')->assertSee('Refinery No. 1');

        $this->put('/inbound/' . $row->id, ['tank_id' => $this->tankD->id, 'inbound_date' => '2026-09-20', 'amount_kg' => '10000', 'cost' => '6.1000', 'from_name' => 'Depot'])
            ->assertRedirect('/inbound');
        $this->assertSame('Depot', $row->fresh()->from_name);
        $this->put('/inbound/' . $row->id, ['tank_id' => $this->tankD->id, 'inbound_date' => '2026-09-20', 'amount_kg' => '10000', 'cost' => '6.1000', 'from_name' => str_repeat('x', 101)])
            ->assertSessionHasErrors('from_name');

        // it is optional
        $this->inbound($this->tankG, '7.0000')->assertRedirect('/inbound');
        $this->assertNull(FuelInbound::orderByDesc('id')->first()->from_name);

        // and the ticket inbound has no From any more
        $this->get('/ticket-inbound/create')->assertOk()->assertDontSee('f_from_name');
    }

    public function testTheInboundTableFiltersByDateFromCostAndNote()
    {
        $this->as('manager');
        $this->inbound($this->tankD, 6.5, ['inbound_date' => '2026-10-01', 'from_name' => 'Acme Oil', 'note' => 'first tanker']);
        $this->inbound($this->tankD, 6.75, ['inbound_date' => '2026-10-02', 'from_name' => 'Beta Fuels', 'note' => 'second tanker, late']);
        $this->inbound($this->tankG, 7, ['inbound_date' => '2026-10-02', 'from_name' => 'ACME OIL', 'note' => null]);
        $costs = function ($query) {
            return $this->get('/inbound' . $query)->assertOk()->viewData('rows')->map(function ($r) {
                return round((float) $r->cost, 2);
            })->all();
        };

        $this->assertSame([7.0, 6.75, 6.5], array_map('floatval', $costs('')));                       // everything, the newest day first, the last made first within a day

        $this->assertSame([6.5], $costs('?inbound_date=2026-10-01'));                                  // the day
        $this->assertSame([7.0, 6.75], $costs('?inbound_date=2026-10-02'));
        $this->assertSame([7.0, 6.5], $costs('?from_name=acme'));                                      // a part of the from, any case
        $this->assertSame([6.75], $costs('?note=late'));                                               // a part of the note
        $this->assertSame([6.75], $costs('?cost=6.75'));                                               // the cost per kg
        $this->assertSame([6.5], $costs('?cost=6.5'));
        $this->assertSame([], $costs('?cost=1'));
        $this->assertSame([6.5], $costs('?from_name=acme&inbound_date=2026-10-01&cost=6.5&note=first')); // together
        $this->assertSame([], $costs('?from_name=acme&note=late'));

        // the % and _ of a search are plain characters
        $this->assertSame([], $costs('?note=' . urlencode('%')));
        $this->get('/inbound?inbound_date=nope')->assertSessionHasErrors('inbound_date');
        $this->get('/inbound?cost=abc')->assertSessionHasErrors('cost');

        // the filters are in the head of the table and keep what was typed
        $html = $this->get('/inbound?from_name=acme&cost=6.5&inbound_date=2026-10-01&note=first')->assertOk()->getContent();
        $this->assertStringContainsString('name="inbound_date" form="inbound-filters" type="date" value="2026-10-01"', $html);
        $this->assertStringContainsString('name="from_name" form="inbound-filters" type="text" maxlength="100" placeholder="Search" value="acme"', $html);
        $this->assertStringContainsString('name="cost" form="inbound-filters" type="number"', $html);
        $this->assertStringContainsString('value="6.5"', $html);
        $this->assertStringContainsString('name="note" form="inbound-filters" type="text" maxlength="255" placeholder="Search" value="first"', $html);
        $this->assertStringContainsString('id="inbound-filters"', $html);
        $this->get('/inbound?note=nothing')->assertOk()->assertSee('No data');
    }

    public function testTheInboundTableIsPagedByFiftyAndKeepsTheFilters()
    {
        $this->as('manager');
        for ($i = 1; $i <= 52; $i++) {
            FuelInbound::forceCreate([
                'tank_id' => $this->tankD->id, 'fuel_type' => $this->diesel, 'amount_kg' => 100, 'cost' => 6,
                'inbound_date' => '2026-09-' . str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT), 'from_name' => 'Acme', 'user_id' => 1,
            ]);
        }

        $first = $this->get('/inbound?from_name=acme')->assertOk();
        $this->assertCount(50, $first->viewData('rows'));
        $this->assertSame(52, $first->viewData('rows')->total());
        $this->assertStringContainsString('from_name=acme&amp;page=2', $first->getContent());          // the filter stays in the links
        $this->assertCount(2, $this->get('/inbound?from_name=acme&page=2')->viewData('rows'));
        $this->assertCount(0, $this->get('/inbound?from_name=nobody')->viewData('rows'));
        $this->assertSame(52, $this->get('/inbound')->viewData('rows')->total());
    }}
