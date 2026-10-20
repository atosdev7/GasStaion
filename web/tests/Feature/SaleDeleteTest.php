<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Sales\Sale;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class SaleDeleteTest extends TestCase
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

    private function deleteSale($sale, $role = 'assistant')
    {
        return $this->as($role)->delete('/ticket-sales/' . $sale->id);
    }

    private function assertNotDeleted($sale)
    {
        $this->assertNotNull(Sale::find($sale->id));
    }

    private function assertBlocked($response, $reason)
    {
        $response->assertSessionHasErrors('sale');
        $this->assertSame(__('sale_delete.blocked_' . $reason), session('errors')->first('sale'));
    }

    // ---- what a delete undoes ----

    public function testDeletingATicketSaleReturnsTheTicketsAndCancelsItsDebt()
    {
        $this->publishTickets(10);
        $sale = $this->sell(4, ['paid_amount' => 10]); // 40 due, 10 paid: a debt of 30
        $this->assertSame(1, Debt::count());
        $this->assertSame(4, Ticket::where('sale_id', $sale->id)->count());

        $this->deleteSale($sale)->assertRedirect('/ticket-sales')->assertSessionHas('status', __('sale_delete.deleted'));

        $this->assertNull(Sale::find($sale->id));
        $this->assertNotNull(Sale::onlyTrashed()->find($sale->id)); // kept for review
        $this->assertSame(0, Ticket::where('sale_id', $sale->id)->count());
        $this->assertSame(10, Ticket::where('status', config('ticket_statuses.publish'))->count());
        $returned = Ticket::where('status', config('ticket_statuses.publish'))->whereNull('sale_id')->whereNull('sale_date')->count();
        $this->assertSame(10, $returned);
        $this->assertSame(0, Ticket::where('synced', true)->count()); // the Smart210 will pull them as published again
        $this->assertSame(0, Debt::count());
        $this->assertSame(1, Debt::onlyTrashed()->count());
    }

    public function testDeletingASpotSaleMakesItsTicketUnusableAndKeepsTheLink()
    {
        $sale = $this->spotSell(7.5);
        $ticket = Ticket::where('sale_id', $sale->id)->first();
        $this->assertSame(config('ticket_statuses.sale'), (int) $ticket->status);

        $this->deleteSale($sale);

        $fresh = Ticket::find($ticket->barcode);
        $this->assertSame(config('ticket_statuses.restrict'), (int) $fresh->status);
        $this->assertSame($sale->id, $fresh->sale_id); // the link tells a deleted sale's ticket from a normal one
        $this->assertFalse((bool) $fresh->synced);
        $this->assertNull(Sale::find($sale->id));
    }

    public function testDeletingATicketLoanSaleCancelsTheLoan()
    {
        $this->publishTickets(10);
        $sale = $this->sell(5, ['usage' => 'ticket_loan', 'payment_method' => null, 'paid_amount' => null]);
        $this->assertSame(1, TicketLoan::count());

        $this->deleteSale($sale);

        $this->assertSame(0, TicketLoan::count());
        $this->assertSame(1, TicketLoan::onlyTrashed()->count());
        $this->assertSame(10, Ticket::where('status', config('ticket_statuses.publish'))->count());
    }

    // ---- when it is refused (it is for wrong input only) ----

    public function testAConfirmedSaleCannotBeDeleted()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);
        Sale::whereKey($sale->id)->update(['confirmed_at' => now(), 'confirmed_by' => 1]);

        $this->assertBlocked($this->deleteSale($sale), 'confirmed');
        $this->assertNotDeleted($sale);
        $this->assertSame(2, Ticket::where('sale_id', $sale->id)->count());
    }

    public function testASaleOlderThanTheWindowCannotBeDeleted()
    {
        $this->publishTickets(5);
        $old = $this->sell(2);
        $recent = $this->sell(1);
        Sale::whereKey($old->id)->update(['sale_date' => today()->subDays(4)->toDateString()]);
        Sale::whereKey($recent->id)->update(['sale_date' => today()->subDays(2)->toDateString()]);

        $this->assertBlocked($this->deleteSale($old), 'too_old');
        $this->assertNotDeleted($old);

        $this->deleteSale($recent)->assertSessionHasNoErrors(); // inside the 3 days
        $this->assertNull(Sale::find($recent->id));
    }

    public function testTheWindowComesFromTheConfig()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);
        Sale::whereKey($sale->id)->update(['sale_date' => today()->subDays(2)->toDateString()]);
        config(['sales.delete_days' => 1]);

        $this->assertBlocked($this->deleteSale($sale), 'too_old');

        config(['sales.delete_days' => 5]);
        $this->deleteSale($sale)->assertSessionHasNoErrors();
        $this->assertNull(Sale::find($sale->id));
    }

    public function testASaleWithAUsedOrRestrictedTicketCannotBeDeleted()
    {
        $this->publishTickets(5);
        $used = $this->sell(2);
        $restricted = $this->sell(2);
        Ticket::where('sale_id', $used->id)->first()->forceFill(['status' => config('ticket_statuses.supply')])->save();
        Ticket::where('sale_id', $restricted->id)->first()->forceFill(['status' => config('ticket_statuses.restrict')])->save();

        $this->assertBlocked($this->deleteSale($used), 'ticket_used');
        $this->assertBlocked($this->deleteSale($restricted), 'ticket_used');
        $this->assertNotDeleted($used);
        $this->assertNotDeleted($restricted);
        $this->assertSame(1, Ticket::where('sale_id', $used->id)->where('status', config('ticket_statuses.sale'))->count()); // untouched
    }

    public function testASaleWhoseTicketsAreGoneCannotBeVerifiedSoItIsRefused()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);
        Ticket::where('sale_id', $sale->id)->delete(); // as a backup would clear finished tickets

        $this->assertBlocked($this->deleteSale($sale), 'ticket_used');
        $this->assertNotDeleted($sale);
    }

    public function testASaleWithAPaymentOnItsDebtCannotBeDeleted()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2, ['paid_amount' => 0]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => Debt::first()->id, 'amount' => 5, 'payment_method' => 'cash',
            'paid_date' => today()->toDateString(), 'user_id' => 1,
        ]);

        $this->assertBlocked($this->deleteSale($sale), 'paid');
        $this->assertNotDeleted($sale);
        $this->assertSame(1, Debt::count());
    }

    public function testAGeneralPaymentOfTheCustomerDoesNotBlock()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2, ['paid_amount' => 0]);
        DebtPayment::forceCreate([ // not tied to any debt
            'customer_id' => $this->customer->id, 'debt_id' => null, 'amount' => 5, 'payment_method' => 'cash',
            'paid_date' => today()->toDateString(), 'user_id' => 1,
        ]);

        $this->deleteSale($sale)->assertSessionHasNoErrors();
        $this->assertNull(Sale::find($sale->id));
    }

    public function testASaleWhoseLoanWasSettledCannotBeDeleted()
    {
        $this->publishTickets(10);
        $sale = $this->sell(5, ['usage' => 'ticket_loan', 'payment_method' => null, 'paid_amount' => null]);
        TicketLoanSettlement::forceCreate([
            'loan_id' => TicketLoan::first()->id, 'ticket_barcode' => '0000006000000999', 'amount_kg' => 5,
            'settled_at' => now(), 'user_id' => 1,
        ]);

        $this->assertBlocked($this->deleteSale($sale), 'settled');
        $this->assertNotDeleted($sale);
        $this->assertSame(1, TicketLoan::count());
    }

    public function testADeletedSaleCannotBeDeletedAgain()
    {
        $this->publishTickets(5);
        $sale = $this->sell(2);
        $this->deleteSale($sale);

        $this->deleteSale($sale)->assertNotFound();
    }

    public function testWhoMayDelete()
    {
        $this->publishTickets(10);
        foreach (['assistant', 'accountant', 'manager', 'admin'] as $role) {
            $sale = $this->sell(1);
            $this->deleteSale($sale, $role)->assertSessionHasNoErrors();
            $this->assertNull(Sale::find($sale->id), $role);
        }

        $sale = $this->sell(1);
        $this->deleteSale($sale, 'cashier')->assertForbidden();
        $this->assertNotDeleted($sale);
    }

    // ---- the list ----

    public function testTheListOffersDeleteOnlyWhereItIsAllowed()
    {
        $this->publishTickets(10);
        $open = $this->sell(2);
        $confirmed = $this->sell(1);
        Sale::whereKey($confirmed->id)->update(['confirmed_at' => now(), 'confirmed_by' => 1]);

        $html = $this->as('assistant')->get('/ticket-sales')->assertOk()->getContent();

        // The quote ends the URL, so the Return link (the same URL plus /return) does not count as a delete form.
        $this->assertStringContainsString('action="' . route('ticket-sales.destroy', $open->id) . '"', $html);
        $this->assertStringNotContainsString('action="' . route('ticket-sales.destroy', $confirmed->id) . '"', $html);
        $this->assertStringContainsString(__('sale_delete.blocked_confirmed'), $html); // shown as a lock with its reason
    }

    public function testDeletedSalesHaveTheirOwnView()
    {
        $this->publishTickets(10);
        $kept = $this->sell(1);
        $gone = $this->sell(3);
        $this->deleteSale($gone);

        $this->as('assistant')->get('/ticket-sales?deleted=1')->assertOk()
            ->assertSee(__('sale_delete.deleted_note'))
            ->assertSee('15') // the deleted sale's 3 x 5 kg
            ->assertDontSee(__('sale_delete.delete') . '</button>', false);

        $recent = $this->get('/ticket-sales')->assertOk()->getContent();
        $this->assertStringContainsString(route('ticket-sales.destroy', $kept->id), $recent);
        $this->assertStringNotContainsString(route('ticket-sales.destroy', $gone->id), $recent);
    }

    // ---- confirming erases deleted sales for good ----

    public function testConfirmingADateErasesItsDeletedSalesWithWhatWasCancelled()
    {
        $this->publishTickets(10);
        $gone = $this->sell(2, ['paid_amount' => 0]); // with a debt
        $kept = $this->sell(1);
        $this->deleteSale($gone);
        $this->assertSame(1, Debt::onlyTrashed()->count());

        $this->as('accountant')->post('/confirmations', ['date' => today()->toDateString(), 'sales' => [$kept->id]])
            ->assertSessionHas('status', function ($status) {
                return strpos($status, __('confirm.erased', ['count' => 1])) !== false;
            });

        $this->assertNull(Sale::withTrashed()->find($gone->id)); // gone for good
        $this->assertSame(0, Debt::withTrashed()->count());
        $this->assertNotNull(Sale::find($kept->id)->confirmed_at);
        $this->assertSame(0, Ticket::where('sale_id', $gone->id)->count());
    }

    public function testOnlyTheConfirmedDatesDeletedSalesAreErased()
    {
        $this->publishTickets(10);
        $today = $this->sell(1);
        $other = $this->sell(1);
        $this->deleteSale($today);
        $this->deleteSale($other);
        Sale::onlyTrashed()->whereKey($other->id)->update(['sale_date' => '2026-09-01']);

        $this->as('accountant')->post('/confirmations', ['date' => today()->toDateString()]); // nothing checked: only the erasing

        $this->assertNull(Sale::withTrashed()->find($today->id));
        $this->assertNotNull(Sale::onlyTrashed()->find($other->id)); // another date: still waiting
        // the items go with the sale that was erased and stay with the one that waits
        $this->assertSame(0, \App\Models\Sales\SaleItem::where('sale_id', $today->id)->count());
        $this->assertSame(1, \App\Models\Sales\SaleItem::where('sale_id', $other->id)->count());
    }

    public function testConfirmingWithNothingCheckedStillNeedsSomethingToDo()
    {
        $this->as('accountant')->post('/confirmations', ['date' => today()->toDateString()])->assertSessionHasErrors('sales');
    }

    public function testTheConfirmationScreenWarnsAboutWhatWillBeErased()
    {
        $this->publishTickets(5);
        $sale = $this->sell(1);
        $this->deleteSale($sale);

        $this->as('accountant')->get('/confirmations?date=' . today()->toDateString())->assertOk()
            ->assertSee(__('confirm.will_erase', ['count' => 1]))
            ->assertSee(today()->toDateString() . ' (0 / 0 / 1 ' . __('confirm.deleted_short') . ')');
    }

    // ---- a ticket of a deleted sale can never be used again ----

    public function testTheRestrictionOnADeletedSalesTicketCannotBeLifted()
    {
        $sale = $this->spotSell(5);
        $ticket = Ticket::where('sale_id', $sale->id)->first();
        $this->deleteSale($sale);

        $this->as('manager')->post('/tickets/' . $ticket->barcode . '/lift-restriction')->assertSessionHasErrors('barcode');
        $this->assertSame(config('ticket_statuses.restrict'), (int) Ticket::find($ticket->barcode)->status);
        $this->assertSame(__('sale_delete.ticket_sale_deleted'), session('errors')->first('barcode'));

        // still refused once the sale has been erased for good
        $this->as('accountant')->post('/confirmations', ['date' => today()->toDateString()]);
        $this->assertNull(Sale::withTrashed()->find($sale->id));
        $this->as('manager')->post('/tickets/' . $ticket->barcode . '/lift-restriction')->assertSessionHasErrors('barcode');
        $this->assertSame(config('ticket_statuses.restrict'), (int) Ticket::find($ticket->barcode)->status);
    }

    public function testAnOrdinaryRestrictionCanStillBeLifted()
    {
        $this->publishTickets(5);
        $sale = $this->sell(1);
        $ticket = Ticket::where('sale_id', $sale->id)->first();
        $this->as('manager')->post('/tickets/' . $ticket->barcode . '/restrict');

        $this->post('/tickets/' . $ticket->barcode . '/lift-restriction')->assertSessionHasNoErrors();

        $this->assertSame(config('ticket_statuses.sale'), (int) Ticket::find($ticket->barcode)->status);
    }
}
