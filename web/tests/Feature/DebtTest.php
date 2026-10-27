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
        $this->as('assistant')->get('/payments')->assertOk();     // the assistant records payments too
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
        $this->as('cashier')->get('/payments/create')->assertForbidden();
        $this->recordPayment()->assertForbidden();
        $this->as('manager')->get('/payments/create')->assertOk();         // the manager can do everything the accountant and the assistant can

        $this->as('accountant')->get('/payments/create')->assertOk();
        $this->recordPayment()->assertRedirect('/payments');
        $this->as('assistant')->get('/payments/create')->assertOk();
        $this->recordPayment()->assertRedirect('/payments');
        $this->assertSame(2, DebtPayment::count());
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
        // every debt has an Actions drop-down (View, Settle); the sale of the debt is in it, the opening balance has no sale
        $this->assertSame(2, substr_count($html, 'debt-actions"'));
        $this->assertSame(1, substr_count($html, 'View sale'));
        $this->assertStringContainsString('href="' . url('/ticket-sales/' . $debt->sale_id) . '"', $html);
        $this->assertStringContainsString('View sale', $html);
        $this->as('accountant')->get('/ticket-sales/' . $debt->sale_id)->assertOk();                       // the link opens the sale

    }

    public function testTheDebtsAndThePaymentsAreTwoTabsAndAPageOfAListKeepsItsTab()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);
        for ($i = 0; $i < 26; ++$i) {
            DebtPayment::forceCreate([
                'customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 1, 'payment_method' => 'bank', 'paid_date' => '2026-10-02', 'user_id' => 1,
            ]);
        }

        $html = $this->as('accountant')->get('/payments')->assertOk()->getContent();
        $this->assertStringContainsString('id="tab-debts-link"', $html);
        $this->assertStringContainsString('id="tab-payments-link"', $html);
        $this->assertRegExp('/id="tab-debts-link"[^>]*>Debts<\/a>.*id="tab-payments-link"[^>]*>Payments<\/a>/s', $html);
        $this->assertRegExp('/class="nav-link active" id="tab-debts-link"/', $html);                              // the debts first
        $this->assertSame(1, substr_count($html, 'id="tab-debts" role="tabpanel"'));
        $this->assertSame(1, substr_count($html, 'id="tab-payments" role="tabpanel"'));
        // the filters of the debts stay on their tab, and the pages of the payments lead back to the payments tab
        $this->assertStringContainsString('<input type="hidden" name="tab" value="debts">', $html);
        $this->assertStringContainsString('tab=payments', $html);
        $payments = $this->get('/payments?tab=payments')->assertOk()->getContent();
        $this->assertRegExp('/class="nav-link active" id="tab-payments-link"/', $payments);
        $this->assertRegExp('/class="tab-pane fade show active" id="tab-payments"/', $payments);
    }

    public function testTheSettleActionOpensThePaymentFormFilledInForThatDebt()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);
        DebtPayment::forceCreate(['customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 30, 'payment_method' => 'bank', 'paid_date' => '2026-10-02', 'user_id' => 1]);
        $settled = $this->debtOf('2026-10-01', $this->customer, 'D', 'cash', 20);
        DebtPayment::forceCreate(['customer_id' => $this->customer->id, 'debt_id' => $settled->id, 'amount' => 20, 'payment_method' => 'cash', 'paid_date' => '2026-10-02', 'user_id' => 1]);

        // Settle is in the drop-down of an open debt, for those who record payments; a settled debt has none
        $html = $this->as('accountant')->get('/payments')->assertOk()->getContent();
        $this->assertStringContainsString('href="' . route('payments.create', ['debt_id' => $debt->id]) . '"', $html);
        $this->assertStringNotContainsString('debt_id=' . $settled->id, $html);
        $this->as('assistant')->get('/payments')->assertSee('debt_id=' . $debt->id);
        $this->as('manager')->get('/payments')->assertOk()->assertSee('debt_id=' . $debt->id);

        // the form: the customer, the debt, its balance and its payment method are filled in
        $form = $this->as('accountant')->get('/payments/create?debt_id=' . $debt->id)->assertOk()->getContent();
        $this->assertRegExp('/<option value="' . $this->customer->id . '" selected>/', $form);
        $this->assertRegExp('/<option value="' . $debt->id . '"[^>]*selected/s', $form);
        $this->assertStringContainsString('name="amount" type="number" step="0.01" min="0" value="70"', $form);   // 100 - 30
        $this->assertRegExp('/<option value="bank" selected>/', $form);

        // a debt that is settled, or an unknown one, leaves the form empty
        $this->get('/payments/create?debt_id=' . $settled->id)->assertOk()->assertDontSee('selected>ACME', false);
        $this->get('/payments/create?debt_id=nothing')->assertOk();
        $this->get('/payments/create')->assertOk()->assertSee('name="amount" type="number" step="0.01" min="0" value=""', false);
    }

    public function testTheViewActionShowsTheDebtItsSaleAndItsPayments()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);
        $paid = DebtPayment::forceCreate(['customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 30, 'payment_method' => 'bank', 'paid_date' => '2026-10-02', 'user_id' => 1, 'note' => 'transfer']);
        DebtPayment::forceCreate(['customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => -30, 'payment_method' => 'bank', 'paid_date' => '2026-10-03', 'user_id' => 1, 'reverses_payment_id' => $paid->id]);
        DebtPayment::forceCreate(['customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 45, 'payment_method' => 'bank', 'paid_date' => '2026-10-04', 'user_id' => 1]);
        DebtPayment::forceCreate(['customer_id' => $this->customer->id, 'debt_id' => null, 'amount' => 999, 'payment_method' => 'bank', 'paid_date' => '2026-10-04', 'user_id' => 1, 'note' => 'general']);   // not of this debt

        $html = $this->as('accountant')->get('/payments')->assertOk()->getContent();
        $this->assertStringContainsString('class="dropdown-item debt-detail"', $html);
        $this->assertStringContainsString('href="' . url('/debts/' . $debt->id) . '"', $html);

        $popup = $this->get('/debts/' . $debt->id . '?modal=1')->assertOk()->assertDontSee('<html', false)->getContent();
        $this->assertStringContainsString('ACME', $popup);
        $this->assertStringContainsString('href="' . url('/ticket-sales/' . $debt->sale_id) . '"', $popup);      // the link to the sale
        $this->assertStringContainsString('Payments of this debt (3)', $popup);                                    // the payment, its reversal and the one of day 4
        $this->assertStringContainsString('transfer', $popup);
        $this->assertStringNotContainsString('general', $popup);
        $this->assertRegExp('/<tr class="font-weight-bold"><th>Balance<\/th><td>55.00<\/td>/', $popup);            // 100 - 45 (the 30 was reversed)
        $this->assertStringContainsString('Reversed', $popup);
        $this->get('/debts/' . $debt->id)->assertOk()->assertSee('<html', false)->assertSee('Back to the list');
        $this->get('/debts/11111111-1111-1111-1111-111111111111')->assertNotFound();

        // an opening balance has no sale; the cashier has no access
        $opening = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => null, 'amount' => 25, 'payment_method' => 'cash', 'user_id' => 1]);
        $this->get('/debts/' . $opening->id . '?modal=1')->assertOk()->assertSee('Opening balance')->assertDontSee('/ticket-sales/');
        $this->as('cashier')->get('/debts/' . $debt->id . '?modal=1')->assertForbidden();
    }

    private function paymentOn($debt, $amount = 30, $extra = [])
    {
        return DebtPayment::forceCreate($extra + [
            'customer_id' => $this->customer->id, 'debt_id' => $debt ? $debt->id : null, 'amount' => $amount, 'payment_method' => 'bank',
            'paid_date' => '2026-10-02', 'date' => '2026-10-02', 'user_id' => 1, 'note' => 'first',
        ]);
    }

    public function testAPaymentEnteredWrongIsEditedByThoseWhoRecordPayments()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);
        $other = $this->debtOf('2026-10-01', $this->customer, 'D', 'cash', 60);
        $payment = $this->paymentOn($debt);

        foreach (['accountant', 'assistant'] as $role) {
            $this->as($role)->get('/payments/' . $payment->id . '/edit')->assertOk()->assertSee('ACME')->assertSee('value="30.00"', false);
        }
        $html = $this->get('/payments/' . $payment->id . '/edit')->getContent();
        $this->assertStringContainsString('data-method="cash"', $html);                // the method of each debt, to follow the debt chosen

        $this->put('/payments/' . $payment->id, ['debt_id' => $other->id, 'amount' => 45, 'payment_method' => 'cash', 'paid_date' => '2026-10-03', 'note' => 'right debt'])
            ->assertRedirect('/payments?tab=payments')->assertSessionHas('status');
        $fresh = $payment->fresh();
        $this->assertSame($other->id, $fresh->debt_id);
        $this->assertEquals(45, $fresh->amount);
        $this->assertSame('cash', $fresh->payment_method);
        $this->assertSame('2026-10-03', substr((string) $fresh->paid_date, 0, 10));
        $this->assertSame('2026-10-03', substr((string) $fresh->date, 0, 10));          // the confirmation date follows
        $this->assertSame('right debt', $fresh->note);
        $this->assertSame($this->customer->id, $fresh->customer_id);                    // the customer stays
        $this->assertSame(1, DebtPayment::count());                                     // edited, not added

        // a general payment, and a payment over the balance keeps its warning
        $this->put('/payments/' . $payment->id, ['debt_id' => '', 'amount' => 5, 'payment_method' => 'bank', 'paid_date' => '2026-10-03'])->assertSessionHas('status');
        $this->assertNull($payment->fresh()->debt_id);
        $this->put('/payments/' . $payment->id, ['debt_id' => $other->id, 'amount' => 100, 'payment_method' => 'cash', 'paid_date' => '2026-10-03'])->assertSessionHas('warning');

        // the list offers the edit
        $this->get('/payments')->assertOk()->assertSee(route('payments.edit', $payment->id));
    }

    public function testAPaymentIsEditedWithValidDataOnly()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);
        $mine = $this->paymentOn($debt);
        $stranger = Customer::forceCreate(['name' => 'Other Ltd']);
        $foreign = Debt::forceCreate(['customer_id' => $stranger->id, 'sale_id' => null, 'amount' => 50, 'payment_method' => 'cash', 'user_id' => 1]);
        $form = ['debt_id' => $debt->id, 'amount' => 30, 'payment_method' => 'bank', 'paid_date' => '2026-10-02'];

        $this->as('accountant');
        $this->put('/payments/' . $mine->id, ['debt_id' => $foreign->id] + $form)->assertSessionHasErrors('debt_id');                  // the debt of another customer
        $this->put('/payments/' . $mine->id, ['amount' => 0] + $form)->assertSessionHasErrors('amount');
        $this->put('/payments/' . $mine->id, ['payment_method' => 'card'] + $form)->assertSessionHasErrors('payment_method');
        $this->put('/payments/' . $mine->id, ['paid_date' => today()->addDay()->toDateString()] + $form)->assertSessionHasErrors('paid_date');
        $this->put('/payments/' . $mine->id, ['note' => str_repeat('x', 256)] + $form)->assertSessionHasErrors('note');
        $this->assertEquals(30, $mine->fresh()->amount);
        $this->assertSame($debt->id, $mine->fresh()->debt_id);
    }

    public function testAReversalAReversedPaymentAndAConfirmedOneAreNotEdited()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);
        $reversed = $this->paymentOn($debt, 20);
        $reversal = $this->paymentOn($debt, -20, ['reverses_payment_id' => $reversed->id]);
        $confirmed = $this->paymentOn($debt, 30, ['confirmed_at' => now(), 'confirmed_by' => 1]);
        $open = $this->paymentOn($debt, 10);
        $form = ['debt_id' => $debt->id, 'amount' => 99, 'payment_method' => 'bank', 'paid_date' => '2026-10-02'];

        $this->as('accountant');
        foreach ([$reversed, $reversal, $confirmed] as $row) {
            $this->get('/payments/' . $row->id . '/edit')->assertRedirect('/payments?tab=payments')->assertSessionHasErrors('payment');
            $this->put('/payments/' . $row->id, $form)->assertSessionHasErrors('payment');
        }
        $this->assertEquals(20, $reversed->fresh()->amount);
        $this->assertEquals(-20, $reversal->fresh()->amount);
        $this->assertEquals(30, $confirmed->fresh()->amount);

        // the list: Edit for the open one, a lock for the confirmed one, nothing for the reversed one and the reversal
        $html = $this->get('/payments?tab=payments')->assertOk()->getContent();
        $this->assertStringContainsString(route('payments.edit', $open->id), $html);
        $this->assertStringNotContainsString(route('payments.edit', $confirmed->id), $html);
        $this->assertStringNotContainsString(route('payments.edit', $reversed->id), $html);
        $this->assertStringNotContainsString(route('payments.edit', $reversal->id), $html);
        $this->assertStringContainsString('fa-lock', $html);

        // a confirmed payment cannot be reversed either (no button, and the server refuses); a manager takes the confirmation back to edit it
        $this->assertStringNotContainsString(route('payments.reverse', $confirmed->id), $html);
        $this->assertStringContainsString(route('payments.reverse', $open->id), $html);
        $this->post('/payments/' . $confirmed->id . '/reverse')->assertSessionHasErrors('payment');
        $this->assertSame(0, DebtPayment::where('reverses_payment_id', $confirmed->id)->count());
        $confirmed2 = $this->paymentOn($debt, 7, ['confirmed_at' => now(), 'confirmed_by' => 1]);
        $this->as('manager')->post('/payments/' . $confirmed2->id . '/unconfirm')->assertRedirect();
        $this->as('accountant')->put('/payments/' . $confirmed2->id, ['amount' => 8] + $form)->assertSessionHas('status');
        $this->assertEquals(8, $confirmed2->fresh()->amount);
    }

    public function testOnlyThoseWhoRecordPaymentsEditOne()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);
        $payment = $this->paymentOn($debt);
        foreach (['cashier'] as $role) {
            $this->as($role)->get('/payments/' . $payment->id . '/edit')->assertForbidden();
            $this->put('/payments/' . $payment->id, ['amount' => 1])->assertForbidden();
        }
        $this->assertEquals(30, $payment->fresh()->amount);
    }

    public function testTheMethodOfThePaymentFollowsTheDebtChosen()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'bank', 100);
        $cash = $this->debtOf('2026-10-02', $this->customer, 'D', 'cash', 60);

        $html = $this->as('accountant')->get('/payments/create')->assertOk()->getContent();
        $this->assertStringContainsString('data-method="bank"', $html);
        $this->assertStringContainsString('data-method="cash"', $html);
        $this->assertStringContainsString("var method = \$(debt).find('option:selected').data('method');", $html);   // the form changes the method when a debt is chosen
        // a debt chosen with Settle: its own method is selected
        $this->get('/payments/create?debt_id=' . $cash->id)->assertOk()->assertSee('<option value="cash" selected>', false);
    }

    public function testACustomerWhoStillOwesIsNotDeleted()
    {
        $this->as('accountant');
        $debtor = Customer::forceCreate(['name' => 'Debtor']);
        $debt = Debt::forceCreate(['customer_id' => $debtor->id, 'sale_id' => null, 'amount' => 50, 'payment_method' => 'cash', 'user_id' => 1]);

        $this->delete('/customers/' . $debtor->id)->assertRedirect('/customers')->assertSessionHas('error');
        $this->assertStringContainsString('1 debts (50.00)', session('error'));
        $this->assertNotNull(Customer::find($debtor->id));

        // a debt that is settled (a payment and a return both count) lets him go
        DebtPayment::forceCreate(['customer_id' => $debtor->id, 'debt_id' => $debt->id, 'amount' => 50, 'payment_method' => 'cash', 'paid_date' => '2026-10-02', 'user_id' => 1]);
        $this->delete('/customers/' . $debtor->id)->assertSessionHas('status');
        $this->assertNull(Customer::find($debtor->id));

        // an open ticket loan stops it too, a settled one does not
        $borrower = Customer::forceCreate(['name' => 'Borrower']);
        $loan = \App\Models\Debts\TicketLoan::forceCreate(['loaned_at' => '2026-10-01 09:00:00', 'customer_id' => $borrower->id, 'fuel_type' => 'D', 'amount_kg' => 20, 'settled_amount_kg' => 5, 'user_id' => 1]);
        $this->delete('/customers/' . $borrower->id)->assertSessionHas('error');
        $this->assertStringContainsString('1 ticket loans (15.00 kg)', session('error'));
        $this->assertNotNull(Customer::find($borrower->id));
        $loan->forceFill(['settled_amount_kg' => 20])->save();
        $this->delete('/customers/' . $borrower->id)->assertSessionHas('status');
        $this->assertNull(Customer::find($borrower->id));

        // the built-in customer is still never deleted
        $this->delete('/customers/' . Customer::individual()->id)->assertForbidden();
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

        foreach (['cashier'] as $role) {
            $this->as($role)->post('/payments/' . $payment->id . '/reverse')->assertForbidden();
        }
        $this->assertSame(1, DebtPayment::count());

        // the assistant records and reverses payments like the accountant
        $this->as('assistant')->post('/payments/' . $payment->id . '/reverse')->assertSessionHas('status');
        $this->assertSame(2, DebtPayment::count());
    }

    public function testThePaymentsTabFiltersByPaidOnCustomerMethodAndNote()
    {
        $other = Customer::forceCreate(['name' => 'Globex']);
        $mk = function ($customer, $date, $method, $note) {
            return DebtPayment::forceCreate(['customer_id' => $customer->id, 'debt_id' => null, 'amount' => 5, 'payment_method' => $method, 'paid_date' => $date, 'date' => $date, 'note' => $note, 'user_id' => 1])->id;
        };
        $a = $mk($this->customer, '2026-10-01', 'cash', 'first instalment');
        $b = $mk($this->customer, '2026-10-02', 'bank', 'transfer 100%');
        $c = $mk($other, '2026-10-02', 'cash', null);

        $ids = function ($query) {
            return $this->as('accountant')->get('/payments?tab=payments' . $query)->assertOk()->viewData('payments')->pluck('id')->all();
        };
        $this->assertEqualsCanonicalizing([$a, $b, $c], $ids(''));
        $this->assertEqualsCanonicalizing([$b, $c], $ids('&paid_date=2026-10-02'));
        $this->assertEqualsCanonicalizing([$a, $b], $ids('&pay_customer=' . $this->customer->id));
        $this->assertEqualsCanonicalizing([$a, $c], $ids('&pay_method=cash'));
        $this->assertSame([$a], $ids('&note=instal'));                                      // a part of the note
        $this->assertSame([$b], $ids('&note=' . urlencode('100%')));                         // % is not a wildcard
        $this->assertSame([$b], $ids('&paid_date=2026-10-02&pay_customer=' . $this->customer->id . '&pay_method=bank'));   // together
        $this->assertSame([], $ids('&paid_date=2026-09-01'));

        // the filters of the debts do not touch the payments
        $this->assertEqualsCanonicalizing([$a, $b, $c], $ids('&customer=' . $other->id));

        $this->as('accountant')->get('/payments?pay_method=card')->assertSessionHasErrors('pay_method');
        $this->get('/payments?paid_date=abc')->assertSessionHasErrors('paid_date');

        // the inputs are in the head of the columns, tied to the form of the tab; the paging keeps them
        $html = $this->get('/payments?tab=payments&pay_method=bank')->assertOk()->getContent();
        $this->assertStringContainsString('id="payment-filters"', $html);
        foreach (['paid_date', 'pay_customer', 'pay_method', 'note'] as $name) {
            $this->assertRegExp('/name="' . $name . '" form="payment-filters"/', $html, $name);
        }
        $this->assertStringContainsString('<option value="bank" selected>', $html);
    }

    public function testTheSubmenuOfThePaymentsIsCalledDebtPayments()
    {
        $this->assertSame('Debt Payments', __('menu.payments'));
        $this->as('accountant')->get('/')->assertOk()->assertSee('Debt Payments');
    }

    public function testReversingAPaymentAsksInAModalWithTheReason()
    {
        $payment = DebtPayment::forceCreate(['customer_id' => $this->customer->id, 'debt_id' => null, 'amount' => 5, 'payment_method' => 'cash', 'paid_date' => '2026-10-01', 'date' => '2026-10-01', 'user_id' => 1]);

        $html = $this->as('accountant')->get('/payments?tab=payments')->assertOk()->getContent();
        $this->assertStringContainsString('data-action="' . url('/payments/' . $payment->id . '/reverse') . '"', $html);   // the button only opens the window
        $this->assertStringContainsString('id="reverse-modal"', $html);
        $this->assertStringContainsString('Do you really want to reverse this payment?', $html);
        $this->assertRegExp('/id="reverse-note" name="note" maxlength="200" autocomplete="off"/', $html);                 // the reason is asked there
        $this->assertSame(1, substr_count($html, 'name="note"') - 1);                                                     // (one more is the filter of the notes): none in the rows
    }

    public function testThePaymentHasAViewAndTheReversalBadgesLinkToIt()
    {
        $debt = $this->debtOf('2026-10-01', $this->customer, 'D', 'cash', 100);
        $paid = $this->paymentOn($debt, 30, ['note' => 'by hand']);
        $other = $this->paymentOn($debt, 10);
        $this->as('accountant')->post('/payments/' . $paid->id . '/reverse', ['note' => 'typed twice'])->assertSessionHas('status');
        $reversal = DebtPayment::where('reverses_payment_id', $paid->id)->first();

        // the badges of the list are links to the other payment (the popup)
        $html = $this->get('/payments?tab=payments')->assertOk()->getContent();
        $this->assertRegExp('/<a href="' . preg_quote(url('/payments/' . $paid->id), '/') . '" class="badge badge-secondary payment-detail">Reverses #' . $paid->id . '<\/a>/', $html);
        $this->assertRegExp('/<a href="' . preg_quote(url('/payments/' . $reversal->id), '/') . '" class="badge badge-secondary payment-detail">Reversed<\/a>/', $html);

        // "Debt of" of a payment in the list opens the debt in the popup
        $this->assertRegExp('/<a href="' . preg_quote(route('debts.show', $debt->id), '/') . '" class="debt-detail">2026-10-01<\/a>/', $html);

        // the view: the page and the popup (modal=1), with the debt it pays and the payment it is tied to
        $page = $this->get('/payments/' . $paid->id)->assertOk()->assertSee('by hand')->assertSee('30.00')->assertSee('Reversed');
        $this->assertStringContainsString(route('debts.show', $debt->id), $page->getContent());
        $this->assertStringContainsString(url('/payments/' . $reversal->id), $page->getContent());
        $popup = $this->get('/payments/' . $reversal->id . '?modal=1')->assertOk()->assertSee('Reverses #' . $paid->id)->assertSee('-30.00')->assertSee('typed twice');
        $this->assertStringNotContainsString('<html', $popup->getContent());
        $this->assertStringContainsString(url('/payments/' . $paid->id), $popup->getContent());
        $this->get('/payments/' . $other->id)->assertOk()->assertDontSee('Reversed');
        $this->get('/payments/99999')->assertNotFound();

        // everyone who views the payments may see it, the cashier may not
        foreach (['manager', 'assistant'] as $role) {
            $this->as($role)->get('/payments/' . $paid->id)->assertOk();
        }
        $this->as('cashier')->get('/payments/' . $paid->id)->assertForbidden();
    }
}
