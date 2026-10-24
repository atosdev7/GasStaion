<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Sales\Sale;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtTest extends TestCase
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

    private function sale($totalAmount)
    {
        return Sale::forceCreate([
            'sale_date' => today()->toDateString(), 'customer_id' => $this->customer->id, 'fuel_type' => 'D',
            'price' => 1, 'amount_kg' => $totalAmount, 'payment_method' => 'bank',
            'total_amount' => $totalAmount, 'paid_amount' => 0, 'unpaid_amount' => $totalAmount,
            'usage' => 'sale', 'user_id' => 1,
        ]);
    }

    public function testBalanceIsAmountMinusSettledFromLinkedPayments()
    {
        $sale = $this->sale(100);
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $sale->id,
            'amount' => 100, 'payment_method' => 'bank', 'user_id' => 1,
        ]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => $debt->id,
            'amount' => 40, 'payment_method' => 'bank', 'paid_date' => '2026-09-23', 'user_id' => 1,
        ]);

        $this->as('manager')->get('/payments')->assertOk()
            ->assertSee('100.00')  // owed
            ->assertSee('40.00')   // settled
            ->assertSee('60.00')   // balance
            ->assertSee(__('debts.debt_open'));
    }

    public function testFullySettledDebtShowsAsSettled()
    {
        $sale = $this->sale(50);
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $sale->id,
            'amount' => 50, 'payment_method' => 'cash', 'user_id' => 1,
        ]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => $debt->id,
            'amount' => 50, 'payment_method' => 'cash', 'paid_date' => '2026-09-23', 'user_id' => 1,
        ]);

        $this->as('manager')->get('/payments')->assertOk()->assertSee(__('debts.debt_settled'));
    }

    public function testUnallocatedPaymentIsNotAppliedToAnyDebt()
    {
        $sale = $this->sale(80);
        Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $sale->id,
            'amount' => 80, 'payment_method' => 'bank', 'user_id' => 1,
        ]);
        // Payment with no debt_id — customer has multiple debts, unclear which this pays down.
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => null,
            'amount' => 30, 'payment_method' => 'cash', 'paid_date' => '2026-09-23', 'user_id' => 1,
        ]);

        $response = $this->as('manager')->get('/payments')->assertOk();
        $response->assertSee('80.00'); // debt still shows full amount owed, unaffected
        $response->assertSee('0.00');  // its settled_amount is 0 — the unallocated payment didn't count toward it
        $response->assertSee(__('debts.unallocated_payments'));
        $response->assertSee('ACME');
    }

    public function testPermissions()
    {
        $this->as('manager')->get('/payments')->assertOk();       // payments.view
        $this->as('accountant')->get('/payments')->assertOk();    // payments.manage implies view
        $this->as('assistant')->get('/payments')->assertForbidden();
        $this->as('cashier')->get('/payments')->assertForbidden();
    }

    // ---- recording a payment ----

    private function recordPayment($extra = [])
    {
        return $this->post('/payments', $extra + [
            'customer_id' => $this->customer->id,
            'amount' => 10,
            'payment_method' => 'cash',
            'paid_date' => '2026-09-23',
        ]);
    }

    public function testRecordPaymentAgainstASpecificDebt()
    {
        $sale = $this->sale(100);
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $sale->id,
            'amount' => 100, 'payment_method' => 'bank', 'user_id' => 1,
        ]);

        $this->as('accountant')->recordPayment(['debt_id' => $debt->id, 'amount' => 40])
            ->assertRedirect('/payments');

        $payment = DebtPayment::first();
        $this->assertSame($debt->id, $payment->debt_id);
        $this->assertSame($this->customer->id, $payment->customer_id);
        $this->assertEquals(40, $payment->amount);
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame('2026-09-23', $payment->date); // its own paid_date
    }

    public function testRecordGeneralPaymentWithNoDebtId()
    {
        $this->as('accountant');

        $this->recordPayment()->assertRedirect('/payments'); // no debt_id given

        $payment = DebtPayment::first();
        $this->assertNull($payment->debt_id);
        $this->assertSame($this->customer->id, $payment->customer_id);
    }

    public function testDebtMustBelongToTheSelectedCustomer()
    {
        $other = Customer::forceCreate(['name' => 'Other Co']);
        $sale = $this->sale(50);
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $sale->id,
            'amount' => 50, 'payment_method' => 'cash', 'user_id' => 1,
        ]);

        $this->as('accountant')->post('/payments', [
            'customer_id' => $other->id, // mismatched — debt belongs to $this->customer, not $other
            'debt_id' => $debt->id,
            'amount' => 10, 'payment_method' => 'cash', 'paid_date' => '2026-09-23',
        ])->assertSessionHasErrors('debt_id');

        $this->assertSame(0, DebtPayment::count());
    }

    public function testRecordPaymentPermissions()
    {
        $this->as('manager')->get('/payments/create')->assertForbidden(); // view only
        $this->recordPayment()->assertForbidden();

        $this->as('accountant')->get('/payments/create')->assertOk();
        $this->recordPayment()->assertRedirect('/payments');
    }

    public function testCreateScreenRenders()
    {
        $sale = $this->sale(20);
        Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $sale->id,
            'amount' => 20, 'payment_method' => 'cash', 'user_id' => 1,
        ]);

        $this->as('accountant')->get('/payments/create')->assertOk()->assertSee('ACME');
    }

    public function testTheLongSelectsAreSelect2()
    {
        $this->sale(10);

        // the customers, the open debts and the open loans are searchable boxes; the page loads the script that makes them
        $pay = $this->as('accountant')->get('/payments/create')->assertOk()->getContent();
        $this->assertRegExp('/<select id="f_customer_id" name="customer_id" class="form-control js-select2/', $pay);
        $this->assertRegExp('/<select id="f_debt_id" name="debt_id" class="form-control js-select2[^"]*" data-no-placeholder>/', $pay);
        $this->assertStringContainsString('js/select2-init.js', $pay);
        $this->assertStringContainsString("$(customer).on('change'", $pay); // Select2 tells a change through jQuery only
        $this->assertFileExists(public_path('js/select2-init.js'));

        $this->assertRegExp('/<select id="f_loan_id" name="loan_id" class="form-control js-select2/', $this->as('accountant')->get('/loans/create')->assertOk()->getContent());
        $this->assertStringContainsString('<select id="f_debt_customer" name="customer_id" class="form-control js-select2">', $this->as('admin')->get('/balances/debts')->assertOk()->getContent());
        $this->assertStringContainsString('<select id="f_loan_customer" name="customer_id" class="form-control js-select2">', $this->as('admin')->get('/balances/loans')->assertOk()->getContent());
        $this->assertRegExp('/<select id="f_customer_id" name="customer_id" class="form-control js-select2 mr-3" data-width="280px">/', $this->as('accountant')->get('/reports/customer')->assertOk()->getContent());

        // the selects with a few entries stay as they are
        $this->assertStringNotContainsString('js-select2', $this->as('admin')->get('/ticket-publish/create')->assertOk()->getContent());
    }

    // a debt of a sale: ($date, $customer, $fuel, $method, $amount, $paid)
    private function debtOf($date, $customer, $fuel, $method, $amount, $paid = 0)
    {
        $sale = Sale::forceCreate([
            'sale_date' => $date, 'customer_id' => $customer->id, 'fuel_type' => $fuel, 'price' => 1, 'amount_kg' => $amount, 'payment_method' => $method,
            'total_amount' => $amount, 'paid_amount' => 0, 'unpaid_amount' => $amount, 'usage' => 'sale', 'user_id' => 1,
        ]);
        $debt = Debt::forceCreate(['customer_id' => $customer->id, 'sale_id' => $sale->id, 'amount' => $amount, 'payment_method' => $method, 'user_id' => 1]);
        if ($paid) {
            DebtPayment::forceCreate(['customer_id' => $customer->id, 'debt_id' => $debt->id, 'amount' => $paid, 'payment_method' => $method, 'paid_date' => $date, 'user_id' => 1]);
        }

        return $debt;
    }

    public function testThePaymentsTableHasFiltersInTheColumnHeads()
    {
        $other = Customer::forceCreate(['name' => 'Globex']);
        $a = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);              // open
        $b = $this->debtOf('2026-10-01', $other, 'A', 'cash', 50, 50);                   // settled
        $c = $this->debtOf('2026-10-02', $this->customer, 'A', 'cash', 30, 10);          // open (20 left)
        $d = $this->debtOf('2026-10-03', $other, 'D', 'bank', 70);                       // open

        $ids = function ($query) {
            return $this->as('accountant')->get('/payments' . $query)->assertOk()->viewData('debts')->pluck('id')->all();
        };

        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id, $d->id], $ids(''));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids('?sale_date=2026-10-01'));                       // the day of the sale
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $ids('?customer=' . $this->customer->id));
        $this->assertEqualsCanonicalizing([$a->id, $d->id], $ids('?payment_method=bank'));
        $this->assertEqualsCanonicalizing([$a->id, $c->id, $d->id], $ids('?status=open'));
        $this->assertSame([$b->id], $ids('?status=settled'));
        $this->assertSame([$c->id], $ids('?customer=' . $this->customer->id . '&payment_method=cash&status=open'));       // together
        $this->assertSame([], $ids('?sale_date=2026-09-01'));

        // the totals are those of the debts the filters leave
        $response = $this->as('accountant')->get('/payments?customer=' . $this->customer->id)->assertOk();
        $this->assertEquals(130, $response->viewData('totalOwed'));
        $this->assertEquals(10, $response->viewData('totalSettled'));
        $this->assertEquals(120, $response->viewData('totalBalance'));

        // the filters are in the page: the inputs of every column, tied to one form, with the customer as a Select2
        $html = $this->as('accountant')->get('/payments?status=open&payment_method=bank')->assertOk()->getContent();
        $this->assertStringContainsString('<form method="GET" action="' . url('/payments') . '" id="debt-filters">', $html);
        foreach (['sale_date', 'customer', 'payment_method', 'status'] as $name) {
            $this->assertRegExp('/name="' . $name . '" form="debt-filters"/', $html, $name);
        }
        $this->assertRegExp('/id="debt-customer-filter" class="[^"]*js-select2/', $html);
        $this->assertStringContainsString('<option value="open" selected>', $html);
        $this->assertStringContainsString('<option value="bank" selected>', $html);
        $this->assertStringContainsString('All customers', $html);
        $this->assertStringContainsString('All payment methods', $html);
        $this->assertStringNotContainsString('name="fuel_type"', $html);   // no fuel type filter

        // a bad value is refused
        $this->as('accountant')->get('/payments?status=nowhere')->assertSessionHasErrors('status');
        $this->as('accountant')->get('/payments?payment_method=card')->assertSessionHasErrors('payment_method');
        $this->as('accountant')->get('/payments?sale_date=yesterday')->assertSessionHasErrors('sale_date');
    }

    public function testThePaymentsTableHasNoFuelTypeAndLinksEachDebtToItsSale()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);
        Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => null, 'amount' => 25, 'payment_method' => 'cash', 'user_id' => 1]);   // an opening balance

        $html = $this->as('accountant')->get('/payments')->assertOk()->getContent();

        $this->assertStringNotContainsString('<th>Fuel type</th>', $html);
        $this->assertRegExp('/<th>Status<\/th>\s*<th class="text-right">Actions<\/th>/', $html);
        // the sale of the debt is in its Actions drop-down; the opening balance has no sale and no drop-down
        $this->assertSame(1, substr_count($html, 'debt-actions"'));
        $this->assertStringContainsString('href="' . url('/ticket-sales/' . $debt->sale_id) . '"', $html);
        $this->assertStringContainsString('View sale', $html);
        $this->as('accountant')->get('/ticket-sales/' . $debt->sale_id)->assertOk();                       // the link opens the sale

    }

    public function testThePaymentsTableIsPagedByFiftyAndKeepsTheFilters()
    {
        for ($i = 0; $i < 52; $i++) {
            $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 10 + $i);
        }
        $this->debtOf('2026-10-01', $this->customer, 'A', 'cash', 5);

        $first = $this->as('accountant')->get('/payments?payment_method=bank')->assertOk();
        $this->assertCount(50, $first->viewData('debts'));
        $this->assertSame(52, $first->viewData('debts')->total());
        $this->assertStringContainsString('payment_method=bank&amp;page=2', $first->getContent());                // the paging keeps the filter
        $this->assertCount(2, $this->as('accountant')->get('/payments?payment_method=bank&page=2')->viewData('debts'));
    }

    public function testTheUnallocatedPaymentsFollowTheCustomerFilter()
    {
        $other = Customer::forceCreate(['name' => 'Globex']);
        $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 10);
        DebtPayment::forceCreate(['customer_id' => $this->customer->id, 'debt_id' => null, 'amount' => 3, 'payment_method' => 'cash', 'paid_date' => '2026-10-01', 'user_id' => 1]);
        DebtPayment::forceCreate(['customer_id' => $other->id, 'debt_id' => null, 'amount' => 4, 'payment_method' => 'cash', 'paid_date' => '2026-10-01', 'user_id' => 1]);

        $this->assertCount(2, $this->as('accountant')->get('/payments')->viewData('unallocated'));
        $this->assertSame([$other->id], $this->as('accountant')->get('/payments?customer=' . $other->id)->viewData('unallocated')->pluck('customer_id')->all());
    }

    public function testAPaymentCannotBeDatedInTheFuture()
    {
        $this->as('accountant');
        $this->recordPayment(['paid_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('paid_date');
        $this->assertSame(0, DebtPayment::count());
        $this->recordPayment(['paid_date' => today()->toDateString()])->assertRedirect('/payments');
        $this->assertSame(1, DebtPayment::count());
    }

    public function testAPaymentOverTheBalanceIsKeptAndShowsACredit()
    {
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $this->sale(100)->id, 'amount' => 100, 'payment_method' => 'bank', 'user_id' => 1,
        ]);
        $this->as('accountant');

        $response = $this->recordPayment(['debt_id' => $debt->id, 'amount' => 130, 'paid_date' => today()->toDateString()])->assertRedirect('/payments');
        $response->assertSessionHas('warning');
        $this->assertStringContainsString('credit of 30.00', session('warning'));
        $this->assertSame(1, DebtPayment::count());                                        // kept: the customer may pay ahead

        $page = $this->get('/payments')->assertOk();
        $page->assertSee('Credit')->assertSee('-30.00');
        $this->assertSame(-30.0, round($page->viewData('debts')->first()->balance, 2));
        $this->get('/payments')->assertOk();                                                // the warning was shown once
        $this->assertFalse(session()->has('warning'));
    }

    public function testAWrongPaymentIsUndoneByAReversalAndStaysInTheList()
    {
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $this->sale(100)->id, 'amount' => 100, 'payment_method' => 'bank', 'user_id' => 1,
        ]);
        $this->as('accountant');
        $this->recordPayment(['debt_id' => $debt->id, 'amount' => 40, 'payment_method' => 'bank', 'paid_date' => '2026-09-23']);
        $payment = DebtPayment::first();

        $this->post('/payments/' . $payment->id . '/reverse', ['note' => 'typed twice'])->assertRedirect('/payments')->assertSessionHas('status');

        // the wrong payment is untouched, its reversal is a line of its own: -40, today, same customer, debt and method
        $this->assertEquals(40, $payment->fresh()->amount);
        $this->assertSame('2026-09-23', substr((string) $payment->fresh()->paid_date, 0, 10));
        $reversal = DebtPayment::where('reverses_payment_id', $payment->id)->first();
        $this->assertEquals(-40, $reversal->amount);
        $this->assertSame(today()->toDateString(), substr((string) $reversal->paid_date, 0, 10));
        $this->assertSame($debt->id, $reversal->debt_id);
        $this->assertSame('bank', $reversal->payment_method);
        $this->assertStringContainsString('typed twice', $reversal->note);
        $this->assertSame(2, DebtPayment::count());

        // the debt owes its 100 again; the page lists both lines
        $page = $this->get('/payments')->assertOk();
        $this->assertSame(100.0, round($page->viewData('debts')->first()->balance, 2));
        $this->assertSame(0.0, round((float) DebtPayment::where('debt_id', $debt->id)->sum('amount'), 2));
        $page->assertSee('Reversed')->assertSee('Reverses #' . $payment->id)->assertSee('-40.00');

        // it cannot be reversed twice, and a reversal cannot be reversed
        $this->post('/payments/' . $payment->id . '/reverse')->assertSessionHasErrors('payment');
        $this->post('/payments/' . $reversal->id . '/reverse')->assertSessionHasErrors('payment');
        $this->assertSame(2, DebtPayment::count());
    }

    public function testTheDebtSummaryShowsAReversalOnItsOwnDayAndLeavesTheDaysBefore()
    {
        $sale = $this->sale(100);
        $sale->forceFill(['sale_date' => today()->subDays(5)->toDateString()])->save();                // an old debt
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $sale->id, 'amount' => 100, 'payment_method' => 'bank', 'user_id' => 1,
        ]);
        $this->as('accountant');
        $yesterday = today()->subDay()->toDateString();
        $payment = DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 40, 'payment_method' => 'bank', 'paid_date' => $yesterday, 'date' => $yesterday, 'user_id' => 1,
        ]);
        $this->post('/payments/' . $payment->id . '/reverse');

        $row = function ($from, $to) {
            return collect($this->get("/debt-summary?from=$from&to=$to")->viewData('payments'))->firstWhere('name', 'ACME');
        };
        $before = $row($yesterday, $yesterday);                                              // yesterday: the payment of 40 as it was
        $this->assertEquals(40, $before['payments']['bank']);
        $this->assertEquals(60, $before['closing']['bank']);
        $reversalDay = $row(today()->toDateString(), today()->toDateString());               // today: -40 among the payments
        $this->assertEquals(-40, $reversalDay['payments']['bank']);
        $this->assertEquals(60, $reversalDay['opening']['bank']);
        $this->assertEquals(100, $reversalDay['closing']['bank']);
    }

    public function testASaleWhoseOnlyPaymentWasReversedCanBeDeletedAgain()
    {
        $sale = $this->sale(100);
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $sale->id, 'amount' => 100, 'payment_method' => 'bank', 'user_id' => 1,
        ]);
        $this->as('accountant');
        $payment = DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 40, 'payment_method' => 'bank', 'paid_date' => today()->toDateString(), 'user_id' => 1,
        ]);

        \App\Models\Tickets\Ticket::forceCreate([
            'barcode' => '0000006000000001', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 1,
            'status' => config('ticket_statuses.sale'), 'sale_id' => $sale->id,
        ]);

        $this->assertSame('paid', \App\Support\Sales\SaleDeletion::blocker($sale->fresh()));
        $this->post('/payments/' . $payment->id . '/reverse');
        $this->assertNotSame('paid', \App\Support\Sales\SaleDeletion::blocker($sale->fresh()));   // nothing is paid any more
    }

    public function testOnlyThoseWhoManagePaymentsCanReverseOne()
    {
        $debt = Debt::forceCreate([
            'customer_id' => $this->customer->id, 'sale_id' => $this->sale(100)->id, 'amount' => 100, 'payment_method' => 'bank', 'user_id' => 1,
        ]);
        $payment = DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 40, 'payment_method' => 'bank', 'paid_date' => today()->toDateString(), 'user_id' => 1,
        ]);

        foreach (['manager', 'assistant', 'cashier'] as $role) {
            $this->as($role)->post('/payments/' . $payment->id . '/reverse')->assertForbidden();
        }
        $this->assertSame(1, DebtPayment::count());
    }}
