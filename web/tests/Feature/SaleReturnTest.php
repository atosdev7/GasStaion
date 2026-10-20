<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Sale;
use App\Models\SaleReturnTicket;
use App\Models\Ticket;
use App\Models\TicketLoan;
use App\Models\TicketLoanSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml). Tickets are spec 0 = 5 kg, price 2 = 10 per ticket.
 */
class SaleReturnTest extends TestCase
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

    private function publishTickets($quantity)
    {
        $this->as('manager')->post('/ticket-inbound', ['fuel_type' => 'D', 'spec' => 0, 'quantity' => $quantity, 'inbound_date' => '2026-09-20']);
        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 1, 'end_serial' => $quantity]);
    }

    // The sale just created, found by its id: two sales made in the same second tie on created_at.
    private function newSale(\Closure $make)
    {
        $before = Sale::withTrashed()->pluck('id')->all();
        $make();

        return Sale::withTrashed()->whereNotIn('id', $before)->firstOrFail();
    }

    private function sell($quantity, $extra = [])
    {
        return $this->newSale(function () use ($quantity, $extra) {
            $this->as('manager')->post('/ticket-sales', $extra + [
                'sale_type' => 'ticket', 'fuel_type' => 'D', 'lines' => [['spec' => 0, 'quantity' => $quantity]],
                'customer_id' => $this->customer->id, 'price' => 2, 'usage' => 'sale', 'payment_method' => 'cash',
                'paid_amount' => $quantity * 5 * 2,
            ])->assertRedirect('/ticket-sales');
        });
    }

    private function spotSell($amountKg)
    {
        return $this->newSale(function () use ($amountKg) {
            $this->as('manager')->post('/ticket-sales', [
                'sale_type' => 'spot', 'fuel_type' => 'D', 'amount_kg' => $amountKg, 'customer_id' => $this->customer->id,
                'price' => 2, 'usage' => 'sale', 'paid_amount' => $amountKg * 2,
            ])->assertRedirect('/spot-sales');
        });
    }

    private function barcodes($sale, $count = null)
    {
        $codes = Ticket::where('sale_id', $sale->id)->orderBy('serial')->pluck('barcode')->all();

        return $count === null ? $codes : array_slice($codes, 0, $count);
    }

    private function giveBack($sale, array $barcodes, $role = 'assistant', $extra = [])
    {
        return $this->as($role)->post('/ticket-sales/' . $sale->id . '/return', $extra + ['ticket_barcodes' => implode("\n", $barcodes)]);
    }

    private function returnRows($sale)
    {
        return Sale::where('reverses_sale_id', $sale->id)->orderBy('created_at')->get();
    }

    private function assertRefused($response, $key, $field = 'sale', $params = [])
    {
        $response->assertSessionHasErrors($field);
        $this->assertSame(__('sale_return.' . $key, $params), session('errors')->first($field));
    }

    // ---- the entry itself ----

    public function testPartialReturnOfAPaidSaleRefundsAndReturnsTheTickets()
    {
        $this->publishTickets(10);
        $sale = $this->sell(4); // 40, fully paid
        $codes = $this->barcodes($sale, 1);

        $this->giveBack($sale, $codes)->assertRedirect('/ticket-sales')->assertSessionHas('status');

        $return = $this->returnRows($sale)->first();
        $this->assertSame($sale->id, $return->reverses_sale_id);
        $this->assertEquals(-5, $return->amount_kg);
        $this->assertEquals(-10, $return->total_amount);
        $this->assertEquals(-10, $return->paid_amount); // refunded
        $this->assertEquals(0, $return->unpaid_amount);
        $this->assertSame(today()->toDateString(), substr($return->sale_date, 0, 10));
        $this->assertFalse((bool) $return->synced);

        $ticket = Ticket::find($codes[0]);
        $this->assertSame(config('ticket_statuses.publish'), (int) $ticket->status);
        $this->assertNull($ticket->sale_id);
        $this->assertFalse((bool) $ticket->synced);
        $this->assertSame(1, SaleReturnTicket::where('sale_id', $return->id)->count());
        $this->assertSame(3, Ticket::where('sale_id', $sale->id)->count());

        // The original stays exactly as it was.
        $fresh = Sale::find($sale->id);
        $this->assertEquals(20, $fresh->amount_kg);
        $this->assertEquals(40, $fresh->total_amount);
    }

    public function testReturnedValueCancelsTheDebtBeforeAnythingIsRefunded()
    {
        $this->publishTickets(10);
        $sale = $this->sell(4, ['paid_amount' => 10]); // 40 due, 10 paid: a debt of 30
        $codes = $this->barcodes($sale);

        $this->giveBack($sale, array_slice($codes, 0, 2)); // value 20, all of it against the debt
        $first = $this->returnRows($sale)->first();
        $this->assertEquals(-20, $first->total_amount);
        $this->assertEquals(0, $first->paid_amount);
        $this->assertEquals(-20, $first->unpaid_amount);
        $this->assertEquals(10, Debt::first()->amount);

        $this->giveBack($sale, array_slice($codes, 2, 2)); // value 20: 10 cancels the rest of the debt, 10 is refunded
        $second = $this->returnRows($sale)->firstWhere('id', '<>', $first->id); // same-second rows tie on created_at
        $this->assertEquals(-20, $second->total_amount);
        $this->assertEquals(-10, $second->paid_amount);
        $this->assertEquals(-10, $second->unpaid_amount);
        $this->assertEquals(0, Debt::first()->amount);
        $this->assertSame(0, Ticket::where('sale_id', $sale->id)->count());
    }

    public function testPaymentsAlreadyMadeAgainstTheDebtAreNotCancelledTwice()
    {
        $this->publishTickets(10);
        $sale = $this->sell(4, ['paid_amount' => 0]); // a debt of 40
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => Debt::first()->id, 'amount' => 30,
            'payment_method' => 'cash', 'paid_date' => today()->toDateString(), 'user_id' => 1,
        ]);

        $this->giveBack($sale, $this->barcodes($sale, 2)); // value 20, but only 10 is still owed

        $return = $this->returnRows($sale)->first();
        $this->assertEquals(-10, $return->unpaid_amount);
        $this->assertEquals(-10, $return->paid_amount); // the customer had paid 30 of it, so 10 comes back
        $this->assertEquals(30, Debt::first()->amount);
    }

    public function testTheAllCheckboxReturnsEveryUnusedTicketAndSkipsUsedOnes()
    {
        $this->publishTickets(10);
        $sale = $this->sell(4);
        $used = $this->barcodes($sale, 1)[0];
        Ticket::whereKey($used)->update(['status' => config('ticket_statuses.restrict')]);

        $this->as('assistant')->post('/ticket-sales/' . $sale->id . '/return', ['all' => 1])->assertRedirect('/ticket-sales');

        $return = $this->returnRows($sale)->first();
        $this->assertEquals(-15, $return->amount_kg);
        $this->assertSame(3, SaleReturnTicket::where('sale_id', $return->id)->count());
        $this->assertSame(config('ticket_statuses.restrict'), (int) Ticket::find($used)->status);
    }

    public function testReturningTheWholeRemainderTakesBackExactlyWhatIsLeft()
    {
        $this->publishTickets(10);
        $sale = $this->sell(3, ['price' => 3.33, 'paid_amount' => 49.95]); // 15 kg x 3.33 = 49.95
        $codes = $this->barcodes($sale);

        $this->giveBack($sale, [$codes[0]]); // 5 x 3.33 = 16.65
        $this->giveBack($sale, [$codes[1], $codes[2]]);

        $this->assertEquals(0, Sale::where('id', $sale->id)->orWhere('reverses_sale_id', $sale->id)->sum('amount_kg'));
        $this->assertEquals(0, Sale::where('id', $sale->id)->orWhere('reverses_sale_id', $sale->id)->sum('total_amount'));
        $this->assertEquals(0, Sale::where('id', $sale->id)->orWhere('reverses_sale_id', $sale->id)->sum('paid_amount'));
    }

    public function testAConfirmedSaleCanBeReturned()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);
        Sale::whereKey($sale->id)->update(['confirmed_at' => now(), 'confirmed_by' => 1]);

        $this->giveBack($sale, $this->barcodes($sale, 1))->assertRedirect('/ticket-sales');

        $this->assertSame(1, $this->returnRows($sale)->count());
        $this->assertNull($this->returnRows($sale)->first()->confirmed_at); // the accountant confirms the return later
    }

    // ---- spot and loan sales are returned whole ----

    public function testASpotSalesTicketBecomesUnusable()
    {
        $sale = $this->spotSell(7.5);
        $ticket = Ticket::where('sale_id', $sale->id)->first();

        $this->giveBack($sale, [$ticket->barcode])->assertRedirect('/spot-sales');

        $return = $this->returnRows($sale)->first();
        $this->assertEquals(-7.5, $return->amount_kg);
        $this->assertEquals(-15, $return->total_amount);
        $fresh = Ticket::find($ticket->barcode);
        $this->assertSame(config('ticket_statuses.restrict'), (int) $fresh->status);
        $this->assertSame($sale->id, $fresh->sale_id);

        // Nothing is left to return a second time.
        $this->assertRefused($this->giveBack($sale, [$ticket->barcode]), 'tickets_not_returnable', 'ticket_barcodes', ['barcodes' => $ticket->barcode]);
    }

    public function testATicketLoanSaleCanOnlyBeReturnedWholeAndCancelsTheLoan()
    {
        $this->publishTickets(10);
        $sale = $this->sell(5, ['usage' => 'ticket_loan', 'payment_method' => null, 'paid_amount' => null]);
        $codes = $this->barcodes($sale);

        $this->assertRefused($this->giveBack($sale, array_slice($codes, 0, 2)), 'whole_only');
        $this->assertSame(0, $this->returnRows($sale)->count());
        $this->assertSame(1, TicketLoan::count());

        $this->giveBack($sale, $codes)->assertRedirect('/ticket-sales');

        $this->assertSame(0, TicketLoan::count());
        $this->assertSame(1, TicketLoan::onlyTrashed()->count());
        $this->assertSame(10, Ticket::where('status', config('ticket_statuses.publish'))->count());
    }

    public function testALoanWithSettlementsCannotBeReturned()
    {
        $this->publishTickets(10);
        $sale = $this->sell(5, ['usage' => 'ticket_loan', 'payment_method' => null, 'paid_amount' => null]);
        TicketLoanSettlement::forceCreate([
            'loan_id' => TicketLoan::first()->id, 'ticket_barcode' => '0000006000000999', 'amount_kg' => 5,
            'settled_at' => now(), 'user_id' => 1,
        ]);

        $this->assertRefused($this->giveBack($sale, $this->barcodes($sale)), 'loan_settled');
        $this->assertSame(0, $this->returnRows($sale)->count());
    }

    // ---- refusals ----

    public function testBarcodesThatAreNotUnusedTicketsOfTheSaleAreRefusedAndNothingChanges()
    {
        $this->publishTickets(10);
        $sale = $this->sell(2);
        $other = $this->sell(2);
        $codes = $this->barcodes($sale);

        $response = $this->giveBack($sale, [$codes[0], $this->barcodes($other, 1)[0]]);

        $response->assertSessionHasErrors('ticket_barcodes');
        $this->assertSame(0, $this->returnRows($sale)->count());
        $this->assertSame(2, Ticket::where('sale_id', $sale->id)->count());
    }

    public function testSomethingMustBeEntered()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);

        $this->assertRefused($this->giveBack($sale, []), 'nothing_entered', 'ticket_barcodes');
        $this->assertSame(0, $this->returnRows($sale)->count());
    }

    public function testAReturnEntryCannotBeReturnedAndADeletedSaleIsNotFound()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);
        $this->giveBack($sale, $this->barcodes($sale, 1));
        $return = $this->returnRows($sale)->first();

        $this->as('assistant')->get('/ticket-sales/' . $return->id . '/return')->assertNotFound();
        $this->assertRefused($this->giveBack($return, ['0000006000000001']), 'is_return');

        $gone = $this->sell(1);
        $this->as('assistant')->delete('/ticket-sales/' . $gone->id);
        $this->as('assistant')->get('/ticket-sales/' . $gone->id . '/return')->assertNotFound();
        $this->giveBack($gone, ['0000006000000001'])->assertNotFound();
    }

    public function testASaleWithReturnsAndAReturnEntryCannotBeDeleted()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);
        $this->giveBack($sale, $this->barcodes($sale, 1));
        $return = $this->returnRows($sale)->first();

        $this->as('assistant')->delete('/ticket-sales/' . $sale->id)->assertSessionHasErrors('sale');
        $this->assertSame(__('sale_delete.blocked_returned'), session('errors')->first('sale'));
        $this->assertNotNull(Sale::find($sale->id));

        $this->as('assistant')->delete('/ticket-sales/' . $return->id)->assertSessionHasErrors('sale');
        $this->assertSame(__('sale_delete.blocked_is_return'), session('errors')->first('sale'));
        $this->assertNotNull(Sale::find($return->id));
    }

    public function testOnlyManagerAccountantAndAssistantMayReturn()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);
        $codes = $this->barcodes($sale);

        $this->giveBack($sale, [$codes[0]], 'cashier')->assertForbidden();
        $this->as('cashier')->get('/ticket-sales/' . $sale->id . '/return')->assertForbidden();
        $this->assertSame(0, $this->returnRows($sale)->count());

        foreach (['accountant', 'manager'] as $i => $role) {
            $this->giveBack($sale, [$codes[$i]], $role)->assertRedirect('/ticket-sales');
        }
        $this->assertSame(2, $this->returnRows($sale)->count());
    }

    // ---- screens and reports ----

    public function testTheReturnPageShowsTheUnusedTicketsAndTheDebt()
    {
        $this->publishTickets(5);
        $sale = $this->sell(3, ['paid_amount' => 5]);

        $this->as('assistant')->get('/ticket-sales/' . $sale->id . '/return')->assertOk()
            ->assertSee(__('sale_return.title'))->assertSee('25.00'); // still owed: 30 - 5
    }

    public function testTheListShowsTheReturnButtonAndBadges()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);
        $this->as('assistant')->get('/ticket-sales')->assertSee('/ticket-sales/' . $sale->id . '/return');

        $this->giveBack($sale, $this->barcodes($sale, 1));
        $this->as('assistant')->get('/ticket-sales')->assertOk()
            ->assertSee(__('sale_return.badge'))
            ->assertSee(__('sale_return.returned_kg', ['kg' => '5.00']));

        $this->giveBack($sale, $this->barcodes($sale));
        $this->as('assistant')->get('/ticket-sales')->assertDontSee('/ticket-sales/' . $sale->id . '/return');
    }

    public function testReportsNetOutReturnsAndCountOnlyOriginalSales()
    {
        $this->publishTickets(10);
        $sale = $this->sell(4); // 20 kg, 40
        $this->giveBack($sale, $this->barcodes($sale, 1)); // -5 kg, -10

        $period = '?from=' . today()->toDateString() . '&to=' . today()->toDateString();
        $summary = $this->as('accountant')->get('/reports/sales' . $period)->assertOk()->viewData('summary');
        $this->assertEquals(1, $summary->sum('sales')); // the return is not a sale of its own
        $this->assertEquals(15, $summary->sum('kg'));
        $this->assertEquals(30, $summary->sum('total'));
        $this->assertEquals(30, $summary->sum('paid'));

        $cash = $this->as('accountant')->get('/reports/cash' . $period)->assertOk()->viewData('rows');
        $this->assertEquals(30, $cash->sum('sales')); // 40 received, 10 refunded

        $profit = $this->as('accountant')->get('/reports/profit' . $period)->assertOk()->viewData('rows');
        $this->assertEquals(30, $profit->sum('revenue'));
    }

    public function testTheDashboardCountsOnlyOriginalSalesToday()
    {
        $this->publishTickets(10);
        $sale = $this->sell(4);
        $this->giveBack($sale, $this->barcodes($sale, 1));

        $this->as('manager')->get('/')->assertOk();
        $this->assertSame(1, $this->as('manager')->get('/')->viewData('todaySales'));
    }
}
