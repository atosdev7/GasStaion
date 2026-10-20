<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Sales\Sale;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtSummaryTest extends TestCase
{
    use RefreshDatabase;

    private $acme;

    private $globex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->acme = Customer::forceCreate(['name' => 'ACME']);
        $this->globex = Customer::forceCreate(['name' => 'Globex']);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function debt($customer, $date, $amount, $method = 'bank')
    {
        $saleId = null;
        if ($date !== null) {
            $saleId = Sale::forceCreate([
                'sale_date' => $date, 'customer_id' => $customer->id, 'fuel_type' => 'D', 'price' => 1, 'amount_kg' => $amount, 'payment_method' => 'bank',
                'total_amount' => $amount, 'paid_amount' => 0, 'unpaid_amount' => $amount, 'usage' => 'sale', 'user_id' => 1,
            ])->id;
        }

        return Debt::forceCreate(['customer_id' => $customer->id, 'sale_id' => $saleId, 'amount' => $amount, 'payment_method' => $method, 'user_id' => 1]);
    }

    private function payment($customer, $date, $amount, $debt = null, $method = 'cash')
    {
        return DebtPayment::forceCreate([
            'customer_id' => $customer->id, 'debt_id' => $debt ? $debt->id : null, 'amount' => $amount, 'payment_method' => $method, 'paid_date' => $date, 'user_id' => 1,
        ]);
    }

    private function loan($customer, $fuel, $date, $kg)
    {
        return TicketLoan::forceCreate(['customer_id' => $customer->id, 'fuel_type' => $fuel, 'amount_kg' => $kg, 'user_id' => 1, 'loaned_at' => $date . ' 10:00:00', 'date' => $date]);
    }

    private function settle($loan, $date, $kg)
    {
        return TicketLoanSettlement::forceCreate(['loan_id' => $loan->id, 'ticket_barcode' => str_pad((string) random_int(1, 999999), 16, '0', STR_PAD_LEFT), 'amount_kg' => $kg,
            'settled_at' => $date . ' 11:00:00', 'date' => $date, 'user_id' => 1]);
    }

    // ACME: a bank opening balance of 100 and a bank debt of 50 (20 September) and a bank payment of 30 (25 September) before the period; in it a bank
    // debt of 200 (5 October), a cash debt of 90 (8 October), a cash payment of 80 tied to the bank debt (6 October) and a bank payment of 20 tied to no
    // debt (7 October); a debt of 999 (1 November) after it. Globex: a bank debt of 40 in it.
    private function fixtures()
    {
        $this->debt($this->acme, null, 100);
        $this->debt($this->acme, '2026-09-20', 50);
        $this->payment($this->acme, '2026-09-25', 30, null, 'bank');
        $inside = $this->debt($this->acme, '2026-10-05', 200);
        $this->payment($this->acme, '2026-10-06', 80, $inside, 'cash');
        $this->payment($this->acme, '2026-10-07', 20, null, 'bank');
        $this->debt($this->acme, '2026-10-08', 90, 'cash');
        $this->debt($this->acme, '2026-11-01', 999);
        $this->debt($this->globex, '2026-10-10', 40);

        // loans: ACME borrowed 100 kg of diesel before the period (20 kg settled before it); in it 50 kg of diesel (30 settled) and 10 kg of gasoline
        $before = $this->loan($this->acme, 'D', '2026-09-20', 100);
        $this->settle($before, '2026-09-25', 20);
        $inside = $this->loan($this->acme, 'D', '2026-10-05', 50);
        $this->settle($inside, '2026-10-06', 30);
        $this->loan($this->acme, 'A', '2026-10-08', 10);
        $this->loan($this->globex, 'D', '2026-12-01', 5);        // after the period
    }

    private function figures($row)
    {
        return array_merge(
            array_values($row['opening']), array_values($row['debts']), array_values($row['payments']), array_values($row['closing'])
        );
    }

    public function testThePaymentsTabSummarisesByCustomerInBankAndCashFromTheStartDateToTheEndDate()
    {
        $this->fixtures();

        $response = $this->as('accountant')->get('/debt-summary?from=2026-10-01&to=2026-10-31')->assertOk();
        $rows = collect($response->viewData('payments'))->keyBy('name');

        // ACME, bank | cash: owed at the start 120 | 0 (100 + 50 - 30), new debts 200 | 90, payments 20 | 80 (also the one tied to no debt), at the end 300 | 10
        $this->assertSame(['bank', 'cash'], array_keys($rows['ACME']['opening']));
        $this->assertEquals([120, 0, 200, 90, 20, 80, 300, 10], $this->figures($rows['ACME']));
        $this->assertEquals([0, 0, 40, 0, 0, 0, 40, 0], $this->figures($rows['Globex']));
        $this->assertSame(['ACME', 'Globex'], collect($response->viewData('payments'))->pluck('name')->all());      // by name

        $html = $response->getContent();
        $this->assertRegExp('/id="tab-payments-link"[^>]*>Payments \(2\)<\/a>/', $html);
        $this->assertRegExp('/id="tab-loans-link"[^>]*>Ticket Loans \(1\)<\/a>/', $html);
        // the headings: Opening balance, New debts, Payments received, Closing balance, each over Bank and Cash
        $this->assertRegExp('/<th rowspan="2">Customer<\/th>\s*<th colspan="2" class="text-center border-left">Opening balance<\/th>\s*<th colspan="2" class="text-center border-left">New debts<\/th>\s*<th colspan="2" class="text-center border-left">Payments received<\/th>\s*<th colspan="2" class="text-center border-left">Closing balance<\/th>/', $html);
        $this->assertSame(4, preg_match_all('/<th class="text-right border-left">Bank<\/th>\s*<th class="text-right">Cash<\/th>/', $html));
        $this->assertRegExp('/<td>ACME<\/td>\s*<td class="text-right border-left">120\.00<\/td>\s*<td class="text-right">0\.00<\/td>\s*<td class="text-right border-left">200\.00<\/td>\s*<td class="text-right">90\.00<\/td>\s*<td class="text-right border-left">20\.00<\/td>\s*<td class="text-right">80\.00<\/td>\s*<td class="text-right border-left">300\.00<\/td>\s*<td class="text-right">10\.00<\/td>/', $html);
        $this->assertRegExp('/<td>Total<\/td>\s*<td class="text-right border-left">120\.00<\/td>\s*<td class="text-right">0\.00<\/td>\s*<td class="text-right border-left">240\.00<\/td>\s*<td class="text-right">90\.00<\/td>\s*<td class="text-right border-left">20\.00<\/td>\s*<td class="text-right">80\.00<\/td>\s*<td class="text-right border-left">340\.00<\/td>\s*<td class="text-right">10\.00<\/td>/', $html);
        $this->assertStringContainsString('name="from" type="date" value="2026-10-01"', $html);
        $this->assertStringContainsString('name="to" type="date" value="2026-10-31"', $html);

        // another period: the opening balance belongs to the start whatever the period
        $early = collect($this->as('accountant')->get('/debt-summary?from=2026-09-01&to=2026-09-30')->viewData('payments'))->keyBy('name');
        $this->assertEquals([100, 0, 50, 0, 30, 0, 120, 0], $this->figures($early['ACME']));
        $this->assertFalse($early->has('Globex'));                                  // nothing of Globex in September

        // the end day is included; ACME owes at the start of 10 October 300 | 10
        $oneDay = collect($this->as('accountant')->get('/debt-summary?from=2026-10-10&to=2026-10-10')->viewData('payments'))->keyBy('name');
        $this->assertEquals([300, 10, 0, 0, 0, 0, 300, 10], $this->figures($oneDay['ACME']));
        $this->assertEquals([0, 0, 40, 0, 0, 0, 40, 0], $this->figures($oneDay['Globex']));
    }

    public function testTheTicketLoansTabSummarisesTheKgByCustomerAndFuelType()
    {
        $this->fixtures();

        $response = $this->as('accountant')->get('/debt-summary?from=2026-10-01&to=2026-10-31')->assertOk();
        $loans = collect($response->viewData('loans'));

        $this->assertSame(['ACME'], $loans->pluck('name')->all());                  // Globex's loan is after the period
        $acme = $loans->first()['fuels'];
        // diesel: 80 kg at the start (100 - 20), 50 more, 30 settled, 100 at the end; gasoline: 10 kg borrowed
        $this->assertEquals(['opening' => 80, 'loaned' => 50, 'settled' => 30, 'closing' => 100], $acme['D']);
        $this->assertEquals(['opening' => 0, 'loaned' => 10, 'settled' => 0, 'closing' => 10], $acme['A']);

        // the fuel types stand side by side: gasoline on the left, diesel on the right
        $html = $response->getContent();
        $this->assertRegExp('/<th colspan="4" class="text-center border-left">Gasoline<\/th>\s*<th colspan="4" class="text-center border-left">Diesel<\/th>/', $html);
        $this->assertRegExp('/<td>ACME<\/td>\s*<td class="text-right border-left">0\.00<\/td>\s*<td class="text-right">10\.00<\/td>\s*<td class="text-right">0\.00<\/td>\s*<td class="text-right">10\.00<\/td>\s*<td class="text-right border-left">80\.00<\/td>\s*<td class="text-right">50\.00<\/td>\s*<td class="text-right">30\.00<\/td>\s*<td class="text-right">100\.00<\/td>/', $html);
    }

    public function testTheSummaryExportsBothTabsToExcel()
    {
        $this->fixtures();

        $html = $this->as('accountant')->get('/debt-summary?from=2026-10-01&to=2026-10-31')->assertOk()->getContent();
        $this->assertStringContainsString('debt-summary/export?from=2026-10-01&amp;to=2026-10-31', $html);

        $response = $this->as('accountant')->get('/debt-summary/export?from=2026-10-01&to=2026-10-31')->assertOk();
        $response->assertHeader('content-type', \App\Support\Reports\XlsxWriter::MIME);
        $this->assertStringContainsString('debts-summary-2026-10-01_2026-10-31.xlsx', $response->headers->get('content-disposition'));

        $zip = new \ZipArchive();
        $zip->open($response->baseResponse->getFile()->getPathname());
        $workbook = $zip->getFromName('xl/workbook.xml');
        $payments = $zip->getFromName('xl/worksheets/sheet1.xml');
        $loans = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertFalse($zip->getFromName('xl/worksheets/sheet3.xml'));
        $zip->close();

        $this->assertRegExp('/name="Payments".*name="Ticket Loans"/s', $workbook);
        $this->assertRegExp('#<row r="1">.*Customer.*Opening balance.*New debts.*Payments received.*Closing balance.*</row>#s', $payments);
        $this->assertRegExp('#<row r="2">.*Bank.*Cash.*Bank.*Cash.*Bank.*Cash.*Bank.*Cash.*</row>#s', $payments);
        foreach (['B1:C1', 'D1:E1', 'F1:G1', 'H1:I1', 'A1:A2'] as $range) {
            $this->assertStringContainsString('<mergeCell ref="' . $range . '"/>', $payments);
        }
        // ACME: 120 | 0, 200 | 90, 20 | 80, 300 | 10; the totals at the foot
        $this->assertRegExp('#<row r="3">.*ACME.*<v>120</v>.*<v>0</v>.*<v>200</v>.*<v>90</v>.*<v>20</v>.*<v>80</v>.*<v>300</v>.*<v>10</v>.*</row>#s', $payments);
        $this->assertRegExp('#<row r="5">.*Total.*<v>120</v>.*<v>0</v>.*<v>240</v>.*<v>90</v>.*<v>20</v>.*<v>80</v>.*<v>340</v>.*<v>10</v>.*</row>#s', $payments);

        $this->assertStringContainsString('<mergeCell ref="B1:E1"/>', $loans);
        $this->assertStringContainsString('<mergeCell ref="F1:I1"/>', $loans);
        $this->assertStringContainsString('<mergeCell ref="A1:A2"/>', $loans);
        $this->assertRegExp('#<row r="3">.*ACME.*<v>10</v>.*<v>80</v>.*<v>50</v>.*<v>30</v>.*<v>100</v>.*</row>#s', $loans);
    }

    public function testThePeriodIsValidatedAndDefaultsToTheMonthSoFar()
    {
        $response = $this->as('accountant')->get('/debt-summary')->assertOk();
        $this->assertSame(today()->startOfMonth()->toDateString(), $response->viewData('from'));
        $this->assertSame(today()->toDateString(), $response->viewData('to'));

        $this->as('accountant')->get('/debt-summary?from=2026-10-31&to=2026-10-01')->assertSessionHasErrors('to');
        $this->as('accountant')->get('/debt-summary?from=yesterday')->assertSessionHasErrors('from');
        $this->as('accountant')->get('/debt-summary/export?from=2026-10-31&to=2026-10-01')->assertSessionHasErrors('to');
    }

    public function testOnlyThoseWhoMayViewPaymentsSeeTheSummary()
    {
        $this->as('manager')->get('/debt-summary')->assertOk();
        $this->as('accountant')->get('/debt-summary')->assertOk();
        $this->as('assistant')->get('/debt-summary')->assertForbidden();
        $this->as('cashier')->get('/debt-summary')->assertForbidden();
        $this->as('cashier')->get('/debt-summary/export')->assertForbidden();

        // it is a submenu of Debts Management, after Payments and Ticket Loans
        $html = $this->as('accountant')->get('/')->assertOk()->getContent();
        $at = [];
        foreach (['group_debts' => '>Debts Management<', 'payments' => '>Payments<', 'ticket_loans' => '>Ticket Loans<'] as $key => $needle) {
            $at[$key] = strpos($html, $needle);
            $this->assertNotFalse($at[$key], $key);
        }
        $summary = strpos($html, 'href="' . url('/debt-summary') . '"', $at['ticket_loans']);
        $this->assertNotFalse($summary);
        $this->assertTrue($at['group_debts'] < $at['payments'] && $at['payments'] < $at['ticket_loans'] && $at['ticket_loans'] < $summary);
        $this->as('accountant')->get('/debt-summary')->assertSee('nav-link active', false);
    }
}
