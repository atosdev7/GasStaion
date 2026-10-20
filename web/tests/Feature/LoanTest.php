<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    private $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->customer = Customer::forceCreate(['name' => 'ACME']);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    public function testBalanceIsAmountMinusSettled()
    {
        TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D',
            'amount_kg' => 50, 'settled_amount_kg' => 20, 'user_id' => 1,
        ]);

        $this->as('manager')->get('/loans')->assertOk()
            ->assertSee('50.00')
            ->assertSee('20.00')
            ->assertSee('30.00')
            ->assertSee(__('debts.debt_open'));
    }

    public function testFullySettledLoanShowsAsSettled()
    {
        TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D',
            'amount_kg' => 25, 'settled_amount_kg' => 25, 'user_id' => 1,
        ]);

        $this->as('manager')->get('/loans')->assertOk()->assertSee(__('debts.debt_settled'));
    }

    public function testPermissions()
    {
        $this->as('manager')->get('/loans')->assertOk();
        $this->as('accountant')->get('/loans')->assertOk();
        $this->as('assistant')->get('/loans')->assertForbidden();
        $this->as('cashier')->get('/loans')->assertForbidden();
    }

    // ---- settling a loan ----

    private function soldTicket($barcode, $fuelType = 'D', $spec = 0)
    {
        return Ticket::forceCreate([
            'barcode' => $barcode, 'fuel_type' => $fuelType, 'spec' => $spec, 'code' => '06',
            'serial' => 1, 'status' => config('ticket_statuses.sale'), 'sale_date' => now(),
        ]);
    }

    public function testSettleLoanWithASoldTicketMarksItSuppliedAndCreditsTheLoan()
    {
        $loan = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D',
            'amount_kg' => 50, 'user_id' => 1,
        ]);
        $this->soldTicket('0000000000000001', 'D', 0); // 5kg

        $this->as('accountant')->post('/loans', [
            'loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001',
        ])->assertRedirect('/loans');

        $settlement = TicketLoanSettlement::first();
        $this->assertSame($loan->id, $settlement->loan_id);
        $this->assertSame('0000000000000001', $settlement->ticket_barcode);
        $this->assertEquals(5, $settlement->amount_kg);
        $this->assertSame(today()->toDateString(), $settlement->date);

        $ticket = Ticket::find('0000000000000001');
        $this->assertSame(config('ticket_statuses.supply'), $ticket->status);
        $this->assertNotNull($ticket->supply_date);

        $this->assertEquals(5, $loan->fresh()->settled_amount_kg);
    }

    public function testDirectTicketUsesItsOwnAmountKgNotASpec()
    {
        $loan = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'A',
            'amount_kg' => 30, 'user_id' => 1,
        ]);
        Ticket::forceCreate([
            'barcode' => '0000021000000001', 'fuel_type' => 'A', 'spec' => null, 'code' => '21',
            'serial' => 1, 'amount_kg' => 23.5, 'status' => config('ticket_statuses.sale'), 'sale_date' => now(),
        ]);

        $this->as('accountant')->post('/loans', [
            'loan_id' => $loan->id, 'ticket_barcode' => '0000021000000001',
        ])->assertRedirect('/loans');

        $this->assertEquals(23.5, TicketLoanSettlement::first()->amount_kg);
    }

    public function testCannotSettleWithAnUnsoldTicket()
    {
        $loan = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D',
            'amount_kg' => 50, 'user_id' => 1,
        ]);
        Ticket::forceCreate([
            'barcode' => '0000000000000001', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06',
            'serial' => 1, 'status' => config('ticket_statuses.publish'), // never sold
        ]);

        $this->as('accountant')->post('/loans', [
            'loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001',
        ])->assertSessionHasErrors('ticket_barcode');
        $this->assertSame(0, TicketLoanSettlement::count());
    }

    public function testCannotSettleWithAMismatchedFuelType()
    {
        $loan = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D',
            'amount_kg' => 50, 'user_id' => 1,
        ]);
        $this->soldTicket('0000000000000001', 'A', 0); // Gasoline ticket, Diesel loan

        $this->as('accountant')->post('/loans', [
            'loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001',
        ])->assertSessionHasErrors('ticket_barcode');
        $this->assertSame(0, TicketLoanSettlement::count());
    }

    public function testCreateScreenOnlyListsOpenLoans()
    {
        $open = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 10, 'user_id' => 1,
        ]);
        $settled = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D',
            'amount_kg' => 10, 'settled_amount_kg' => 10, 'user_id' => 1,
        ]);

        $html = $this->as('accountant')->get('/loans/create')->assertOk()->getContent();
        $this->assertStringContainsString('value="' . $open->id . '"', $html);
        $this->assertStringNotContainsString('value="' . $settled->id . '"', $html);
    }

    public function testSettlementPermissions()
    {
        $loan = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 10, 'user_id' => 1,
        ]);
        $this->soldTicket('0000000000000001');

        $this->as('manager')->get('/loans/create')->assertForbidden(); // view only
        $this->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001'])->assertForbidden();

        $this->as('accountant')->get('/loans/create')->assertOk();
    }

    // a loan: ($date, $customer, $fuel, $amount, $settled)
    private function loanOf($date, $customer, $fuel, $amount, $settled = 0)
    {
        return TicketLoan::forceCreate([
            'customer_id' => $customer->id, 'fuel_type' => $fuel, 'amount_kg' => $amount, 'settled_amount_kg' => $settled, 'user_id' => 1,
            'loaned_at' => $date . ' 10:00:00', 'date' => $date,
        ]);
    }

    public function testTheLoansTableHasFiltersInTheColumnHeads()
    {
        $other = Customer::forceCreate(['name' => 'Globex']);
        $a = $this->loanOf('2026-10-01', $this->customer, 'D', 100);                 // open
        $b = $this->loanOf('2026-10-01', $other, 'A', 50, 50);                      // settled
        $c = $this->loanOf('2026-10-02', $this->customer, 'A', 30, 10);             // open (20 left)
        $d = $this->loanOf('2026-10-03', $other, 'D', 70);                          // open

        $ids = function ($query) {
            return $this->as('accountant')->get('/loans' . $query)->assertOk()->viewData('loans')->pluck('id')->all();
        };

        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id, $d->id], $ids(''));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids('?loan_date=2026-10-01'));
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $ids('?customer=' . $this->customer->id));
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $ids('?fuel_type=A'));
        $this->assertEqualsCanonicalizing([$a->id, $c->id, $d->id], $ids('?status=open'));
        $this->assertSame([$b->id], $ids('?status=settled'));
        $this->assertSame([$c->id], $ids('?customer=' . $this->customer->id . '&fuel_type=A&status=open'));            // together
        $this->assertSame([], $ids('?loan_date=2026-09-01'));

        // the totals are those of the loans the filters leave
        $response = $this->as('accountant')->get('/loans?customer=' . $this->customer->id)->assertOk();
        $this->assertEquals(130, $response->viewData('totalLoaned'));
        $this->assertEquals(10, $response->viewData('totalSettled'));
        $this->assertEquals(120, $response->viewData('totalBalance'));

        // the filters are in the page: an input in the head of each column, tied to one form, the customer a Select2
        $html = $this->as('accountant')->get('/loans?status=open&fuel_type=D')->assertOk()->getContent();
        $this->assertStringContainsString('<form method="GET" action="' . url('/loans') . '" id="loan-filters">', $html);
        foreach (['loan_date', 'customer', 'fuel_type', 'status'] as $name) {
            $this->assertRegExp('/name="' . $name . '" form="loan-filters"/', $html, $name);
        }
        $this->assertRegExp('/id="loan-customer-filter" class="[^"]*js-select2/', $html);
        $this->assertStringContainsString('<option value="open" selected>', $html);
        $this->assertStringContainsString('<option value="D" selected>', $html);

        $this->as('accountant')->get('/loans?status=nowhere')->assertSessionHasErrors('status');
        $this->as('accountant')->get('/loans?loan_date=yesterday')->assertSessionHasErrors('loan_date');
    }

    public function testTheLoansTableIsPagedByFiftyAndKeepsTheFilters()
    {
        for ($i = 0; $i < 52; $i++) {
            $this->loanOf('2026-10-01', $this->customer, 'D', 10 + $i);
        }
        $this->loanOf('2026-10-01', $this->customer, 'A', 5);

        $first = $this->as('accountant')->get('/loans?fuel_type=D')->assertOk();
        $this->assertCount(50, $first->viewData('loans'));
        $this->assertSame(52, $first->viewData('loans')->total());
        $this->assertStringContainsString('fuel_type=D&amp;page=2', $first->getContent());
        $this->assertCount(2, $this->as('accountant')->get('/loans?fuel_type=D&page=2')->viewData('loans'));
    }
}
