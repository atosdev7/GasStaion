<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private $diesel = 'D'; // config('ticket_codes.D') = [0=>06, 1=>07, 2=>02, 3=>05]
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

    // Publishes $quantity tickets of Diesel/$spec via the real inbound+publish flow.
    private function publishTickets($quantity, $spec = 0, $fuel = null)
    {
        $fuel = $fuel ?: $this->diesel;
        $this->as('manager')->post('/ticket-inbound', [
            'fuel_type' => $fuel, 'spec' => $spec, 'quantity' => $quantity, 'inbound_date' => '2026-09-20',
        ]);
        $this->post('/ticket-publish', ['stock_item' => $fuel . ':' . $spec, 'start_serial' => 1, 'end_serial' => $quantity]);
    }

    private function sell($quantity, $extra = [], $spec = 0, $fuel = null)
    {
        $fuel = $fuel ?: $this->diesel;

        return $this->post('/ticket-sales', $extra + [
            'sale_type' => 'ticket',
            'fuel_type' => $fuel,
            'lines' => [['spec' => $spec, 'quantity' => $quantity]],
            'customer_id' => $this->customer->id,
            'price' => 2,
            'usage' => 'sale',
            'payment_method' => 'cash',
            'paid_amount' => $quantity * config('ticket_specs.' . $spec) * 2, // full amount by default
        ]);
    }

    private function spotSell($amountKg, $extra = [], $fuel = null)
    {
        $fuel = $fuel ?: $this->diesel;

        return $this->post('/ticket-sales', $extra + [
            'sale_type' => 'spot',
            'fuel_type' => $fuel,
            'amount_kg' => $amountKg,
            'customer_id' => $this->customer->id,
            'price' => 2,
            'usage' => 'sale',
            'paid_amount' => $amountKg * 2, // full amount by default
        ]);
    }

    // ---- ticket sale ----

    public function testSaleConsumesPublishedTicketsAndMarksThemSold()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $this->sell(4)->assertRedirect('/ticket-sales');

        $sale = Sale::first();
        $this->assertSame($this->customer->id, $sale->customer_id);
        $this->assertSame('ticket', $sale->sale_type);
        $this->assertSame($this->diesel, $sale->fuel_type);
        $this->assertEquals(20, $sale->amount_kg); // 4 tickets x 5kg
        $this->assertEquals(40, $sale->total_amount); // 20kg x price 2
        $this->assertEquals(40, $sale->paid_amount);
        $this->assertEquals(0, $sale->unpaid_amount);
        $this->assertSame('cash', $sale->payment_method);
        $this->assertSame('sale', $sale->usage);
        $this->assertFalse((bool) $sale->synced);

        // the 4 lowest-serial tickets are now sold, tied to this sale
        $sold = DB::table('tickets')->where('status', config('ticket_statuses.sale'))->orderBy('serial')->get();
        $this->assertCount(4, $sold);
        $this->assertSame([1, 2, 3, 4], $sold->pluck('serial')->all());
        foreach ($sold as $t) {
            $this->assertSame($sale->id, $t->sale_id);
            $this->assertNotNull($t->sale_date);
        }

        // remaining 6 are still published
        $this->assertSame(6, DB::table('tickets')->where('status', config('ticket_statuses.publish'))->count());
    }

    public function testMultipleLinesInOneSaleSumAcrossSpecs()
    {
        $this->publishTickets(20, 0); // 5kg
        $this->publishTickets(20, 1); // 10kg
        $this->as('manager');

        $this->post('/ticket-sales', [
            'sale_type' => 'ticket',
            'fuel_type' => $this->diesel,
            'lines' => [
                ['spec' => 0, 'quantity' => 10],
                ['spec' => 1, 'quantity' => 10],
            ],
            'customer_id' => $this->customer->id,
            'price' => 2,
            'usage' => 'sale',
            'payment_method' => 'cash',
            'paid_amount' => 300, // (10*5 + 10*10) * 2
        ])->assertRedirect('/ticket-sales');

        $sale = Sale::first();
        $this->assertEquals(150, $sale->amount_kg); // 10*5 + 10*10
        $this->assertEquals(300, $sale->total_amount);

        $this->assertSame(10, DB::table('tickets')->where('spec', 0)->where('status', config('ticket_statuses.sale'))->count());
        $this->assertSame(10, DB::table('tickets')->where('spec', 1)->where('status', config('ticket_statuses.sale'))->count());
    }

    public function testStartSerialSelectsASpecificRangeInsteadOfFifo()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $this->sell(3, ['lines' => [['spec' => 0, 'quantity' => 3, 'start_serial' => 5]]])
            ->assertRedirect('/ticket-sales');

        $sold = DB::table('tickets')->where('status', config('ticket_statuses.sale'))->orderBy('serial')->pluck('serial')->all();
        $this->assertSame([5, 6, 7], $sold);
        // the lowest-serial ones (1-4) are untouched, still published
        $this->assertSame(7, DB::table('tickets')->where('status', config('ticket_statuses.publish'))->count());
    }

    public function testStartSerialRangeMustBeFullyPublished()
    {
        $this->publishTickets(5); // serials 1-5 only
        $this->as('manager');

        $this->sell(3, ['lines' => [['spec' => 0, 'quantity' => 3, 'start_serial' => 4]]]) // 4,5,6 - 6 doesn't exist
            ->assertSessionHasErrors('lines');
        $this->assertSame(0, Sale::count());
    }

    public function testSelfAndOtherUsagesAreAlwaysZeroAmount()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $this->sell(3, ['usage' => 'self', 'payment_method' => null, 'paid_amount' => null])
            ->assertRedirect('/ticket-sales');

        $sale = Sale::first();
        $this->assertSame('self', $sale->usage);
        $this->assertEquals(0, $sale->total_amount);
        $this->assertEquals(0, $sale->paid_amount);
        $this->assertEquals(0, $sale->unpaid_amount);
        // tickets are still consumed even though no money changed hands
        $this->assertSame(3, DB::table('tickets')->where('status', config('ticket_statuses.sale'))->count());
    }

    public function testTicketLoanCreatesALinkedLoanRow()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $this->sell(5, ['usage' => 'ticket_loan', 'payment_method' => null, 'paid_amount' => null])
            ->assertRedirect('/ticket-sales');

        $sale = Sale::first();
        $this->assertEquals(0, $sale->total_amount);

        $loan = TicketLoan::first();
        $this->assertSame($sale->id, $loan->sale_id);
        $this->assertSame($this->customer->id, $loan->customer_id);
        $this->assertSame($this->diesel, $loan->fuel_type);
        $this->assertEquals(25, $loan->amount_kg); // 5 tickets x 5kg
        $this->assertEquals(0, $loan->settled_amount_kg);
        $this->assertSame(today()->toDateString(), $sale->fresh()->sale_date);
        $this->assertSame(today()->toDateString(), $loan->fresh()->date);
    }

    public function testOtherUsageDoesNotCreateATicketLoan()
    {
        $this->publishTickets(5);
        $this->as('manager');

        $this->sell(2, ['usage' => 'other', 'payment_method' => null, 'paid_amount' => null]);

        $this->assertSame(0, TicketLoan::count());
    }

    // unpaid_amount is never submitted — it's always total_amount - paid_amount, computed server-side.
    public function testUnpaidAmountIsAlwaysComputedFromPaidAmount()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $this->sell(4, ['paid_amount' => 15])->assertRedirect('/ticket-sales'); // total is 40

        $sale = Sale::first();
        $this->assertEquals(15, $sale->paid_amount);
        $this->assertEquals(25, $sale->unpaid_amount);
    }

    // A paid_amount above the total can't happen from the UI (it's clamped client-side), but the
    // server clamps too so unpaid_amount can never go negative even from a bad/forged submission.
    public function testOverpaymentIsClampedNotNegativeUnpaid()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $this->sell(4, ['paid_amount' => 999])->assertRedirect('/ticket-sales'); // total is 40

        $sale = Sale::first();
        $this->assertEquals(40, $sale->paid_amount);
        $this->assertEquals(0, $sale->unpaid_amount);
    }

    public function testContractSaleWithAnUnpaidBalanceCreatesADebt()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $this->sell(4, ['payment_method' => 'bank', 'paid_amount' => 0])->assertRedirect('/ticket-sales'); // total is 40

        $sale = Sale::first();
        $this->assertEquals(0, $sale->paid_amount);
        $this->assertEquals(40, $sale->unpaid_amount);
        $this->assertSame('bank', $sale->payment_method);

        $debt = Debt::first();
        $this->assertSame($sale->id, $debt->sale_id);
        $this->assertSame($this->customer->id, $debt->customer_id);
        $this->assertEquals(40, $debt->amount);
        $this->assertSame('bank', $debt->payment_method);
    }

    public function testFullyPaidSaleDoesNotCreateADebt()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $this->sell(4); // paid in full by default

        $this->assertSame(0, Debt::count());
    }

    public function testASaleHasTheDateOfTheDayItIsMadeOnByDefault()
    {
        $this->publishTickets(5);
        $this->as('manager');

        $this->sell(1);
        $this->spotSell(2);

        foreach (Sale::all() as $sale) {
            $this->assertSame(today()->toDateString(), $sale->sale_date);
        }
        $this->assertSame(today()->toDateString(), substr((string) Ticket::whereNotNull('sale_id')->first()->sale_date, 0, 10));
    }

    public function testASaleCanBeDatedOnAnEarlierDayEverywhere()
    {
        $this->publishTickets(5);
        $this->as('manager');
        $day = today()->subDays(3)->toDateString();

        $this->sell(2, ['sale_date' => $day, 'usage' => 'ticket_loan', 'payment_method' => null, 'paid_amount' => null])->assertRedirect('/ticket-sales');
        $this->spotSell(2, ['sale_date' => $day])->assertRedirect('/spot-sales');

        $this->assertSame(2, Sale::count());
        foreach (Sale::all() as $sale) {
            $this->assertSame($day, $sale->sale_date);
        }
        // the tickets sold and the loan carry the same day
        foreach (Ticket::whereNotNull('sale_id')->get() as $ticket) {
            $this->assertSame($day, substr((string) $ticket->sale_date, 0, 10));
        }
        $this->assertSame($day, TicketLoan::first()->date);
        $this->assertSame($day, substr((string) TicketLoan::first()->loaned_at, 0, 10));

        // the reports count it under that day
        $this->get('/reports/sales?from=' . $day . '&to=' . $day)->assertOk();
        $this->assertSame(2, $this->get('/reports/sales?from=' . $day . '&to=' . $day)->viewData('summary')->sum('sales'));
    }

    public function testASaleCannotBeDatedInTheFutureOrWithABadDate()
    {
        $this->publishTickets(5);
        $this->as('manager');

        $this->sell(1, ['sale_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('sale_date');
        $this->sell(1, ['sale_date' => '12/10/2026'])->assertSessionHasErrors('sale_date');
        $this->spotSell(1, ['sale_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('sale_date');
        $this->assertSame(0, Sale::count());
    }

    public function testTheSaleFormsHaveADateFieldFilledWithToday()
    {
        $this->publishTickets(5);
        $this->as('manager');

        foreach (['/ticket-sales/create', '/spot-sales/create'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('id="f_sale_date" name="sale_date" type="date" max="' . today()->toDateString() . '" value="' . today()->toDateString() . '"', false);
        }
    }

    public function testTheFormShowsTheStartAndEndOfTheItemsAsFullBarcodes()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $response = $this->get('/ticket-sales/create')->assertOk();
        // the barcode is the sign and code (7 digits) and the serial padded to 9: the page gets the 7 digits of every fuel type and spec
        $prefix = substr(\App\Support\Tickets\Barcode::make($this->diesel, 0, 0), 0, 7);
        $this->assertSame($prefix, $response->viewData('barcodePrefixes')[$this->diesel][0]);
        $this->assertStringContainsString("prefix + ('000000000' + serial).slice(-9)", $response->getContent());
        $this->assertStringContainsString('barcodeOf(item.spec, a.start)', $response->getContent());
        $this->assertStringContainsString('barcodeOf(item.spec, a.end)', $response->getContent());
    }

    public function testTheItemsPartHasAnAddFormAndATableWithStartAndEndSerials()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $html = $this->get('/ticket-sales/create')->assertOk()->getContent();

        // the add form on the left (no field names, so it is not posted) and the table of items on the right
        $this->assertRegExp('/<div class="card card-outline card-primary">.*?id="f_item_spec".*?id="f_item_start".*?id="f_item_quantity".*?id="f_item_end" type="text" readonly.*?id="f_check_item".*?id="f_add_line"/s', $html);
        // the add form, the table and the weight are one panel (the weight is under the table)
        $this->assertRegExp('/card card-outline card-primary.*?id="f_lines".*?id="f_amount_kg_display".*?<hr>/s', $html);
        $this->assertNotRegExp('/id="f_item_(spec|quantity|start|end)"[^>]* name=/', $html);
        $this->assertRegExp('/<th class="text-right">Start serial<\/th>\s*<th class="text-right">End serial<\/th>/', $html);

        // the published serials are sent as runs, for the page to work out start and end
        $this->assertSame([[1, 10]], $this->get('/ticket-sales/create')->viewData('serials')[$this->diesel][0]);
    }

    public function testThePublishedSerialsAreSentAsRunsOfConsecutiveNumbers()
    {
        $this->publishTickets(10);
        $this->as('manager');
        $this->sell(1, ['lines' => [['spec' => 0, 'quantity' => 1, 'start_serial' => 4]]]);   // 4 is gone: 1-3 and 5-10 are left
        $this->sell(1, ['lines' => [['spec' => 0, 'quantity' => 2, 'start_serial' => 8]]]);   // 8-9 gone: 1-3, 5-7, 10

        $this->assertSame([[1, 3], [5, 7], [10, 10]], $this->get('/ticket-sales/create')->viewData('serials')[$this->diesel][0]);
    }

    public function testTwoItemsOfOneSaleNeverTakeTheSameTicket()
    {
        $this->publishTickets(10);
        $this->as('manager');

        $this->sell(1, ['lines' => [['spec' => 0, 'quantity' => 3], ['spec' => 0, 'quantity' => 2]]])->assertSessionHasNoErrors();

        $sale = Sale::first();
        $this->assertEquals(25, $sale->amount_kg); // 5 tickets of 5 kg, none counted twice
        $this->assertSame(range(1, 5), Ticket::where('sale_id', $sale->id)->orderBy('serial')->pluck('serial')->map(function ($n) { return (int) $n; })->all());

        // an item that asks for serials an earlier item took is refused
        $this->sell(1, ['lines' => [['spec' => 0, 'quantity' => 2], ['spec' => 0, 'quantity' => 1, 'start_serial' => 6]]])->assertSessionHasErrors('lines');
        $this->assertSame(1, Sale::count());
    }

    public function testANewCustomerCanBeTypedIntoTheSaleForms()
    {
        $this->publishTickets(10);
        $this->as('manager');
        $before = Customer::count();

        // a ticket sale to a name that is not a customer yet: the customer is added with the sale
        $this->sell(1, ['customer_id' => null, 'new_customer' => '  Fresh Buyer ', 'usage' => 'self', 'paid_amount' => null])->assertSessionHasNoErrors();
        $new = Customer::where('name', 'Fresh Buyer')->first();
        $this->assertNotNull($new);
        $this->assertFalse((bool) $new->synced); // the terminal gets it with the next download
        $this->assertSame($new->id, Sale::first()->customer_id);

        // the same name again (in another case) is the same customer, a spot sale too
        $this->spotSell(2, ['customer_id' => null, 'new_customer' => 'FRESH buyer', 'usage' => 'self', 'paid_amount' => null])->assertSessionHasNoErrors();
        $this->assertSame($before + 1, Customer::count());
        $this->assertSame(2, Sale::where('customer_id', $new->id)->count());

        // a customer that was chosen wins over the typed name
        $this->sell(1, ['customer_id' => $this->customer->id, 'new_customer' => 'Ignored', 'usage' => 'self', 'paid_amount' => null]);
        $this->assertNull(Customer::where('name', 'Ignored')->first());
    }

    public function testACustomerIsNeededAndNewOnesOnlyFromPeopleWhoMayAddThem()
    {
        $this->publishTickets(10);

        $this->as('manager');
        $this->sell(1, ['customer_id' => null, 'new_customer' => null])->assertSessionHasErrors('customer_id');
        $this->sell(1, ['customer_id' => null, 'new_customer' => str_repeat('x', 101)])->assertSessionHasErrors('new_customer');

        $before = Customer::count();
        $this->as('assistant'); // sells, but cannot manage customers
        $this->sell(1, ['customer_id' => null, 'new_customer' => 'Not Allowed'])->assertSessionHasErrors('new_customer');
        $this->spotSell(1, ['customer_id' => null, 'new_customer' => 'Not Allowed'])->assertSessionHasErrors('new_customer');
        $this->assertSame($before, Customer::count());
        $this->assertSame(0, Sale::count());
    }

    public function testTheCustomerFieldOfTheFormsIsASelect2ThatKnowsIfItMayAdd()
    {
        $this->publishTickets(10);

        foreach (['/ticket-sales/create', '/spot-sales/create'] as $url) {
            $html = $this->as('manager')->get($url)->assertOk()->getContent();
            // AdminLTE's Select2 on a select of the customers; what is posted are the two hidden fields
            $this->assertRegExp('/<select id="f_customer_name"[^>]*data-can-add="1"[^>]*>\s*<option value=""><\/option>\s*<option value="' . $this->customer->id . '"[^>]*>ACME<\/option>/', $html, $url);
            $this->assertStringNotContainsString('<select id="f_customer_name" name=', $html);
            $this->assertStringContainsString('name="customer_id"', $html);
            $this->assertStringContainsString('name="new_customer"', $html);
            $this->assertStringContainsString("$('#f_customer_name')", $html);
            $this->assertStringContainsString('plugins/select2/js/select2.min.js', $html);

            $this->assertRegExp('/data-can-add="0"/', $this->as('assistant')->get($url)->assertOk()->getContent(), $url);
        }
    }

    public function testEveryPageLoadsTheDatePickerOfAdminLte()
    {
        $html = $this->as('manager')->get('/ticket-sales/create')->assertOk()->getContent();

        // moment, then the Tempus Dominus date picker, then the script that upgrades every date box; their files are in the app (no CDN)
        $this->assertRegExp('/plugins\/moment\/moment\.min\.js.*plugins\/tempusdominus-bootstrap-4\/js\/tempusdominus-bootstrap-4\.min\.js.*js\/datepicker\.js/s', $html);
        $this->assertStringContainsString('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css', $html);
        foreach (['moment/moment.min.js', 'tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js',
                  'tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css'] as $file) {
            $this->assertFileExists(public_path('vendor/adminlte/plugins/' . $file));
        }
        $this->assertStringNotContainsString('cdn', strtolower($html));
    }

    public function testTheSalesTableHasItsColumnsInOrderAndAViewActionOnEveryRow()
    {
        $this->publishTickets(10);
        $this->as('manager');
        $this->sell(2);
        $this->sell(1);

        $html = $this->get('/ticket-sales')->assertOk()->getContent();

        // the head: sale date, customer, fuel type, weight, payment method, total, paid, unpaid, usage, actions
        $head = substr($html, strpos($html, '<thead>'), strpos($html, '</thead>') - strpos($html, '<thead>'));
        preg_match_all('/<th[^>]*>\s*([^<]*?)\s*<\/th>/', substr($head, 0, strpos($head, '<tr class="filters">')), $m);
        $this->assertSame(['Sale date', 'Customer', 'Fuel type', 'Weight (kg)', 'Payment method', 'Total amount', 'Paid amount', 'Unpaid amount', 'Usage', 'Actions'],
            array_map('html_entity_decode', $m[1]));

        // a row has its cells in the same order, then the view action
        $sale = Sale::orderBy('created_at')->first();
        $this->assertRegExp('/<tr>\s*<td>' . today()->toDateString() . '<\/td>\s*<td>ACME<\/td>\s*<td>Diesel<\/td>\s*<td class="text-right">[^<]*<\/td>\s*<td>Cash<\/td>\s*<td class="text-right">[^<]*<\/td>\s*<td class="text-right">[^<]*<\/td>\s*<td class="text-right">[^<]*<\/td>\s*<td>\s*Sale/s', $html);
        // the actions of a row are one drop-down: View, and Return / Delete when they are possible
        $this->assertSame(2, substr_count($html, 'class="btn-group sale-actions"'));
        $this->assertSame(2, substr_count($html, 'class="dropdown-item sale-detail"'));
        $this->assertSame(2, substr_count($html, 'data-toggle="dropdown" data-display="static"'));
        $this->assertSame(0, substr_count($html, 'btn-outline-danger'));
        $this->assertStringContainsString('href="' . url('/ticket-sales/' . $sale->id) . '"', $html);
    }

    public function testTheViewActionShowsTheSaleAndItsTicketsAsAPageAndAsAPopup()
    {
        $this->publishTickets(10);
        $this->as('manager');
        $this->sell(3, ['comment' => 'for the road']);
        $sale = Sale::first();

        $page = $this->get('/ticket-sales/' . $sale->id)->assertOk()->assertSee('<html', false)->assertSee('for the road')->assertSee('ACME')->assertSee('Diesel');
        $page->assertSee('Back to the list');

        // the popup gets the detail alone: the items of the sale (spec, count, start and end serial) and no list of the tickets
        $popup = $this->get('/ticket-sales/' . $sale->id . '?modal=1')->assertOk()->assertDontSee('<html', false)->assertSee('for the road')->assertSee('Items');
        // start and end are shown as full barcodes (sign, code and the serial of 9 digits)
        $first = \App\Support\Tickets\Barcode::make($this->diesel, 0, 1);
        $last = \App\Support\Tickets\Barcode::make($this->diesel, 0, 3);
        $this->assertSame(16, strlen($first));
        $this->assertRegExp('/<td class="text-right">3<\/td>\s*<td class="text-right">' . $first . '<\/td>\s*<td class="text-right">' . $last . '<\/td>\s*<td class="text-right">15\.00<\/td>/', $popup->getContent());
        $this->assertStringNotContainsString('<th>Barcode</th>', $popup->getContent());
        $this->assertSame(0, substr_count($popup->getContent(), '<td>00'));  // no barcodes

        // a spot sale is shown too, and a deleted one for people who may see the list
        $this->spotSell(2);
        $spot = Sale::where('sale_type', 'spot')->first();
        $this->get('/ticket-sales/' . $spot->id)->assertOk()->assertSee('Spot');
        $this->get('/ticket-sales/' . $sale->id . '/nothing')->assertNotFound();
        $this->get('/ticket-sales/11111111-1111-1111-1111-111111111111')->assertNotFound();

        $this->as('cashier')->get('/ticket-sales/' . $sale->id)->assertForbidden();
    }

    public function testTheItemsOfASaleAreKeptAsEntered()
    {
        $this->publishTickets(20);
        $this->as('manager');

        // three lines: 1-3, then 4-5 (it touches the first and is still its own item), then 10-11
        $this->sell(1, ['lines' => [
            ['spec' => 0, 'quantity' => 3, 'start_serial' => 1],
            ['spec' => 0, 'quantity' => 2, 'start_serial' => 4],
            ['spec' => 0, 'quantity' => 2, 'start_serial' => 10],
        ]])->assertSessionHasNoErrors();
        $sale = Sale::first();

        $items = SaleItem::where('sale_id', $sale->id)->orderBy('id')->get();
        $this->assertSame(3, $items->count());
        $this->assertSame([[0, 1, 3, 3, '15.00'], [0, 4, 5, 2, '10.00'], [0, 10, 11, 2, '10.00']],
            $items->map(function ($i) { return [(int) $i->spec, (int) $i->start_serial, (int) $i->end_serial, (int) $i->quantity, (string) $i->amount_kg]; })->all());
        $this->assertEquals($sale->amount_kg, $items->sum('amount_kg'));

        // the detail shows them as they were entered, not the touching ones together
        $html = $this->get('/ticket-sales/' . $sale->id . '?modal=1')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, '<td>5 kg</td>'));
        $code = function ($serial) { return \App\Support\Tickets\Barcode::make($this->diesel, 0, $serial); };
        $this->assertRegExp('/<td class="text-right">3<\/td>\s*<td class="text-right">' . $code(1) . '<\/td>\s*<td class="text-right">' . $code(3) . '<\/td>\s*<td class="text-right">15\.00<\/td>.*?<td class="text-right">2<\/td>\s*<td class="text-right">' . $code(4) . '<\/td>\s*<td class="text-right">' . $code(5) . '<\/td>/s', $html);

        // a line without a start serial takes the lowest ones: its first and last serial are kept
        $this->sell(1, ['lines' => [['spec' => 0, 'quantity' => 2]]]);
        $fifo = SaleItem::where('sale_id', '<>', $sale->id)->first();
        $this->assertSame([6, 7, 2], [(int) $fifo->start_serial, (int) $fifo->end_serial, (int) $fifo->quantity]);

        // a sale that is refused keeps no items; a spot sale has none
        $count = SaleItem::count();
        $this->sell(1, ['lines' => [['spec' => 0, 'quantity' => 500]]])->assertSessionHasErrors('lines');
        $this->spotSell(2);
        $this->assertSame($count, SaleItem::count());
        $spot = $this->get('/ticket-sales/' . Sale::where('sale_type', 'spot')->first()->id . '?modal=1')->assertOk()->getContent();
        $this->assertStringNotContainsString('Items', $spot);
    }

    public function testTheSalesTableCanBeFilteredByDateAndCustomer()
    {
        $this->publishTickets(20);
        $this->as('manager');
        $other = Customer::forceCreate(['name' => 'Zed Trading']);
        $day = today()->subDays(2)->toDateString();

        $this->sell(1);                                                                // ACME, today
        $this->sell(1, ['sale_date' => $day]);                                         // ACME, two days ago
        $this->sell(1, ['customer_id' => $other->id, 'sale_date' => $day]);            // Zed, two days ago

        $names = function ($url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match_all('/<tr>\s*<td>(\d{4}-\d\d-\d\d)<\/td>\s*<td>([^<]*)<\/td>/', $html, $m);

            return array_map(function ($d, $c) { return $d . ' ' . $c; }, $m[1], $m[2]);
        };

        $this->assertCount(3, $names('/ticket-sales'));
        $this->assertSame([$day . ' ACME', $day . ' Zed Trading'], $this->sorted($names('/ticket-sales?sale_date=' . $day)));
        $this->assertSame([today()->toDateString() . ' ACME', $day . ' ACME'], $names('/ticket-sales?customer=' . $this->customer->id));
        $this->assertSame([$day . ' Zed Trading'], $names('/ticket-sales?customer=' . $other->id . '&sale_date=' . $day));
        $this->assertSame([], $names('/ticket-sales?customer=' . $other->id . '&sale_date=' . today()->toDateString()));
        $this->assertSame([], $names('/ticket-sales?customer=999999'));

        // the filters are in the head of the columns and the chosen ones stay chosen
        $this->get('/ticket-sales?customer=' . $other->id . '&sale_date=' . $day)->assertSee('name="customer" form="sale-filters"', false)
            ->assertSee('<option value="' . $other->id . '" selected>Zed Trading</option>', false)->assertSee('value="' . $day . '"', false);

        // a bad date or a bad customer is refused
        $this->get('/ticket-sales?sale_date=12/10/2026')->assertSessionHasErrors('sale_date');
        $this->get('/ticket-sales?customer=zed')->assertSessionHasErrors('customer');

        // the spot list and the deleted list have the same filters
        $this->spotSell(1, ['sale_date' => $day]);
        $this->assertSame([$day . ' ACME'], $names('/spot-sales?sale_date=' . $day));
        $this->assertSame([], $names('/spot-sales?sale_date=' . today()->toDateString()));
    }

    public function testTheCustomerFilterIsATypedBoxWithSuggestionsAndTheDeletedListHasItsOwnColumn()
    {
        $this->publishTickets(5);
        $this->as('manager');
        Customer::forceCreate(['name' => 'Zed Trading']);
        $this->sell(1);

        $html = $this->get('/ticket-sales')->assertOk()->getContent();
        // Select2 (AdminLTE's plugin) on a select of all the customers, an empty first option for "all customers"
        $this->assertRegExp('/<select name="customer" form="sale-filters" id="sale-customer-filter"[^>]*>\s*<option value=""><\/option>.*?>Zed Trading<\/option>/s', $html);
        $this->assertStringContainsString('plugins/select2/js/select2.min.js', $html);
        $this->assertStringContainsString('plugins/select2/css/select2.min.css', $html);
        $this->assertStringContainsString("$('#sale-customer-filter').select2(", $html);
        $this->assertFileExists(public_path('vendor/adminlte/plugins/select2/js/select2.min.js'));
        $this->assertFileExists(public_path('vendor/adminlte/plugins/select2/css/select2.min.css'));

        // the deleted sales: a Deleted at column of its own before the actions
        $sale = Sale::first();
        $this->delete('/ticket-sales/' . $sale->id)->assertRedirect('/ticket-sales');
        $deleted = $this->get('/ticket-sales?deleted=1')->assertOk()->getContent();
        $this->assertRegExp('/<th>Usage<\/th>\s*<th>Deleted at<\/th>\s*<th class="text-right">Actions<\/th>/', $deleted);
        $this->assertStringContainsString('name="deleted" value="1"', $deleted); // the filters stay in the deleted list
        $this->assertSame(0, substr_count($deleted, 'text-danger"><i class="fas fa-trash'));  // nothing to delete again
    }

    private function sorted(array $list)
    {
        sort($list);

        return $list;
    }

    public function testTheSalesTableIsPagedAndKeepsItsFilters()
    {
        $this->as('manager');
        for ($i = 0; $i < 53; $i++) {
            Sale::forceCreate([
                'sale_date' => today()->toDateString(), 'customer_id' => $this->customer->id, 'sale_type' => 'ticket', 'fuel_type' => 'D', 'price' => 2,
                'amount_kg' => 5, 'payment_method' => 'cash', 'total_amount' => 10, 'paid_amount' => 10, 'unpaid_amount' => 0, 'usage' => 'sale', 'user_id' => 1,
            ]);
        }

        $first = $this->get('/ticket-sales?customer=' . $this->customer->id)->assertOk()->getContent();
        $this->assertSame(50, substr_count($first, 'sale-detail" data-id'));
        $this->assertStringContainsString('page=2', $first);
        $this->assertStringContainsString('customer=' . $this->customer->id, $first); // the link to the next page keeps the filter

        $second = $this->get('/ticket-sales?customer=' . $this->customer->id . '&page=2')->assertOk()->getContent();
        $this->assertSame(3, substr_count($second, 'sale-detail" data-id'));
    }

    public function testTheSpotFormHasTheFieldsOfTheTicketFormInTheSameOrder()
    {
        $this->publishTickets(5);
        $this->as('manager');

        // customer / date; fuel type / cost / price; weight; total / paid / unpaid; payment / receipt; usage / comment, in rows of three
        $order = ['f_customer_name', 'f_sale_date', 'f_fuel_type', 'f_cost', 'f_price', 'f_amount_kg', 'f_total_amount_display', 'f_paid_amount',
                  'f_unpaid_amount_display', 'f_receipt', 'f_usage', 'f_comment'];
        foreach (['/ticket-sales/create' => ['f_amount_kg_display'], '/spot-sales/create' => ['f_amount_kg']] as $url => $weight) {
            $html = $this->get($url)->assertOk()->getContent();
            $positions = [];
            foreach (array_map(function ($id) use ($weight) { return $id === 'f_amount_kg' ? $weight[0] : $id; }, $order) as $id) {
                $this->assertNotFalse(strpos($html, 'id="' . $id . '"'), $url . ' ' . $id);
                $positions[] = strpos($html, 'id="' . $id . '"');
            }
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions, $url . ' has its fields in the order');
            $this->assertSame(2, substr_count($html, '<hr>'), $url);
            $this->assertGreaterThanOrEqual(6, substr_count($html, 'col-md-4'), $url);
        }

        // the amounts that only a sale has hide for the other uses, in both forms
        $this->assertSame(3, substr_count($this->get('/spot-sales/create')->getContent(), 'col-md-4 f_payment_part'));
    }

    public function testReceiptIsAYesNo()
    {
        $this->publishTickets(5);
        $this->as('manager');

        $this->sell(1)->assertSessionHasNoErrors();                  // nothing sent: no receipt
        $this->sell(1, ['receipt' => '0']);                          // the unchecked box sends 0
        $this->sell(1, ['receipt' => '1']);                          // the checked box
        $this->spotSell(3, ['receipt' => '1']);

        $this->assertSame(2, Sale::where('sale_type', 'ticket')->where('receipt', false)->count());
        $this->assertSame(1, Sale::where('sale_type', 'ticket')->where('receipt', true)->count());
        $this->assertTrue(Sale::where('sale_type', 'spot')->first()->receipt);

        $this->sell(1, ['receipt' => 'R-001'])->assertSessionHasErrors('receipt');
    }

    public function testTheSaleFormsHaveAReceiptCheckbox()
    {
        $this->publishTickets(5);
        $this->as('manager');

        foreach (['/ticket-sales/create', '/spot-sales/create'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertRegExp('/<input id="f_receipt" name="receipt" type="checkbox" value="1"/', $html, $url);
            $this->assertRegExp('/<input type="hidden" name="receipt" value="0">/', $html, $url);
        }
    }

    public function testCannotSellMoreThanPublishedStock()
    {
        $this->publishTickets(3);
        $this->as('manager');

        $this->sell(4)->assertSessionHasErrors('lines');
        $this->assertSame(0, Sale::count());
        $this->assertSame(3, DB::table('tickets')->where('status', config('ticket_statuses.publish'))->count());
    }

    public function testOnlyPublishedTicketsAreEligibleNotBlankOrAlreadySold()
    {
        $this->publishTickets(5);
        $this->as('manager');
        $this->sell(5); // consumes all 5

        $this->sell(1)->assertSessionHasErrors('lines'); // nothing left to sell
    }

    // ---- spot sale ----

    public function testSpotSaleGeneratesADirectTicketAndSkipsPublish()
    {
        $this->as('manager');

        $this->spotSell(23.5)->assertRedirect('/spot-sales');

        $sale = Sale::first();
        $this->assertSame('spot', $sale->sale_type);
        $this->assertEquals(23.5, $sale->amount_kg);
        $this->assertEquals(47, $sale->total_amount); // 23.5 * 2
        $this->assertSame('cash', $sale->payment_method); // always cash, never submitted
        $this->assertSame(today()->toDateString(), $sale->sale_date);

        $ticket = DB::table('tickets')->first();
        $this->assertSame('21', $ticket->code); // reserved 'direct' code
        $this->assertNull($ticket->spec);
        $this->assertEquals(23.5, $ticket->amount_kg);
        $this->assertSame($sale->id, $ticket->sale_id);
        $this->assertSame(config('ticket_statuses.sale'), (int) $ticket->status); // no publish phase
        $this->assertNull($ticket->publish_id);
    }

    public function testSpotSaleSerialsIncrementIndependentlyOfFuelTypeAndSpec()
    {
        $this->as('manager');

        $this->spotSell(10);
        $this->spotSell(15, [], 'A');

        $serials = DB::table('tickets')->orderBy('serial')->pluck('serial')->all();
        $this->assertSame([1, 2], $serials);
    }

    public function testSpotSaleUsageOtherIsZeroAmountButStillCreatesATicket()
    {
        $this->as('manager');

        $this->spotSell(10, ['usage' => 'other', 'paid_amount' => null])->assertRedirect('/spot-sales');

        $sale = Sale::first();
        $this->assertEquals(0, $sale->total_amount);
        $this->assertSame(1, DB::table('tickets')->count());
    }

    // ---- permissions ----

    public function testPermissions()
    {
        $this->publishTickets(10);

        $this->as('assistant')->get('/ticket-sales')->assertOk(); // now has ticket_sales.manage
        $this->sell(1)->assertRedirect('/ticket-sales');

        $this->as('accountant')->get('/ticket-sales')->assertOk();
        $this->sell(1)->assertRedirect('/ticket-sales'); // accountant can sell

        $this->as('cashier')->get('/ticket-sales')->assertForbidden();
    }

    public function testScreensRender()
    {
        $this->publishTickets(10);
        $this->as('manager');
        $this->sell(2);

        $this->get('/ticket-sales')->assertOk()->assertSee('ACME')->assertSee('Diesel');
        $this->get('/ticket-sales/create')->assertOk()->assertSee('Diesel');
        $this->get('/spot-sales/create')->assertOk()->assertSee('Diesel');
        $this->get('/spot-sales')->assertOk();
    }

    public function testTicketSalesAndSpotSalesAreSeparateListsInTheMenu()
    {
        $this->publishTickets(10);
        $this->as('manager');
        $this->sell(2);
        $this->spotSell(23.5);

        $menu = $this->get('/ticket-sales')->assertOk()->assertSee('Ticket Sales')->assertSee('Spot Sales')->getContent();
        $this->assertStringContainsString('href="' . url('/spot-sales') . '"', $menu);

        // each list shows only its own sales; the ready-to-sell box and the sale button belong to their page
        $tickets = $this->get('/ticket-sales')->assertOk()
            ->assertDontSee('Tickets ready to sell')->assertSee('/ticket-sales/create')->assertDontSee('/spot-sales/create')
            ->assertSee('20.00')->assertDontSee('23.5')->getContent();
        $this->assertSame(1, substr_count($tickets, 'class="dropdown-item text-danger"'));

        $spot = $this->get('/spot-sales')->assertOk()
            ->assertDontSee('Tickets ready to sell')->assertSee('/spot-sales/create')->assertDontSee('/ticket-sales/create')
            ->assertSee('23.5')->getContent();
        $this->assertSame(1, substr_count($spot, 'class="dropdown-item text-danger"'));

        // the deleted ones are split the same way
        $spotSale = Sale::where('sale_type', 'spot')->first();
        $this->delete('/ticket-sales/' . $spotSale->id)->assertRedirect('/spot-sales');
        $this->get('/spot-sales?deleted=1')->assertOk()->assertSee('23.5');
        $this->get('/ticket-sales?deleted=1')->assertOk()->assertDontSee('23.5');
    }

    public function testCashierAndOthersWithoutTheSalesPermissionCannotOpenSpotSales()
    {
        $this->as('cashier')->get('/spot-sales')->assertForbidden();
        $this->as('assistant')->get('/spot-sales')->assertOk();
    }
}
