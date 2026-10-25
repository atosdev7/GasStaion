<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtAdjustment;
use App\Models\Debts\DebtPayment;
use App\Models\Sales\Customer;
use App\Models\Sales\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The aging of the debts and the statement of a customer. Runs against the gasstation_test database (see phpunit.xml).
 */
class DebtAgingStatementTest extends TestCase
{
    use RefreshDatabase;

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    // an opening balance entered $days days ago (it is counted from the day it was entered)
    private function debt($customer, $amount, $days, $method = 'cash')
    {
        return Debt::forceCreate([
            'customer_id' => $customer->id, 'sale_id' => null, 'amount' => $amount, 'payment_method' => $method, 'user_id' => 1,
            'created_at' => today()->subDays($days)->toDateString() . ' 10:00:00',
        ]);
    }

    private function pay($customer, $debt, $amount, $date = '2026-10-05', $extra = [])
    {
        return DebtPayment::forceCreate($extra + [
            'customer_id' => $customer->id, 'debt_id' => $debt ? $debt->id : null, 'amount' => $amount, 'payment_method' => 'cash', 'paid_date' => $date, 'date' => $date, 'user_id' => 1,
        ]);
    }

    private function rows($html)
    {
        preg_match_all('/<tr class="aging-row">\s*<td>([^<]*)<\/td>(.*?)<\/tr>/s', $html, $m, PREG_SET_ORDER);
        $rows = [];
        foreach ($m as $row) {
            preg_match_all('/<td[^>]*>\s*([\d,.]+)\s*<\/td>/', $row[2], $n);
            $rows[$row[1]] = $n[1];                                   // 4 buckets, owed, oldest, credit
        }

        return $rows;
    }

    public function testTheAgingShowsWhatEachCustomerOwesByTheAgeOfTheDebtOldestFirst()
    {
        $a = Customer::forceCreate(['name' => 'Alpha']);
        $b = Customer::forceCreate(['name' => 'Beta']);
        $settled = Customer::forceCreate(['name' => 'Settled Ltd']);
        $this->debt($a, 100, 3);                                      // 0 - 7 days
        $second = $this->debt($a, 50, 20);                            // 8 - 30 days, 10 paid
        $this->pay($a, $second, 10);
        $this->pay($a, null, 25);                                     // paid without naming a debt: credit, offsets nothing
        $this->debt($b, 70, 100, 'bank');                             // over 60 days
        $done = $this->debt($settled, 40, 10);
        $this->pay($settled, $done, 40);                              // settled: not here

        $page = $this->as('accountant')->get('/debt-aging')->assertOk();
        $html = $page->getContent();
        $rows = $this->rows($html);
        $this->assertSame(['Beta', 'Alpha'], array_keys($rows));       // the oldest debt first
        $this->assertSame(['0.00', '0.00', '0.00', '70.00', '70.00', '100', '0.00'], $rows['Beta']);
        $this->assertSame(['100.00', '40.00', '0.00', '0.00', '140.00', '20', '25.00'], $rows['Alpha']);
        $this->assertArrayNotHasKey('Settled Ltd', $rows);                  // (his name is in the list of the customers)
        $this->assertRegExp('/<tfoot>.*?100.00.*?40.00.*?70.00.*?210.00.*?25.00/s', $html);       // the totals
        $this->assertStringContainsString('0 - 7 days', $html);
        $this->assertStringContainsString('Over 60 days', $html);
        $this->assertStringContainsString(route('debt-statement.show', ['customer' => $a->id]), $html);   // each row leads to the statement

        // the filters: a customer, a payment method
        $this->assertSame(['Alpha'], array_keys($this->rows($this->get('/debt-aging?customer=' . $a->id)->getContent())));
        $this->assertSame(['Beta'], array_keys($this->rows($this->get('/debt-aging?payment_method=bank')->getContent())));
        $this->get('/debt-aging?customer=abc')->assertSessionHasErrors('customer');
        $this->get('/debt-aging?payment_method=card')->assertSessionHasErrors('payment_method');
    }

    public function testNobodyOwingAnythingSaysSo()
    {
        $this->as('accountant')->get('/debt-aging')->assertOk()->assertSee('Nobody owes anything.');
    }

    public function testTheBucketsAreOfTheDaysSinceTheDebt()
    {
        $controller = \App\Http\Controllers\Debts\DebtAgingController::class;
        $this->assertSame(0, $controller::bucketOf(0));
        $this->assertSame(0, $controller::bucketOf(7));
        $this->assertSame(1, $controller::bucketOf(8));
        $this->assertSame(1, $controller::bucketOf(30));
        $this->assertSame(2, $controller::bucketOf(31));
        $this->assertSame(2, $controller::bucketOf(60));
        $this->assertSame(3, $controller::bucketOf(61));
        $this->assertSame(3, $controller::bucketOf(900));
    }

