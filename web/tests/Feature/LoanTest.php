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
        $this->as('assistant')->get('/loans')->assertOk();         // the assistant settles the loans like the accountant
        $this->as('cashier')->get('/loans')->assertOk();         // the cashier looks at the ticket loans, and settles nothing
        $this->get('/loans/create')->assertForbidden();
        $this->post('/loans', [])->assertForbidden();
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
        ])->assertRedirect('/loans?tab=settlements');

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
        ])->assertRedirect('/loans?tab=settlements');

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

        $this->as('cashier')->get('/loans/create')->assertForbidden(); // view only
        $this->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001'])->assertForbidden();

        $this->as('accountant')->get('/loans/create')->assertOk();
        $this->as('assistant')->get('/loans/create')->assertOk();
        $this->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001'])->assertRedirect('/loans?tab=settlements');
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

    public function testAWrongSettlementIsUndoneByAReversalAndTheTicketGoesBackToTheCustomer()
    {
        $loan = TicketLoan::forceCreate(['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 50, 'user_id' => 1]);
        $this->soldTicket('0000000000000001', 'D', 0); // 5 kg
        $this->as('accountant')->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001']);
        $settlement = TicketLoanSettlement::first();
        $this->assertEquals(5, $loan->fresh()->settled_amount_kg);

        $this->post('/loans/settlements/' . $settlement->id . '/reverse')->assertRedirect('/loans?tab=settlements')->assertSessionHas('status');

        // the settlement is kept, its reversal is a line of its own: -5 kg, today, for the same loan and ticket
        $this->assertEquals(5, $settlement->fresh()->amount_kg);
        $reversal = TicketLoanSettlement::where('reverses_settlement_id', $settlement->id)->first();
        $this->assertEquals(-5, $reversal->amount_kg);
        $this->assertSame($loan->id, $reversal->loan_id);
        $this->assertSame('0000000000000001', $reversal->ticket_barcode);
        $this->assertSame(today()->toDateString(), $reversal->date);
        $this->assertSame(2, TicketLoanSettlement::count());

        // the loan owes the 5 kg again, and the ticket is sold again (the customer has it back)
        $this->assertEquals(0, $loan->fresh()->settled_amount_kg);
        $ticket = Ticket::find('0000000000000001');
        $this->assertSame(config('ticket_statuses.sale'), $ticket->status);
        $this->assertNull($ticket->supply_date);

        // the same ticket can settle again
        $this->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001'])->assertRedirect('/loans?tab=settlements');
        $this->assertEquals(5, $loan->fresh()->settled_amount_kg);

        // the page lists both lines
        $page = $this->get('/loans')->assertOk();
        $page->assertSee('Tickets surrendered')->assertSee('-5.00');
    }

    public function testASettlementCannotBeReversedTwiceOrAfterItsTicketWasUsed()
    {
        $loan = TicketLoan::forceCreate(['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 50, 'user_id' => 1]);
        $this->soldTicket('0000000000000001', 'D', 0);
        $this->as('accountant')->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001']);
        $settlement = TicketLoanSettlement::first();

        // the ticket was supplied at a nozzle since: it cannot go back
        Ticket::where('barcode', '0000000000000001')->update(['supplement_id' => 'a-supplement']);
        $this->post('/loans/settlements/' . $settlement->id . '/reverse')->assertSessionHasErrors('settlement');
        $this->assertSame(1, TicketLoanSettlement::count());
        Ticket::where('barcode', '0000000000000001')->update(['supplement_id' => null]);

        $this->post('/loans/settlements/' . $settlement->id . '/reverse')->assertSessionHasNoErrors();
        $this->post('/loans/settlements/' . $settlement->id . '/reverse')->assertSessionHasErrors('settlement');
        $reversal = TicketLoanSettlement::where('reverses_settlement_id', $settlement->id)->first();
        $this->post('/loans/settlements/' . $reversal->id . '/reverse')->assertSessionHasErrors('settlement');
        $this->assertSame(2, TicketLoanSettlement::count());
        $this->assertEquals(0, $loan->fresh()->settled_amount_kg);
    }

    public function testOnlyThoseWhoManageLoansCanReverseASettlement()
    {
        $loan = TicketLoan::forceCreate(['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 50, 'user_id' => 1]);
        $this->soldTicket('0000000000000001', 'D', 0);
        $this->as('accountant')->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001']);
        $settlement = TicketLoanSettlement::first();

        foreach (['cashier'] as $role) {
            $this->as($role)->post('/loans/settlements/' . $settlement->id . '/reverse')->assertForbidden();
        }
        $this->assertSame(1, TicketLoanSettlement::count());

        // the assistant reverses like the accountant
        $this->as('assistant')->post('/loans/settlements/' . $settlement->id . '/reverse')->assertSessionHas('status');
        $this->assertSame(2, TicketLoanSettlement::count());
    }

    // ---- the page: tabs, actions, popups, filters of the settlements, reverse window ----

    // a loan with n settlements of 5 kg (tickets 0000000000000001...)
    private function settledLoan($times = 1, $amount = 50)
    {
        $loan = TicketLoan::forceCreate(['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => $amount, 'user_id' => 1, 'loaned_at' => '2026-10-01 10:00:00', 'date' => '2026-10-01']);
        for ($i = 1; $i <= $times; $i++) {
            $barcode = str_pad((string) $i, 16, '0', STR_PAD_LEFT);
            $this->soldTicket($barcode, 'D', 0);
            $this->as('accountant')->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => $barcode])->assertSessionHasNoErrors();
        }

        return $loan->fresh();
    }

    public function testThePageHasTabsAndTheLoansHaveActions()
    {
        $open = $this->loanOf('2026-10-01', $this->customer, 'D', 100);
        $done = $this->loanOf('2026-10-02', $this->customer, 'A', 50, 50);

        $html = $this->as('accountant')->get('/loans')->assertOk()->getContent();
        $this->assertStringContainsString('id="tab-loans-link"', $html);
        $this->assertStringContainsString('id="tab-settlements-link"', $html);
        $this->assertRegExp('/nav-link active" id="tab-loans-link"/', $html);                                 // the loans first
        $this->assertRegExp('/nav-link active" id="tab-settlements-link"/', $this->get('/loans?tab=settlements')->getContent());

        // View for every loan; Settle only for an open one, for those who settle
        $this->assertStringContainsString(route('loans.show', $open->id), $html);
        $this->assertStringContainsString(route('loans.show', $done->id), $html);
        $this->assertStringContainsString(route('loans.create', ['loan_id' => $open->id]), $html);
        $this->assertStringNotContainsString(route('loans.create', ['loan_id' => $done->id]), $html);
        $this->assertStringNotContainsString('loan_id=' . $open->id, $this->as('cashier')->get('/loans')->getContent());         // the cashier only looks
        $this->assertStringContainsString(route('loans.show', $open->id), $this->get('/loans')->getContent());

        // Settle opens the form with that loan chosen
        $form = $this->as('accountant')->get('/loans/create?loan_id=' . $open->id)->assertOk()->getContent();
        $this->assertRegExp('/<option value="' . $open->id . '" selected>/', $form);
        $this->assertNotRegExp('/<option value="[^"]*" selected>/', $this->get('/loans/create?loan_id=abc')->getContent());
    }

    public function testALoanHasAViewWithTheSettlementsAndTheCreditBadge()
    {
        $loan = $this->settledLoan(2, 8);                                       // two tickets of 5 kg against 8 kg: 2 kg over
        $this->assertEquals(-2, $loan->amount_kg - $loan->settled_amount_kg);

        $this->as('accountant')->get('/loans')->assertOk()->assertSee('Credit');
        $page = $this->get('/loans/' . $loan->id)->assertOk()->assertSee('ACME')->assertSee('8.00')->assertSee('10.00')->assertSee('-2.00')->assertSee('0000000000000002');
        $this->assertStringContainsString(route('loans.settlements.show', TicketLoanSettlement::first()->id), $page->getContent());
        $popup = $this->get('/loans/' . $loan->id . '?modal=1')->assertOk()->assertSee('0000000000000001');
        $this->assertStringNotContainsString('<html', $popup->getContent());
        $this->get('/loans/00000000-0000-0000-0000-000000000000')->assertNotFound();

        // every one who views the loans may open it; the sale of the loan is linked for those who see it
        foreach (['manager', 'assistant', 'cashier'] as $role) {
            $this->as($role)->get('/loans/' . $loan->id)->assertOk();
        }
        $sale = \App\Models\Sales\Sale::forceCreate([
            'customer_id' => $this->customer->id, 'sale_type' => 'ticket', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 8, 'payment_method' => 'cash', 'total_amount' => 16,
            'paid_amount' => 0, 'unpaid_amount' => 0, 'usage' => 'ticket_loan', 'sale_date' => '2026-10-01', 'user_id' => 1,
        ]);
        $loan->forceFill(['sale_id' => $sale->id])->save();
        $this->assertStringContainsString(route('ticket-sales.show', $sale->id), $this->as('accountant')->get('/loans/' . $loan->id)->getContent());
        $this->assertStringNotContainsString(route('ticket-sales.show', $sale->id), $this->as('cashier')->get('/loans/' . $loan->id . '?modal=1')->getContent());
    }

    public function testASettlementWorthMoreThanTheLoanOwesIsAcceptedWithAWarning()
    {
        $loan = TicketLoan::forceCreate(['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 3, 'user_id' => 1]);
        $this->soldTicket('0000000000000001', 'D', 0);            // 5 kg

        $this->as('accountant')->post('/loans', ['loan_id' => $loan->id, 'ticket_barcode' => '0000000000000001'])
            ->assertRedirect('/loans?tab=settlements')->assertSessionHas('status')->assertSessionHas('warning');
        $this->assertEquals(5, $loan->fresh()->settled_amount_kg);

        // a ticket that fits has no warning
        $other = TicketLoan::forceCreate(['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 50, 'user_id' => 1]);
        $this->soldTicket('0000000000000002', 'D', 0);
        $this->post('/loans', ['loan_id' => $other->id, 'ticket_barcode' => '0000000000000002'])->assertSessionMissing('warning');
    }

    public function testTheSettlementsTabFiltersByDayCustomerFuelAndBarcode()
    {
        $globex = Customer::forceCreate(['name' => 'Globex']);
        $a = TicketLoan::forceCreate(['customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 50, 'user_id' => 1]);
        $b = TicketLoan::forceCreate(['customer_id' => $globex->id, 'fuel_type' => 'A', 'amount_kg' => 50, 'user_id' => 1]);
        $mk = function ($loan, $barcode, $date) {
            return TicketLoanSettlement::forceCreate(['loan_id' => $loan->id, 'ticket_barcode' => $barcode, 'amount_kg' => 5, 'settled_at' => $date . ' 10:00:00', 'date' => $date, 'user_id' => 1])->id;
        };
        $s1 = $mk($a, '0000000000000001', '2026-10-01');
        $s2 = $mk($a, '0000000000000002', '2026-10-02');
        $s3 = $mk($b, '0000000000000103', '2026-10-02');

        $ids = function ($query) {
            return $this->as('accountant')->get('/loans?tab=settlements' . $query)->assertOk()->viewData('settlements')->pluck('id')->all();
        };
        $this->assertEqualsCanonicalizing([$s1, $s2, $s3], $ids(''));
        $this->assertEqualsCanonicalizing([$s2, $s3], $ids('&settled_on=2026-10-02'));
        $this->assertEqualsCanonicalizing([$s1, $s2], $ids('&set_customer=' . $this->customer->id));
        $this->assertSame([$s3], $ids('&set_fuel=A'));
        $this->assertSame([$s3], $ids('&barcode=103'));                                      // a part of the barcode
        $this->assertSame([$s2], $ids('&settled_on=2026-10-02&set_customer=' . $this->customer->id . '&set_fuel=D'));   // together
        $this->assertSame([], $ids('&settled_on=2026-09-01'));
        $this->assertEqualsCanonicalizing([$s1, $s2, $s3], $ids('&customer=' . $globex->id));       // the filters of the loans do not touch it

        $this->as('accountant')->get('/loans?settled_on=yesterday')->assertSessionHasErrors('settled_on');
        $html = $this->get('/loans?tab=settlements&set_fuel=A')->assertOk()->getContent();
        $this->assertStringContainsString('id="settlement-filters"', $html);
        foreach (['settled_on', 'set_customer', 'set_fuel', 'barcode'] as $name) {
            $this->assertRegExp('/name="' . $name . '" form="settlement-filters"/', $html, $name);
        }
        $this->assertStringContainsString('<option value="A" selected>', $html);
    }

    public function testASettlementHasAViewAndTheReversalBadgesLinkToIt()
    {
        $loan = $this->settledLoan(1);
        $settlement = TicketLoanSettlement::first();
        $this->as('accountant')->post('/loans/settlements/' . $settlement->id . '/reverse', ['note' => 'wrong ticket'])->assertSessionHas('status');
        $reversal = TicketLoanSettlement::where('reverses_settlement_id', $settlement->id)->first();
        $this->assertSame('Reversal wrong ticket', $reversal->comment);

        $html = $this->get('/loans?tab=settlements')->assertOk()->getContent();
        $this->assertRegExp('/<a href="' . preg_quote(route('loans.settlements.show', $settlement->id), '/') . '" class="badge badge-secondary settlement-detail">Reversal<\/a>/', $html);
        $this->assertRegExp('/<a href="' . preg_quote(route('loans.settlements.show', $reversal->id), '/') . '" class="badge badge-secondary settlement-detail">Reversed<\/a>/', $html);
        $this->assertStringContainsString(route('loans.settlements.show', $settlement->id), $html);          // View

        $page = $this->get('/loans/settlements/' . $settlement->id)->assertOk()->assertSee('0000000000000001')->assertSee('5.00')->assertSee('Reversed');
        $this->assertStringContainsString(route('loans.show', $loan->id), $page->getContent());
        $popup = $this->get('/loans/settlements/' . $reversal->id . '?modal=1')->assertOk()->assertSee('-5.00')->assertSee('wrong ticket');
        $this->assertStringNotContainsString('<html', $popup->getContent());
        $this->get('/loans/settlements/00000000-0000-0000-0000-000000000000')->assertNotFound();
        foreach (['manager', 'assistant', 'cashier'] as $role) {
            $this->as($role)->get('/loans/settlements/' . $settlement->id)->assertOk();
        }
    }

    public function testReversingASettlementAsksInAModalAndAConfirmedOneCannotBeReversed()
    {
        $this->settledLoan(2);
        $open = TicketLoanSettlement::orderBy('settled_at')->orderBy('id')->get()[0];
        $confirmed = TicketLoanSettlement::orderBy('settled_at')->orderBy('id')->get()[1];
        $confirmed->forceFill(['confirmed_at' => now(), 'confirmed_by' => 1])->save();

        $html = $this->as('accountant')->get('/loans?tab=settlements')->assertOk()->getContent();
        $this->assertStringContainsString('data-action="' . route('loans.settlements.reverse', $open->id) . '"', $html);       // the button only opens the window
        $this->assertStringNotContainsString(route('loans.settlements.reverse', $confirmed->id), $html);                      // a confirmed one has a lock
        $this->assertStringContainsString('fa-lock', $html);
        $this->assertStringContainsString('id="reverse-modal"', $html);
        $this->assertStringContainsString('Do you really want to reverse this settlement?', $html);
        $this->assertRegExp('/id="reverse-note" name="note" maxlength="200" autocomplete="off"/', $html);

        $this->post('/loans/settlements/' . $confirmed->id . '/reverse')->assertSessionHasErrors('settlement');
        $this->assertSame(0, TicketLoanSettlement::where('reverses_settlement_id', $confirmed->id)->count());
        $this->assertSame(config('ticket_statuses.supply'), Ticket::find($confirmed->ticket_barcode)->status);

        // those who only view have no window
        $this->as('cashier')->get('/loans?tab=settlements')->assertOk()->assertDontSee('id="reverse-modal"');
    }
}
