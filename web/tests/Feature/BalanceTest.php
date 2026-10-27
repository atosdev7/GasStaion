<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Fuel\TankBalance;
use App\Support\Fuel\CostChoices;
use App\Support\Fuel\DefaultCost;
use App\Models\Sales\Sale;
use App\Models\Fuel\Tank;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceTest extends TestCase
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

    // a balance is added like a fuel inbound: the tank, the cost, the balance, the day and a note; a tank can hold several, one for each cost
    public function testTankBalancesAreAddedChangedAndDeletedLikeInbounds()
    {
        $t1 = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        $t2 = Tank::forceCreate(['name' => 'T2', 'fuel_type' => 'A']);

        $this->as('accountant')->post('/balances/tanks', [
            'tank_id' => $t1->id, 'cost' => 7.25, 'balance' => 1200.5, 'balance_date' => '2026-10-01', 'note' => ' counted by the dip ',
        ])->assertRedirect('/balances/tanks')->assertSessionHasNoErrors();
        $this->post('/balances/tanks', ['tank_id' => $t1->id, 'cost' => 6.5, 'balance' => 300, 'balance_date' => '2026-09-20'])->assertSessionHasNoErrors();   // another cost, the same tank
        $this->post('/balances/tanks', ['tank_id' => $t2->id, 'cost' => 8, 'balance' => 50, 'balance_date' => '2026-10-02'])->assertSessionHasNoErrors();
        $this->assertSame(3, TankBalance::count());
        $this->assertSame(2, TankBalance::where('tank_id', $t1->id)->count());

        $row = TankBalance::where('tank_id', $t1->id)->where('balance', 1200.5)->first();
        $this->assertEquals(7.25, $row->cost);
        $this->assertSame('D', $row->fuel_type);
        $this->assertSame('2026-10-01', substr((string) $row->balance_date, 0, 10));
        $this->assertSame('counted by the dip', $row->note);
        $this->assertSame('manual', $row->source);
        $this->assertNotNull($row->user_id);
        $this->assertEquals(1500.5, \App\Support\Dashboard\TankLevels::all()->firstWhere('tank.id', $t1->id)->current);       // the balances add up

        $this->get('/balances/tanks/' . $row->id . '/edit')->assertOk()->assertSee('value="7.25"', false);
        $this->put('/balances/tanks/' . $row->id, ['tank_id' => $t1->id, 'cost' => 7.5, 'balance' => 1100, 'balance_date' => '2026-10-03', 'note' => ''])
            ->assertRedirect('/balances/tanks')->assertSessionHasNoErrors();
        $row = $row->fresh();
        $this->assertEquals(7.5, $row->cost);
        $this->assertEquals(1100, $row->balance);
        $this->assertNull($row->note);

        $this->delete('/balances/tanks/' . $row->id)->assertRedirect('/balances/tanks');
        $this->assertNull(TankBalance::find($row->id));
        $this->assertSame(2, TankBalance::count());
    }

    // a tank has one row for each cost: a balance entered at a cost it already has is added to that row
    public function testABalanceAtACostTheTankAlreadyHasIsAddedToThatRow()
    {
        $t1 = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'A']);
        $t2 = Tank::forceCreate(['name' => 'T2', 'fuel_type' => 'A']);
        $this->as('accountant')->post('/balances/tanks', ['tank_id' => $t1->id, 'cost' => 1, 'balance' => 5000, 'balance_date' => '2026-10-20', 'note' => 'dip'])->assertSessionHasNoErrors();
        $this->post('/balances/tanks', ['tank_id' => $t1->id, 'cost' => 1.2, 'balance' => 2000, 'balance_date' => '2026-10-27'])->assertSessionHasNoErrors();

        $this->post('/balances/tanks', ['tank_id' => $t1->id, 'cost' => '1.0000', 'balance' => 230, 'balance_date' => '2026-10-27', 'note' => 'top up'])
            ->assertRedirect('/balances/tanks')->assertSessionHas('status');
        $this->assertStringContainsString('so the 230.00 kg were added to it: it is now 5,230.00 kg', session('status'));

        $rows = TankBalance::where('tank_id', $t1->id)->orderBy('cost')->get();
        $this->assertCount(2, $rows);                                                                    // not a third row
        $this->assertEquals([1, 5230], [$rows[0]->cost, $rows[0]->balance]);
        $this->assertSame('2026-10-27', substr((string) $rows[0]->balance_date, 0, 10));                  // the newest day
        $this->assertSame('dip; top up', $rows[0]->note);
        $this->assertEquals(2000, $rows[1]->balance);                                                     // the other cost is untouched

        // the same cost in another tank is a row of its own
        $this->post('/balances/tanks', ['tank_id' => $t2->id, 'cost' => 1, 'balance' => 100, 'balance_date' => '2026-10-27']);
        $this->assertSame(3, TankBalance::count());

        // a row changed to a cost the tank already has goes into that row
        $second = $rows[1];
        $this->put('/balances/tanks/' . $second->id, ['tank_id' => $t1->id, 'cost' => 1, 'balance' => 1800, 'balance_date' => '2026-10-26', 'note' => ''])->assertSessionHas('status');
        $this->assertNull(TankBalance::find($second->id));
        $this->assertEquals(7030, TankBalance::find($rows[0]->id)->balance);
        $this->assertSame(2, TankBalance::count());
    }

    public function testATankBalanceNeedsItsCostItsBalanceAndADayThatHasPassed()
    {
        $t1 = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        $good = ['tank_id' => $t1->id, 'cost' => 7, 'balance' => 100, 'balance_date' => '2026-10-02'];
        $this->as('accountant');

        foreach ([['cost' => ''], ['balance' => ''], ['balance_date' => ''], ['tank_id' => ''], ['tank_id' => 99999], ['balance' => -1], ['cost' => -1],
                  ['balance_date' => today()->addDay()->toDateString()], ['balance_date' => 'soon'], ['note' => str_repeat('x', 256)]] as $bad) {
            $this->post('/balances/tanks', $bad + $good)->assertSessionHasErrors();
        }
        $this->assertSame(0, TankBalance::count());

        // the page lists them with the fields of an inbound
        $this->post('/balances/tanks', ['note' => 'dip', 'cost' => '7.5'] + $good);
        $page = $this->get('/balances/tanks')->assertOk()->getContent();
        foreach (['name="tank_id"', 'name="cost"', 'name="balance"', 'name="balance_date"', 'name="note"', '7.5000', '100.00', '2026-10-02', 'dip', 'Entered'] as $part) {
            $this->assertStringContainsString($part, $page);
        }
    }

    public function testAnotherBackupsBalanceKeepsItsTankItsBalanceAndItsDayAndCannotBeDeleted()
    {
        $t1 = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        $row = TankBalance::forceCreate([
            'tank_id' => $t1->id, 'balance' => 400, 'cost' => null, 'balance_date' => '2026-10-01', 'source' => 'backup', 'backup_id' => 1, 'note' => 'carried forward',
        ]);
        $this->as('accountant');

        // only the cost (it was not known) and the note can be written; the rest of the form is ignored
        $this->put('/balances/tanks/' . $row->id, ['tank_id' => 999, 'balance' => 1, 'balance_date' => '2026-01-01', 'cost' => 6.2, 'note' => 'cost found'])->assertSessionHasNoErrors();
        $row = $row->fresh();
        $this->assertEquals(6.2, $row->cost);
        $this->assertEquals(400, $row->balance);
        $this->assertSame($t1->id, $row->tank_id);
        $this->assertSame('2026-10-01', substr((string) $row->balance_date, 0, 10));
        $this->assertSame('cost found', $row->note);

        $this->delete('/balances/tanks/' . $row->id)->assertSessionHasErrors('balance');
        $this->assertNotNull(TankBalance::find($row->id));
        $this->get('/balances/tanks')->assertOk()->assertSee('A backup');
    }

    // the default cost is a value of its own: the first cost of a fuel type sets it, and the button of a balance copies the cost of the balance into it
    public function testTheDefaultCostComesFromTheFirstBalanceAndTheButtonOfABalanceChangesIt()
    {
        $d1 = Tank::forceCreate(['name' => 'D1', 'fuel_type' => 'D']);
        $this->assertNull(DefaultCost::get('D'));                                                                 // nothing yet

        $this->as('accountant')->post('/balances/tanks', ['tank_id' => $d1->id, 'cost' => 6, 'balance' => 300, 'balance_date' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->post('/balances/tanks', ['tank_id' => $d1->id, 'cost' => 8, 'balance' => 100, 'balance_date' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->assertEquals(6, DefaultCost::get('D'));                                                            // the first cost; the second does not change it

        $second = TankBalance::where('cost', 8)->first();
        $html = $this->get('/balances/tanks')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<span class="badge badge-success">Default</span>'));            // the row whose cost is the default
        $this->assertSame(1, substr_count($html, 'Make default cost</button>'));                                   // the other one has the button

        $this->post('/balances/tanks/' . $second->id . '/default')->assertRedirect('/balances/tanks')->assertSessionHas('status');
        $this->assertEquals(8, DefaultCost::get('D'));
        $this->assertEquals(8, TankBalance::where('cost', 8)->value('cost'));                                      // the balance itself is not changed

        // a balance with no cost has nothing to copy
        $none = TankBalance::forceCreate(['tank_id' => $d1->id, 'balance' => 5, 'cost' => null, 'balance_date' => '2026-10-02', 'source' => 'backup']);
        $this->post('/balances/tanks/' . $none->id . '/default')->assertSessionHasErrors('balance');
        $this->assertEquals(8, DefaultCost::get('D'));

        // the box of the form sets it when a balance is added
        $this->post('/balances/tanks', ['tank_id' => $d1->id, 'cost' => 9, 'balance' => 10, 'balance_date' => '2026-10-03', 'is_default' => 1]);
        $this->assertEquals(9, DefaultCost::get('D'));
    }

    // the cost field of a sale is a list of the costs of the fuel in the tanks, and the default cost of the fuel type
    public function testTheCostChoicesAreTheCostsOfTheFuelInTheTanksAndTheDefaultCost()
    {
        $tank = Tank::forceCreate(['name' => 'D1', 'fuel_type' => 'D', 'capacity_kg' => 5000]);
        TankBalance::create(['tank_id' => $tank->id, 'balance' => 400, 'cost' => 6, 'balance_date' => '2026-10-01']);
        \App\Models\Fuel\FuelInbound::forceCreate(['tank_id' => $tank->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 9, 'inbound_date' => '2026-10-05', 'user_id' => 1]);
        DefaultCost::set('D', 7.5);                                                                               // not a cost of the fuel in the tank: listed all the same

        $choices = CostChoices::all()['D'];
        $this->assertEquals([[9.0, 100.0, false], [6.0, 400.0, false], [7.5, 0.0, true]], array_map(function ($c) {
            return [$c['cost'], $c['kg'], $c['default']];
        }, $choices));
        $this->assertSame([], CostChoices::all()['A']);                                                           // no tank holds gasoline

        // an old sale's cost that is no longer in the tanks is listed too
        $this->assertCount(4, CostChoices::forFuel('D', 5.25));
        $this->assertCount(3, CostChoices::forFuel('D', 6));                                                      // one that is there already is not listed twice

        // the forms get the list (a user who may see costs only)
        $this->as('accountant')->get('/ticket-sales/create')->assertOk()->assertViewHas('costChoices', function ($choices) {
            return count($choices['D']) === 3;
        });
        $this->as('assistant')->get('/ticket-sales/create')->assertOk()->assertViewHas('costChoices', []);
    }

    public function testTheBalanceOfATankKeepsTheFuelTypeOfTheTankAndEachTankHasASummary()
    {
        $diesel = Tank::forceCreate(['name' => 'D1', 'fuel_type' => 'D', 'capacity_kg' => 1000]);
        $gasoline = Tank::forceCreate(['name' => 'A1', 'fuel_type' => 'A']);
        $this->as('accountant')->post('/balances/tanks', ['tank_id' => $diesel->id, 'balance' => 400, 'cost' => 6, 'balance_date' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->assertSame('D', TankBalance::where('tank_id', $diesel->id)->value('fuel_type'));               // kept on the row, as on a fuel inbound
        $this->assertSame('tank_balances', (new TankBalance)->getTable());

        \App\Models\Fuel\FuelInbound::forceCreate(['tank_id' => $diesel->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 9, 'inbound_date' => '2026-10-05', 'user_id' => 1]);

        $page = $this->get('/balances/tanks')->assertOk()->getContent();
        $this->assertStringContainsString('Summary of each tank', $page);
        preg_match('/<tr class="summary-tank-' . $diesel->id . '">(.*?)<\/tr>/s', $page, $m);
        $cells = array_map('trim', array_map('strip_tags', preg_split('/<\/td>/', $m[1], -1, PREG_SPLIT_NO_EMPTY)));
        $this->assertSame(['D1', 'Diesel', '400.00', '100.00', '0.00', '500.00', '1,000.00', '50.0%'], array_slice($cells, 0, 8));
        preg_match('/<tr class="summary-tank-' . $gasoline->id . '">(.*?)<\/tr>/s', $page, $m);
        $cells = array_map('trim', array_map('strip_tags', preg_split('/<\/td>/', $m[1], -1, PREG_SPLIT_NO_EMPTY)));
        $this->assertSame(['A1', 'Gasoline', '0.00', '0.00', '0.00', '0.00', '-', '-'], array_slice($cells, 0, 8));    // no balance entered for it
    }

    public function testTheTankStockSummaryValuesTheOpeningStockWithItsCost()
    {
        $tank = Tank::forceCreate(['name' => 'D1', 'fuel_type' => 'D', 'capacity_kg' => 5000]);
        TankBalance::create(['tank_id' => $tank->id, 'balance' => 400, 'cost' => 6, 'balance_date' => '2026-10-01']);
        $this->as('accountant');

        $fuel = \App\Support\Fuel\TankStockSummary::build()['fuels']['D'];
        $this->assertEquals(400, $fuel['balance']);
        $this->assertSame(0.0, $fuel['unknown_kg']);                                                              // valued, not unknown
        $this->assertEquals(2400, $fuel['value']);
        $this->assertEquals(6, $fuel['average']);
        $this->assertTrue($fuel['layers'][0]['from_balance']);
        $this->assertSame('2026-10-01', $fuel['layers'][0]['inbound_date']);
        $this->get('/tank-stock')->assertOk()->assertSee('Tank balance');

        // fuel that came in later is taken first (it is the newest); what is left of the tank is the opening stock
        \App\Models\Fuel\FuelInbound::forceCreate([
            'tank_id' => $tank->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 9, 'inbound_date' => '2026-10-05', 'user_id' => 1,
        ]);
        $fuel = \App\Support\Fuel\TankStockSummary::build()['fuels']['D'];
        $this->assertEquals(500, $fuel['balance']);
        $this->assertEquals(3300, $fuel['value']);                                                                 // 100 x 9 + 400 x 6
        $this->assertSame(0.0, $fuel['unknown_kg']);
    }

    // an opening debt and an opening ticket loan have the day they count from ("as of", today when not sent) and a comment
    public function testOpeningDebtsAndLoansHaveAnAsOfDateAndAComment()
    {
        $this->as('accountant')->post('/balances/debts', [
            'customer_id' => $this->customer->id, 'amount' => 300, 'payment_method' => 'cash', 'debt_date' => '2026-09-15', 'comment' => ' old ledger, page 4 ',
        ])->assertRedirect('/balances/debts')->assertSessionHasNoErrors();
        $this->post('/balances/debts', ['customer_id' => $this->customer->id, 'amount' => 50, 'payment_method' => 'bank'])->assertSessionHasNoErrors();

        $old = Debt::where('amount', 300)->first();
        $this->assertSame('2026-09-15', substr((string) $old->debt_date, 0, 10));
        $this->assertSame('old ledger, page 4', $old->comment);
        $this->assertSame('2026-09-15', \App\Support\Debts\CustomerBalances::debtDay($old));          // the statements and the aging count it from that day
        $this->assertSame(today()->toDateString(), substr((string) Debt::where('amount', 50)->value('debt_date'), 0, 10));
        $this->post('/balances/debts', ['customer_id' => $this->customer->id, 'amount' => 1, 'payment_method' => 'cash', 'debt_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('debt_date');
        $this->post('/balances/debts', ['customer_id' => $this->customer->id, 'amount' => 1, 'payment_method' => 'cash', 'debt_date' => 'soon'])->assertSessionHasErrors('debt_date');
        $this->get('/balances/debts')->assertOk()->assertSee('2026-09-15')->assertSee('old ledger, page 4')->assertSee('As of');

        $this->post('/balances/loans', [
            'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 40, 'date' => '2026-09-10', 'comment' => 'tickets of August',
        ])->assertRedirect('/balances/loans')->assertSessionHasNoErrors();
        $loan = TicketLoan::where('amount_kg', 40)->first();
        $this->assertSame('2026-09-10', substr((string) $loan->date, 0, 10));
        $this->assertSame('2026-09-10', substr((string) $loan->loaned_at, 0, 10));
        $this->assertSame('tickets of August', $loan->comment);
        $this->post('/balances/loans', ['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 5])->assertSessionHasNoErrors();
        $this->assertSame(today()->toDateString(), substr((string) TicketLoan::where('amount_kg', 5)->value('date'), 0, 10));
        $this->post('/balances/loans', ['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 1, 'date' => today()->addDay()->toDateString()])->assertSessionHasErrors('date');
        $this->get('/balances/loans')->assertOk()->assertSee('2026-09-10')->assertSee('tickets of August');
    }

    // the rows of the opening debts and loans have actions: edit (the whole row while nothing is paid or settled, then the day and the comment) and delete
    public function testOpeningDebtsAndLoansCanBeEditedFromTheirTables()
    {
        $other = Customer::forceCreate(['name' => 'Beta']);
        $this->as('accountant')->post('/balances/debts', ['customer_id' => $this->customer->id, 'amount' => 300, 'payment_method' => 'cash', 'debt_date' => '2026-09-15']);
        $debt = Debt::first();

        $page = $this->get('/balances/debts')->assertOk()->getContent();
        $this->assertStringContainsString('Actions', $page);
        $this->assertStringContainsString(route('balances.debts.edit', $debt->id), $page);
        $this->get('/balances/debts/' . $debt->id . '/edit')->assertOk()->assertSee('value="2026-09-15"', false)->assertDontSee('readonly');

        $this->put('/balances/debts/' . $debt->id, [
            'customer_id' => $other->id, 'amount' => 250, 'payment_method' => 'bank', 'debt_date' => '2026-09-10', 'comment' => 'corrected',
        ])->assertRedirect('/balances/debts')->assertSessionHasNoErrors();
        $debt = $debt->fresh();
        $this->assertSame([$other->id, 250.0, 'bank', '2026-09-10', 'corrected'], [(int) $debt->customer_id, (float) $debt->amount, $debt->payment_method, substr((string) $debt->debt_date, 0, 10), $debt->comment]);
        $this->put('/balances/debts/' . $debt->id, ['customer_id' => $other->id, 'amount' => 0, 'payment_method' => 'bank', 'debt_date' => '2026-09-10'])->assertSessionHasErrors('amount');
        $this->put('/balances/debts/' . $debt->id, ['customer_id' => $other->id, 'amount' => 5, 'payment_method' => 'bank', 'debt_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('debt_date');

        // paid against: only the day and the comment (what else is sent is not taken)
        DebtPayment::forceCreate(['customer_id' => $other->id, 'debt_id' => $debt->id, 'amount' => 20, 'payment_method' => 'cash', 'paid_date' => '2026-09-12', 'date' => '2026-09-12', 'user_id' => 1]);
        $this->get('/balances/debts/' . $debt->id . '/edit')->assertOk()->assertSee('A payment was made against this debt');
        $this->put('/balances/debts/' . $debt->id, ['customer_id' => $this->customer->id, 'amount' => 999, 'payment_method' => 'cash', 'debt_date' => '2026-09-11', 'comment' => 'dated again'])->assertSessionHasNoErrors();
        $debt = $debt->fresh();
        $this->assertSame([$other->id, 250.0, 'bank', '2026-09-11', 'dated again'], [(int) $debt->customer_id, (float) $debt->amount, $debt->payment_method, substr((string) $debt->debt_date, 0, 10), $debt->comment]);

        // a debt made by a sale is not one of them
        $sale = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => (string) \Illuminate\Support\Str::uuid(), 'amount' => 10, 'payment_method' => 'cash', 'user_id' => 1]);
        $this->get('/balances/debts/' . $sale->id . '/edit')->assertNotFound();

        // the loans
        $this->post('/balances/loans', ['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 40, 'date' => '2026-09-10']);
        $loan = TicketLoan::whereNull('sale_id')->first();
        $this->assertStringContainsString(route('balances.loans.edit', $loan->id), $this->get('/balances/loans')->assertOk()->getContent());
        $this->put('/balances/loans/' . $loan->id, ['customer_id' => $other->id, 'fuel_type' => 'A', 'amount_kg' => 35, 'date' => '2026-09-08', 'comment' => 'recounted'])
            ->assertRedirect('/balances/loans')->assertSessionHasNoErrors();
        $loan = $loan->fresh();
        $this->assertSame([$other->id, 'A', 35.0, '2026-09-08', '2026-09-08', 'recounted'],
            [(int) $loan->customer_id, $loan->fuel_type, (float) $loan->amount_kg, substr((string) $loan->date, 0, 10), substr((string) $loan->loaned_at, 0, 10), $loan->comment]);
        $this->put('/balances/loans/' . $loan->id, ['customer_id' => $other->id, 'fuel_type' => 'A', 'amount_kg' => 0, 'date' => '2026-09-08'])->assertSessionHasErrors('amount_kg');

        TicketLoanSettlement::forceCreate(['loan_id' => $loan->id, 'ticket_barcode' => '0000006000000001', 'amount_kg' => 5, 'settled_at' => now(), 'date' => today()->toDateString(), 'user_id' => 1]);
        $this->get('/balances/loans/' . $loan->id . '/edit')->assertOk()->assertSee('Tickets were given back against this loan');
        $this->put('/balances/loans/' . $loan->id, ['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 99, 'date' => '2026-09-07', 'comment' => 'again'])->assertSessionHasNoErrors();
        $loan = $loan->fresh();
        $this->assertSame([$other->id, 'A', 35.0, '2026-09-07', 'again'], [(int) $loan->customer_id, $loan->fuel_type, (float) $loan->amount_kg, substr((string) $loan->date, 0, 10), $loan->comment]);
    }

    public function testOpeningDebtIsAPlainDebtThatCanBePaid()
    {
        $this->as('accountant')->post('/balances/debts', [
            'customer_id' => $this->customer->id, 'amount' => 500, 'payment_method' => 'bank',
        ])->assertRedirect('/balances/debts');

        $debt = Debt::first();
        $this->assertNull($debt->sale_id);
        $this->assertEquals(500, $debt->amount);

        $this->get('/payments')->assertSee(__('report.opening_balance'))->assertSee('500.00');

        $this->post('/payments', [
            'customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 200,
            'payment_method' => 'bank', 'paid_date' => '2026-09-27',
        ])->assertRedirect('/payments');
        $this->get('/payments')->assertSee('300.00'); // balance
    }

    public function testOpeningLoanIsAPlainLoanThatCanBeSettled()
    {
        $this->as('accountant')->post('/balances/loans', [
            'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 40,
        ])->assertRedirect('/balances/loans');

        $loan = TicketLoan::first();
        $this->assertNull($loan->sale_id);
        $this->assertSame('D', $loan->fuel_type);
        $this->assertEquals(40, $loan->amount_kg);

        $html = $this->get('/loans/create')->assertOk()->getContent();
        $this->assertStringContainsString('value="' . $loan->id . '"', $html);
    }

    public function testDeleteOpeningDebtOnlyWhileUnpaid()
    {
        $free = Debt::forceCreate(['customer_id' => $this->customer->id, 'amount' => 10, 'payment_method' => 'cash', 'user_id' => 1]);
        $paid = Debt::forceCreate(['customer_id' => $this->customer->id, 'amount' => 20, 'payment_method' => 'cash', 'user_id' => 1]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => $paid->id, 'amount' => 5,
            'payment_method' => 'cash', 'paid_date' => '2026-09-27', 'user_id' => 1,
        ]);

        $this->as('accountant')->delete('/balances/debts/' . $free->id)->assertSessionHas('status');
        $this->assertSoftDeleted('debts', ['id' => $free->id]);

        $this->delete('/balances/debts/' . $paid->id)->assertSessionHasErrors('balance');
        $this->assertNotNull(Debt::find($paid->id));
    }

    public function testDeleteOpeningLoanOnlyWhileUnsettled()
    {
        $loan = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 10, 'settled_amount_kg' => 5, 'user_id' => 1,
        ]);
        TicketLoanSettlement::forceCreate([
            'loan_id' => $loan->id, 'ticket_barcode' => '0000006000000001', 'amount_kg' => 5, 'settled_at' => now(), 'user_id' => 1,
        ]);

        $this->as('accountant')->delete('/balances/loans/' . $loan->id)->assertSessionHasErrors('balance');
        $this->assertNotNull(TicketLoan::find($loan->id));
    }

    public function testASaleDebtCannotBeDeletedHere()
    {
        $sale = Sale::forceCreate([
            'sale_date' => today()->toDateString(), 'customer_id' => $this->customer->id, 'sale_type' => 'ticket', 'fuel_type' => 'D',
            'price' => 2, 'amount_kg' => 5, 'payment_method' => 'cash', 'total_amount' => 10,
            'paid_amount' => 0, 'unpaid_amount' => 10, 'usage' => 'sale', 'user_id' => 1,
        ]);
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $sale->id, 'amount' => 10, 'payment_method' => 'cash', 'user_id' => 1,
        ]);

        $this->as('accountant')->delete('/balances/debts/' . $debt->id)->assertNotFound();
        $this->assertNotNull(Debt::find($debt->id));
    }

    public function testPermissions()
    {
        foreach (['/balances/tanks', '/balances/debts', '/balances/loans'] as $path) {
            $this->as('manager')->get($path)->assertOk();
            $this->as('accountant')->get($path)->assertOk();
            $this->as('assistant')->get($path)->assertForbidden();
            $this->as('cashier')->get($path)->assertForbidden();
        }
        $this->as('accountant')->get('/balances')->assertRedirect('/balances/tanks');             // the old address of the single page
        $this->as('assistant')->get('/balances')->assertForbidden();
        $this->post('/balances/debts', ['customer_id' => $this->customer->id, 'amount' => 1, 'payment_method' => 'cash'])
            ->assertForbidden();
    }

    public function testInitializeIsAMenuWithTanksDebtsAndTicketLoansEachItsOwnPage()
    {
        $this->assertSame('Initialize', __('menu.group_init'));
        $t1 = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        $debt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => null, 'amount' => 75, 'payment_method' => 'cash', 'user_id' => 1]);
        $loan = TicketLoan::forceCreate(['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 33, 'user_id' => 1]);

        // the menu: Initialize with Tanks, Debts and Ticket Loans in this order
        $html = $this->as('accountant')->get('/balances/tanks')->assertOk()->getContent();
        $group = strpos($html, '>Initialize<');
        $this->assertNotFalse($group);
        $last = $group;
        foreach (['/balances/tanks', '/balances/debts', '/balances/loans'] as $path) {
            $at = strpos($html, 'href="' . url($path) . '"', $last);
            $this->assertNotFalse($at, $path);
            $last = $at;
        }
        $this->assertStringNotContainsString('Config Init Values', $html);
        $this->assertStringNotContainsString('Opening Balances', $html);

        // each page has its own part and nothing of the others; the current item of the menu is the page itself
        $tanks = $this->as('accountant')->get('/balances/tanks')->assertOk()->getContent();
        $this->assertStringContainsString('name="tank_id"', $tanks);
        $this->assertStringNotContainsString('f_debt_customer', $tanks);
        $this->assertStringNotContainsString('f_loan_customer', $tanks);
        $this->assertSame(1, preg_match_all('/<a href="' . preg_quote(url('/balances'), '/') . '\/[a-z]+" class="nav-link active">/', $tanks));
        $this->assertRegExp('/<a href="' . preg_quote(url('/balances/tanks'), '/') . '" class="nav-link active">/', $tanks);

        $debts = $this->as('accountant')->get('/balances/debts')->assertOk()->getContent();
        $this->assertStringContainsString('f_debt_customer', $debts);
        $this->assertStringContainsString('75.00', $debts);
        $this->assertStringNotContainsString('name="tank_id"', $debts);
        $this->assertStringNotContainsString('f_loan_customer', $debts);
        $this->assertStringContainsString(route('balances.debts.destroy', $debt->id), $debts);
        $this->assertRegExp('/<a href="' . preg_quote(url('/balances/debts'), '/') . '" class="nav-link active">/', $debts);

        $loans = $this->as('accountant')->get('/balances/loans')->assertOk()->getContent();
        $this->assertStringContainsString('f_loan_customer', $loans);
        $this->assertStringContainsString('33.00', $loans);
        $this->assertStringNotContainsString('f_debt_customer', $loans);
        $this->assertStringContainsString(route('balances.loans.destroy', $loan->id), $loans);
        $this->assertRegExp('/<a href="' . preg_quote(url('/balances/loans'), '/') . '" class="nav-link active">/', $loans);
    }

    public function testDeletingAnOpeningRecordComesBackToItsOwnPage()
    {
        $debt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => null, 'amount' => 10, 'payment_method' => 'cash', 'user_id' => 1]);
        $loan = TicketLoan::forceCreate(['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 5, 'user_id' => 1]);

        $this->as('accountant')->delete('/balances/debts/' . $debt->id)->assertRedirect('/balances/debts');
        $this->as('accountant')->delete('/balances/loans/' . $loan->id)->assertRedirect('/balances/loans');
    }

    public function testAnOpeningRemainderIsATicketWithNoSupplyBehindIt()
    {
        $this->as('accountant')->get('/balances/remainders')->assertOk()->assertSee('f_rem_fuel');

        $this->as('accountant')->post('/balances/remainders', ['fuel_type' => 'D', 'amount_kg' => '3.5', 'remainder_date' => '2026-01-05', 'comment' => 'brought by Mr Li'])
            ->assertRedirect('/balances/remainders')->assertSessionHas('status');

        $remainder = \App\Models\Supplements\Remainder::first();
        $this->assertNull($remainder->supplement_id);
        $this->assertEquals(3.5, $remainder->amount_kg);
        $this->assertSame('brought by Mr Li', $remainder->comment);
        $this->assertSame('2026-01-05', substr((string) $remainder->remainder_date, 0, 10));
        $ticket = \App\Models\Tickets\Ticket::find($remainder->ticket_barcode);
        $this->assertSame(config('ticket_statuses.sale'), (int) $ticket->status);       // already sold, ready to be supplied
        $this->assertSame(\App\Support\Tickets\Barcode::posCode('remainder', $ticket->fuel_type), $ticket->code);
        $this->assertEquals(3.5, $ticket->amount_kg);
        $this->assertFalse((bool) $ticket->synced);                                      // the Smart210 pulls it

        $this->as('accountant')->get('/balances/remainders')->assertOk()->assertSee($remainder->ticket_barcode)->assertSee('brought by Mr Li');
        $this->as('accountant')->get('/remainders')->assertOk()->assertSee($remainder->ticket_barcode)->assertSee(__('supplements.remainder_opening'));
    }

    public function testAnOpeningRemainderIsValidatedAndOnlyForThoseWhoManageBalances()
    {
        $this->as('accountant')->post('/balances/remainders', ['fuel_type' => 'X', 'amount_kg' => '0'])->assertSessionHasErrors(['fuel_type', 'amount_kg']);
        $this->as('accountant')->post('/balances/remainders', ['fuel_type' => 'D', 'amount_kg' => '1', 'remainder_date' => '2999-01-01'])->assertSessionHasErrors('remainder_date');
        $this->as('accountant')->post('/balances/remainders', ['fuel_type' => 'D', 'amount_kg' => '1', 'comment' => str_repeat('x', 256)])->assertSessionHasErrors('comment');
        $this->assertSame(0, \App\Models\Supplements\Remainder::count());

        $this->as('cashier')->get('/balances/remainders')->assertForbidden();
        $this->as('cashier')->post('/balances/remainders', ['fuel_type' => 'D', 'amount_kg' => '1'])->assertForbidden();
    }
}