    public function testTheStatementListsTheDebtsReturnsAndPaymentsWithTheRunningBalance()
    {
        $customer = Customer::forceCreate(['name' => 'ACME']);
        $before = Debt::forceCreate(['customer_id' => $customer->id, 'sale_id' => null, 'amount' => 100, 'payment_method' => 'cash', 'user_id' => 1, 'created_at' => '2026-09-15 10:00:00']);
        $sale = Sale::forceCreate([
            'customer_id' => $customer->id, 'sale_type' => 'ticket', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 25, 'payment_method' => 'bank', 'total_amount' => 50,
            'paid_amount' => 0, 'unpaid_amount' => 50, 'usage' => 'sale', 'sale_date' => '2026-10-02', 'user_id' => 1,
        ]);
        $debt = Debt::forceCreate(['customer_id' => $customer->id, 'sale_id' => $sale->id, 'amount' => 50, 'payment_method' => 'bank', 'user_id' => 1]);
        $paid = $this->pay($customer, $debt, 30, '2026-10-05');
        DebtAdjustment::forceCreate(['debt_id' => $debt->id, 'customer_id' => $customer->id, 'amount' => -20, 'adjusted_date' => '2026-10-06', 'user_id' => 1]);
        $this->pay($customer, $debt, -30, '2026-10-07', ['reverses_payment_id' => $paid->id]);
        $this->pay($customer, null, 10, '2026-10-08', ['note' => 'on account']);
        $this->pay($customer, $before, 5, '2026-11-02');                                   // after the period: not in it

        $page = $this->as('accountant')->get('/debt-statement?customer=' . $customer->id . '&from=2026-10-01&to=2026-10-31')->assertOk();
        $html = $page->getContent();

        $this->assertRegExp('/statement-opening">\s*<td>2026-10-01<\/td>\s*<td colspan="3">Owed at the start<\/td>\s*<td><\/td>\s*<td class="text-right">100.00<\/td>/', $html);
        preg_match_all('/<tr class="statement-line">\s*<td>([^<]*)<\/td>\s*<td>([^<]*)<\/td>\s*<td>([^<]*)<\/td>\s*<td>([^<]*)<\/td>\s*<td class="text-right[^"]*">([^<]*)<\/td>\s*<td class="text-right">([^<]*)<\/td>/', $html, $m, PREG_SET_ORDER);
        $lines = array_map(function ($row) { return array_slice($row, 1); }, $m);
        $this->assertSame([
            ['2026-10-02', 'Sale on credit', 'Bank', '', '50.00', '150.00'],
            ['2026-10-05', 'Payment', 'Cash', '', '-30.00', '120.00'],
            ['2026-10-06', 'Return (cut)', '', '', '-20.00', '100.00'],
            ['2026-10-07', 'Payment reversed', 'Cash', '', '30.00', '130.00'],
            ['2026-10-08', 'Payment (not tied to a debt)', 'Cash', 'on account', '-10.00', '120.00'],
        ], $lines);
        $this->assertRegExp('/statement-closing">\s*<td>2026-10-31<\/td>\s*<td colspan="3">Owed at the end<\/td>\s*<td><\/td>\s*<td class="text-right">120.00<\/td>/', $html);

        // the debts still open today, with their age: the old balance (100, the payment of November is tied to it: 95) and the sale (50 - 20 returned, the payment was reversed)
        preg_match_all('/<tr class="statement-open-debt">\s*<td>([^<]*)<\/td>.*?<td class="text-right">([\d,.]+)<\/td>\s*<td class="text-right">(\d+)<\/td>\s*<\/tr>/s', $html, $o, PREG_SET_ORDER);
        $this->assertSame(['2026-09-15 (Opening balance)', '95.00'], [$o[0][1], $o[0][2]]);
        $this->assertSame(['2026-10-02', '30.00'], [$o[1][1], $o[1][2]]);
        $this->assertStringContainsString('Owed today', $html);
        $this->assertStringContainsString('125.00', $html);                                  // 95 + 30
        $this->assertStringContainsString('onclick="window.print()"', $html);

        // another period: what was owed before it is the start
        $later = $this->get('/debt-statement?customer=' . $customer->id . '&from=2026-10-06&to=2026-10-31')->getContent();
        $this->assertRegExp('/statement-opening">\s*<td>2026-10-06<\/td>\s*<td colspan="3">Owed at the start<\/td>\s*<td><\/td>\s*<td class="text-right">120.00<\/td>/', $later);   // 100 + 50 - 30
        $this->assertSame(1, substr_count($later, 'Payment (not tied to a debt)'));
    }

    public function testTheStatementNeedsACustomerAndAValidPeriod()
    {
        $customer = Customer::forceCreate(['name' => 'ACME']);

        $this->as('accountant')->get('/debt-statement')->assertOk()->assertSee('Choose a customer to see the statement.');
        $this->get('/debt-statement?customer=99999')->assertSessionHasErrors('customer');
        $this->get('/debt-statement?customer=' . $customer->id . '&from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/debt-statement?customer=' . $customer->id)->assertOk()->assertSee('Nothing happened in this period.')->assertSee('No debt is open.');
    }

    public function testOnlyThoseWhoSeeThePaymentsSeeTheAgingAndTheStatement()
    {
        $customer = Customer::forceCreate(['name' => 'ACME']);
        foreach (['accountant', 'assistant', 'manager'] as $role) {
            $this->as($role)->get('/debt-aging')->assertOk();
            $this->get('/debt-statement?customer=' . $customer->id)->assertOk();
        }
        $this->as('cashier')->get('/debt-aging')->assertForbidden();
        $this->get('/debt-statement')->assertForbidden();

        // two submenus of Debts Management, after the Summary
        $html = $this->as('accountant')->get('/')->assertOk()->getContent();
        $html = substr($html, strpos($html, '>Debts Management<'));
        $this->assertGreaterThan(strpos($html, '>Summary<'), strpos($html, '>Aging<'));
        $this->assertGreaterThan(strpos($html, '>Aging<'), strpos($html, '>Statement<'));
    }
}
