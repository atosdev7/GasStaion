<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Debts\Debt;
use App\Models\Debts\TicketLoan;
use App\Models\Fuel\Tank;
use App\Models\Fuel\TankBalance;
use App\Models\Sales\Customer;
use App\Models\Supplements\Remainder;
use App\Models\Tickets\OpeningTicket;
use App\Support\Admin\OpeningLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The admin locks the opening balances when the live setup is done: after that they are changed by the system only (a backup rolls the tank balances forward).
class OpeningLockTest extends TestCase
{
    use RefreshDatabase;

    private $customer;
    private $tank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::forceCreate(['name' => 'ACME']);
        $this->tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function counts()
    {
        return [Debt::count(), TicketLoan::count(), TankBalance::count(), Remainder::count()];
    }

    public function testOnlyTheAdminLocksAndUnlocks()
    {
        $this->as('accountant')->post('/balances/lock')->assertStatus(403);
        $this->post('/balances/unlock')->assertStatus(403);
        $this->assertFalse(OpeningLock::isLocked());

        $this->as('admin')->post('/balances/lock')->assertRedirect('/balances/go-live')->assertSessionHas('status');
        $this->assertTrue(OpeningLock::isLocked());
        $this->get('/balances/go-live')->assertOk()->assertSee('have been locked since');

        $this->post('/balances/unlock')->assertRedirect('/balances/go-live');
        $this->assertFalse(OpeningLock::isLocked());
        $this->get('/balances/go-live')->assertOk()->assertSee('are open');
    }

    public function testALockedOpeningBalanceCannotBeAddedChangedOrDeletedByHand()
    {
        // the rows that exist before the lock
        $this->as('accountant');
        $this->post('/balances/tanks', ['tank_id' => $this->tank->id, 'cost' => 7, 'balance' => 100, 'balance_date' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->post('/balances/debts', ['customer_id' => $this->customer->id, 'amount' => 70, 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $this->post('/balances/loans', ['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 40])->assertSessionHasNoErrors();
        $tankRow = TankBalance::first();
        $debt = Debt::first();
        $loan = TicketLoan::first();
        $before = $this->counts();

        $this->as('admin')->post('/balances/lock');
        $this->as('accountant');

        $this->post('/balances/tanks', ['tank_id' => $this->tank->id, 'cost' => 8, 'balance' => 5, 'balance_date' => '2026-10-02'])->assertSessionHasErrors('balance');
        $this->put('/balances/tanks/' . $tankRow->id, ['tank_id' => $this->tank->id, 'cost' => 7, 'balance' => 999, 'balance_date' => '2026-10-01'])->assertSessionHasErrors('balance');
        $this->delete('/balances/tanks/' . $tankRow->id)->assertSessionHasErrors('balance');
        $this->post('/balances/debts', ['customer_id' => $this->customer->id, 'amount' => 5, 'payment_method' => 'cash'])->assertSessionHasErrors('balance');
        $this->put('/balances/debts/' . $debt->id, ['debt_date' => '2026-10-01', 'customer_id' => $this->customer->id, 'amount' => 1, 'payment_method' => 'cash'])->assertSessionHasErrors('balance');
        $this->delete('/balances/debts/' . $debt->id)->assertSessionHasErrors('balance');
        $this->post('/balances/loans', ['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 5])->assertSessionHasErrors('balance');
        $this->put('/balances/loans/' . $loan->id, ['date' => '2026-10-01', 'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 1])->assertSessionHasErrors('balance');
        $this->delete('/balances/loans/' . $loan->id)->assertSessionHasErrors('balance');
        $this->post('/balances/remainders', ['fuel_type' => 'D', 'amount_kg' => 3])->assertSessionHasErrors('balance');

        $this->assertSame($before, $this->counts());
        $this->assertEquals(100, $tankRow->fresh()->balance);
        $this->assertEquals(70, $debt->fresh()->amount);
        $this->assertEquals(40, $loan->fresh()->amount_kg);

        // the pages say so
        $this->get('/balances/debts')->assertOk()->assertSee('are locked');
        $this->get('/balances/tanks')->assertOk()->assertSee('are locked');

        // what a backup wrote may still get its cost: it is not a hand-made balance; and the old paper tickets are not locked
        $made = TankBalance::forceCreate(['tank_id' => $this->tank->id, 'fuel_type' => 'D', 'balance' => 50, 'cost' => null, 'balance_date' => '2026-10-03', 'source' => TankBalance::BACKUP]);
        $this->put('/balances/tanks/' . $made->id, ['cost' => 6.5, 'note' => 'n'])->assertSessionHasNoErrors();
        $this->assertEquals(6.5, $made->fresh()->cost);
        $this->post('/balances/ticket-sales', ['fuel_type' => 'D', 'spec' => 0, 'quantity' => 4])->assertSessionHasNoErrors();
        $this->assertSame(1, OpeningTicket::count());
    }

    public function testUnlockedTheyCanBeChangedAgain()
    {
        $this->as('admin')->post('/balances/lock');
        $this->as('accountant')->post('/balances/debts', ['customer_id' => $this->customer->id, 'amount' => 5, 'payment_method' => 'cash'])->assertSessionHasErrors('balance');
        $this->assertSame(0, Debt::count());

        $this->as('admin')->post('/balances/unlock');
        $this->as('accountant')->post('/balances/debts', ['customer_id' => $this->customer->id, 'amount' => 5, 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame(1, Debt::count());
    }
}
