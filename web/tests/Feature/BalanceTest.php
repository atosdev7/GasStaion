<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Fuel\InitialTankBalance;
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

    public function testSaveTankBalancesCreatesUpdatesAndClears()
    {
        $t1 = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        $t2 = Tank::forceCreate(['name' => 'T2', 'fuel_type' => 'A']);

        $this->as('accountant')->post('/balances/tanks', ['tanks' => [$t1->id => 1200.5, $t2->id => '']])
            ->assertRedirect('/balances/tanks');
        $this->assertEquals(1200.5, InitialTankBalance::where('tank_id', $t1->id)->value('amount_kg'));
        $this->assertSame(1, InitialTankBalance::count());

        $this->post('/balances/tanks', ['tanks' => [$t1->id => '', $t2->id => 300]]);
        $this->assertNull(InitialTankBalance::where('tank_id', $t1->id)->first());
        $this->assertEquals(300, InitialTankBalance::where('tank_id', $t2->id)->value('amount_kg'));
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
        $this->assertStringContainsString('name="tanks[' . $t1->id . ']"', $tanks);
        $this->assertStringNotContainsString('f_debt_customer', $tanks);
        $this->assertStringNotContainsString('f_loan_customer', $tanks);
        $this->assertSame(1, preg_match_all('/<a href="' . preg_quote(url('/balances'), '/') . '\/[a-z]+" class="nav-link active">/', $tanks));
        $this->assertRegExp('/<a href="' . preg_quote(url('/balances/tanks'), '/') . '" class="nav-link active">/', $tanks);

        $debts = $this->as('accountant')->get('/balances/debts')->assertOk()->getContent();
        $this->assertStringContainsString('f_debt_customer', $debts);
        $this->assertStringContainsString('75.00', $debts);
        $this->assertStringNotContainsString('name="tanks[', $debts);
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
        $this->assertSame(config('ticket_reserved_codes.remainder'), $ticket->code);
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
