<?php

namespace Tests\Feature;

use App\Jobs\RunBackupDump;
use App\Models\Sales\Customer;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\TicketInbound;
use App\Models\Tickets\TicketPublish;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class ConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function sale($date, $extra = [])
    {
        return Sale::forceCreate($extra + [
            'sale_type' => 'spot', 'fuel_type' => 'D', 'price' => 2,
            'amount_kg' => 10, 'payment_method' => 'cash', 'total_amount' => 20, 'paid_amount' => 20, 'unpaid_amount' => 0,
            'usage' => 'sale', 'sale_date' => $date, 'user_id' => 1,
        ]);
    }

    private function supplement($date, $extra = [])
    {
        return Supplement::forceCreate($extra + [
            'fuel_type' => 'D', 'nozzle_id' => 1, 'out_amount_lt' => 10, 'out_amount_kg' => 9, 'preset_kg' => 10,
            'supplied_at' => ($date ?: '2026-10-01') . ' 11:00:00', 'supply_date' => $date,
        ]);
    }

    public function testOnlyAccountantAndManagerCanConfirm()
    {
        foreach (['admin', 'manager', 'accountant'] as $role) {
            $this->as($role)->get('/confirmations')->assertOk();
        }
        foreach (['assistant', 'cashier'] as $role) {
            $this->as($role)->get('/confirmations')->assertForbidden();
            $this->as($role)->post('/confirmations', ['date' => '2026-10-01', 'sales' => []])->assertForbidden();
        }
    }

    public function testTheChosenDateShowsOnlyItsOwnRecordsAndOnlyTheUnconfirmedHaveACheckbox()
    {
        $mine = $this->sale('2026-10-01', ['comment' => 'mine']);
        $this->sale('2026-10-01', ['confirmed_at' => now(), 'confirmed_by' => 1]);
        $this->sale('2026-10-02');
        $deleted = $this->sale('2026-10-01');
        $deleted->delete();
        $supplement = $this->supplement('2026-10-01');

        $html = $this->as('accountant')->get('/confirmations?date=2026-10-01')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'name="sales[]"')); // $mine only
        $this->assertStringContainsString($mine->id, $html);
        $this->assertStringNotContainsString($deleted->id, $html);
        $this->assertSame(0, substr_count($html, 'name="supplements[]"')); // the supplements are on their own page

        $html = $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'name="supplements[]"'));
        $this->assertStringContainsString($supplement->id, $html);
        $this->assertSame(0, substr_count($html, 'name="sales[]"'));
    }

    public function testTheSuppliesToConfirmShowTheirMode()
    {
        $this->supplement('2026-10-01', ['mode' => 'manual']);
        $this->supplement('2026-10-01');

        $html = $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertOk()->getContent();
        // no Tickets column: the mode is followed by the preset, out kg, the cost per kg (a user who may see costs), the remainder and the comment
        $this->assertRegExp('/<th>Nozzle<\/th>\s*<th>Mode<\/th>\s*<th class="text-right">Preset amount \(kg\)<\/th>\s*<th class="text-right">Out amount \(kg\)<\/th>\s*<th class="text-right">Cost per kg<\/th>\s*<th>Remainder<\/th>\s*<th>Comment<\/th>/', $html);
        $this->assertStringNotContainsString('>Tickets<', $html);
        $this->assertSame(1, substr_count($html, '<td>Manual</td>'));
        $this->assertSame(1, substr_count($html, '<td>Auto</td>'));
    }

    public function testSalesSupplementsAndSummaryAreSubMenusOfDailyConfirmation()
    {
        $html = $this->as('accountant')->get('/confirmations')->assertOk()->getContent();

        $group = strpos($html, '>Daily Confirmation<');
        $this->assertNotFalse($group);
        $last = $group;
        foreach (['/confirmations', '/confirmations/supplements', '/confirmations/summary'] as $path) {
            $at = strpos($html, 'href="' . url($path) . '"', $last);
            $this->assertNotFalse($at, $path);
            $last = $at;
        }

        // only the page itself is the current item of the menu
        foreach (['/confirmations' => 'Sales', '/confirmations/supplements' => 'Supplements', '/confirmations/summary' => 'Summary'] as $path => $label) {
            $html = $this->as('accountant')->get($path)->assertOk()->getContent();
            $this->assertSame(1, preg_match_all('/<a href="' . preg_quote(url('/confirmations'), '/') . '[^"]*" class="nav-link active">/', $html), $path);
            $this->assertRegExp('/<a href="' . preg_quote(url($path), '/') . '" class="nav-link active">\s*<i[^>]*><\/i>\s*<p>' . $label . '<\/p>/', $html);
        }
    }

    public function testTheSalesAreInTicketSpotCardAndOthersTabsEachWithItsOwnFormAndConfirmButton()
    {
        $ticketGas = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'A', 'payment_method' => 'bank']);
        $ticketDiesel = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'D', 'payment_method' => 'cash']);
        $spotDiesel = $this->sale('2026-10-01', ['sale_type' => 'spot', 'fuel_type' => 'D']);
        $spotGas = $this->sale('2026-10-01', ['sale_type' => 'spot', 'fuel_type' => 'A']);
        $spotGas2 = $this->sale('2026-10-01', ['sale_type' => 'spot', 'fuel_type' => 'A']);
        $self = $this->sale('2026-10-01', ['fuel_type' => 'A', 'usage' => 'self', 'paid_amount' => 0, 'unpaid_amount' => 0]);   // not a sale of fuel
        $card = \App\Models\Cards\Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D']);
        $topUp = $this->sale('2026-10-01', ['sale_type' => 'card', 'card_id' => $card->id, 'fuel_type' => 'D', 'amount_kg' => 40, 'total_amount' => 300, 'paid_amount' => 300]);   // a top-up

        $response = $this->as('accountant')->get('/confirmations?date=2026-10-01')->assertOk();
        $groups = $response->viewData('groups');

        $this->assertSame(['ticket', 'spot', 'card', 'others'], array_keys($groups));
        $this->assertEqualsCanonicalizing([$ticketGas->id, $ticketDiesel->id], $groups['ticket']['rows']->pluck('id')->all()); // one list, not split by fuel
        $this->assertSame(1, count($groups['ticket']['sides']));
        $this->assertSame(['A', 'D'], array_keys($groups['spot']['fuels']));
        $this->assertEqualsCanonicalizing([$spotGas->id, $spotGas2->id], $groups['spot']['fuels']['A']->pluck('id')->all());
        $this->assertSame([$spotDiesel->id], $groups['spot']['fuels']['D']->pluck('id')->all());
        $this->assertSame([$self->id], $groups['others']['rows']->pluck('id')->all());
        $this->assertSame([$topUp->id], $groups['card']['rows']->pluck('id')->all());                      // the top-ups of the cards, one table with a fuel column
        $this->assertSame(1, count($groups['card']['sides']));
        $this->assertSame([2, 3, 1, 1], [$groups['ticket']['count'], $groups['spot']['count'], $groups['card']['count'], $groups['others']['count']]);

        $html = $response->getContent();
        $this->assertRegExp('/id="tab-ticket-link"[^>]*>Ticket Sales \(2\)<\/a>/', $html);
        $this->assertRegExp('/id="tab-spot-link"[^>]*>Spot Sales \(3\)<\/a>/', $html);
        $this->assertRegExp('/id="tab-card-link"[^>]*>Card Sales \(1\)<\/a>/', $html);
        $this->assertRegExp('/id="tab-others-link"[^>]*>Others \(1\)<\/a>/', $html);

        // one form and one confirm button in each tab
        $this->assertSame(4, substr_count($html, '<form method="POST" action="' . url('/confirmations') . '">'));
        $this->assertSame(4, substr_count($html, 'btn btn-success'));
        foreach (['ticket', 'spot', 'card', 'others'] as $tab) {
            $this->assertStringContainsString('<input type="hidden" name="tab" value="' . $tab . '">', $html);
        }

        $pane = function ($tab) use ($html) {
            $from = strpos($html, 'id="tab-' . $tab . '" role="tabpanel"');
            $to = strpos($html, '</form>', $from);

            return substr($html, $from, $to - $from);
        };
        // the ticket tab is one table for all fuels: a Fuel type column and the payment of each sale, no split by fuel
        $ticket = $pane('ticket');
        $this->assertSame(1, substr_count($ticket, '<table'));
        $this->assertStringNotContainsString('Gasoline (', $ticket);   // no heading per fuel
        $this->assertStringContainsString('<th rowspan="2">Fuel type</th>', $ticket);
        $this->assertSame(1, substr_count($ticket, '<th rowspan="2">Payment method</th>'));
        $this->assertRegExp('/<td>Bank<\/td>/', $ticket);
        $this->assertRegExp('/<td>Cash<\/td>/', $ticket);
        $this->assertSame(2, substr_count($ticket, 'name="sales[]"'));
        $this->assertStringContainsString($ticketGas->id, $ticket);
        $this->assertStringContainsString($ticketDiesel->id, $ticket);
        $this->assertStringNotContainsString($spotGas->id, $ticket);
        // the spot tab has a table for each fuel type, the gasoline one and the diesel one under it, with one Excel button above them
        $spot = $pane('spot');
        $this->assertSame(2, substr_count($spot, '<table'));
        $this->assertRegExp('/<h6[^>]*>Gasoline \(2\)<\/h6>.*<table.*<h6[^>]*>Diesel \(1\)<\/h6>.*<table/s', $spot);
        $this->assertSame(0, substr_count($spot, 'border-left">Diesel'));                       // not side by side
        $this->assertSame(1, substr_count($spot, 'fa-file-excel'));
        $this->assertRegExp('/<h6[^>]*>Gasoline \(2\)<\/h6>\s*<a href="[^"]*sales-export[^"]*"[^>]*><i class="fas fa-file-excel">/', $spot);   // the button is at the right of the first heading
        $this->assertSame(2, substr_count($spot, '<tbody>'));
        $this->assertSame(3, substr_count($spot, 'name="sales[]"'));
        $this->assertStringContainsString($spotDiesel->id, $spot);
        // card sales: one table with a Fuel type column, the name is the holder of the card, the payment is cash
        $cardTab = $pane('card');
        $this->assertSame(1, substr_count($cardTab, '<table'));
        $this->assertStringContainsString('<th>Fuel type</th>', $cardTab);
        $this->assertStringContainsString('<td>Li Wei</td>', $cardTab);
        $this->assertStringContainsString($topUp->id, $cardTab);
        // others: one table with a Fuel type column, and the sale type and usage of each sale
        $others = $pane('others');
        $this->assertSame(1, substr_count($others, '<table'));
        $this->assertStringNotContainsString('<h5', $others);
        $this->assertStringContainsString('<th>Fuel type</th>', $others);
        $this->assertStringContainsString('<th>Usage</th>', $others);
        $this->assertStringNotContainsString('<th>Payment method</th>', $others);
        $this->assertStringContainsString($self->id, $others);
    }

    private function item($sale, $spec, $quantity)
    {
        return \App\Models\Sales\SaleItem::forceCreate([
            'sale_id' => $sale->id, 'spec' => $spec, 'start_serial' => 1, 'end_serial' => $quantity, 'quantity' => $quantity,
            'amount_kg' => $quantity * config('ticket_specs.' . $spec),
        ]);
    }

    public function testTheTicketSalesShowTheNumberOfTicketsOfEachSpec()
    {
        $gas = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'A', 'amount_kg' => 40]);   // 3 x 5kg and 2 x 10kg
        $this->item($gas, 0, 3);
        $this->item($gas, 1, 2);
        $diesel = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'D', 'amount_kg' => 70]);
        $this->item($diesel, 0, 4);
        $this->item($diesel, 3, 1);
        $this->item($diesel, 0, 1);                                                                            // a second run of the same spec adds up

        $html = $this->as('accountant')->get('/confirmations?date=2026-10-01')->assertOk()->getContent();
        $from = strpos($html, 'id="tab-ticket" role="tabpanel"');
        $ticket = substr($html, $from, strpos($html, '</form>', $from) - $from);

        // Count over the specs of every fuel (gasoline 5/10/15, diesel 5/10/15/50): 5kg, 10kg, 15kg, 50kg
        $this->assertRegExp('/<th colspan="4" class="text-center">Count<\/th>/', $ticket);
        $this->assertRegExp('/<th class="text-right">5kg<\/th>\s*<th class="text-right">10kg<\/th>\s*<th class="text-right">15kg<\/th>\s*<th class="text-right">50kg<\/th>/', $ticket);
        $this->assertSame(1, substr_count($ticket, '<table'));

        // the counts of a sale, then its weight, total, paid and unpaid
        $cells = function ($sale) use ($ticket) {
            preg_match('/value="' . $sale->id . '".*?<\/tr>/s', $ticket, $row);
            preg_match_all('/<td class="text-right">([^<]*)<\/td>/', $row[0], $m);

            return $m[1];
        };
        $this->assertSame(['3', '2', '', '', '40.00', '20.00', '20.00', '0.00'], $cells($gas));          // gasoline has no 50kg
        $this->assertSame(['5', '', '', '1', '70.00', '20.00', '20.00', '0.00'], $cells($diesel));
        // the totals of each spec at the foot
        preg_match('/<tfoot>.*<\/tfoot>/s', $ticket, $foot);
        preg_match_all('/<td class="text-right">([^<]*)<\/td>/', $foot[0], $m);
        $this->assertSame(['8', '2', '', '1', '110.00', '40.00', '40.00', '0.00'], $m[1]);

        // spot and others have no count columns
        foreach (['spot', 'others'] as $tab) {
            $from = strpos($html, 'id="tab-' . $tab . '" role="tabpanel"');
            $this->assertStringNotContainsString('>Count<', substr($html, $from, strpos($html, '</form>', $from) - $from), $tab);
        }

        // the Excel sheet: Count merged over the four spec columns, every other heading over two rows, the counts and their totals
        $sales = Sale::with('items')->where('sale_type', 'ticket')->orderBy('amount_kg')->get();
        $path = \App\Support\Reports\XlsxWriter::build(\App\Support\Reports\ReportSheets::confirmationSales([
            'title' => 'Ticket Sales', 'fuels' => config('fuel_types'), 'customers' => collect(),
            'sides' => [['title' => null, 'rows' => $sales, 'withFuel' => true, 'detail' => 'payment', 'specs' => [0 => 5, 1 => 10, 2 => 15, 3 => 50]]],
        ]));
        $zip = new \ZipArchive();
        $zip->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('<mergeCell ref="E1:H1"/>', $sheet);   // Count: after Time, Fuel type, Customer, Payment method
        $this->assertStringContainsString('<mergeCell ref="A1:A2"/>', $sheet);
        $this->assertStringContainsString('<mergeCell ref="I1:I2"/>', $sheet);
        $this->assertStringContainsString('>Count<', $sheet);
        foreach (['5kg', '10kg', '15kg', '50kg'] as $label) {
            $this->assertStringContainsString('>' . $label . '<', $sheet);
        }
        // row 3: gasoline 3 and 2 tickets, row 4: diesel 5 of 5kg and 1 of 50kg; the totals in row 5
        $this->assertRegExp('#<row r="3">.*Gasoline.*<v>3</v>.*<v>2</v>.*<v>40</v>.*</row>#s', $sheet);
        $this->assertRegExp('#<row r="4">.*Diesel.*<v>5</v>.*<v>1</v>.*<v>70</v>.*</row>#s', $sheet);
        $this->assertRegExp('#<row r="5">.*Total.*<v>8</v>.*<v>2</v>.*<v>1</v>.*<v>110</v>.*</row>#s', $sheet);
    }

    public function testConfirmingOneTabComesBackToThatTab()
    {
        Bus::fake();
        $ticket = $this->sale('2026-10-01', ['sale_type' => 'ticket']);
        $spot = $this->sale('2026-10-01');

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'from' => 'sales', 'tab' => 'ticket', 'sales' => [$ticket->id]])
            ->assertRedirect(route('confirmations.index', ['date' => '2026-10-01']) . '#tab-ticket');

        $this->assertNotNull($ticket->fresh()->confirmed_at);
        $this->assertNull($spot->fresh()->confirmed_at);
        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'tab' => 'nowhere', 'sales' => [$spot->id]])->assertSessionHasErrors('tab');
    }

    public function testEachTabOfTheSalesPageExportsItsTableAsOneSheet()
    {
        $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'A', 'amount_kg' => 10, 'payment_method' => 'bank']);
        $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'A', 'amount_kg' => 3]);
        $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'D', 'amount_kg' => 7]);
        $this->sale('2026-10-01', ['fuel_type' => 'D']);
        $this->sale('2026-10-01', ['usage' => 'self']);

        $html = $this->as('accountant')->get('/confirmations?date=2026-10-01')->assertOk()->getContent();
        // one button for each tab
        $this->assertSame(4, substr_count($html, 'fa-file-excel'));
        foreach (['ticket', 'spot', 'card', 'others'] as $block) {
            $this->assertStringContainsString('confirmations/sales-export?date=2026-10-01&amp;block=' . $block, $html);
            $response = $this->as('accountant')->get('/confirmations/sales-export?date=2026-10-01&block=' . $block)->assertOk();
            $response->assertHeader('content-type', \App\Support\Reports\XlsxWriter::MIME);
            $this->assertStringContainsString('confirmation-sales-2026-10-01-' . $block . '.xlsx', $response->headers->get('content-disposition'));
        }

        // the ticket file: one sheet, one plain table with a fuel column and the payment, the totals at the foot
        $zip = new \ZipArchive();
        $path = \App\Support\Reports\XlsxWriter::build(\App\Support\Reports\ReportSheets::confirmationSales([
            'title' => 'Ticket Sales', 'fuels' => config('fuel_types'), 'customers' => collect(),
            'sides' => [['title' => null, 'rows' => Sale::where('sale_type', 'ticket')->orderBy('amount_kg')->get(), 'withFuel' => true, 'detail' => 'payment']],
        ]));
        $zip->open($path);
        $this->assertFalse($zip->getFromName('xl/worksheets/sheet2.xml'));      // one sheet
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        $this->assertStringNotContainsString('<mergeCell', $sheet);
        $this->assertStringContainsString('Fuel type', $sheet);
        $this->assertStringContainsString('Payment method', $sheet);
        $this->assertStringContainsString('Gasoline', $sheet);
        $this->assertStringContainsString('Diesel', $sheet);
        $this->assertRegExp('#<row r="1">.*Time.*Fuel type.*Payment method.*</row>#s', $sheet);
        $this->assertStringContainsString('Total', $sheet);
        $this->assertStringContainsString('<v>20</v>', $sheet); // 10 + 3 + 7 kg

        // the spot file: a heading over each fuel (merged) and the sales of the two fuels side by side
        $rows = Sale::where('sale_type', 'spot')->where('usage', 'sale');
        $path = \App\Support\Reports\XlsxWriter::build(\App\Support\Reports\ReportSheets::confirmationSales([
            'title' => 'Spot Sales', 'fuels' => config('fuel_types'), 'customers' => collect(),
            'sides' => [
                ['title' => 'Gasoline (0)', 'rows' => (clone $rows)->where('fuel_type', 'A')->get(), 'withFuel' => false, 'detail' => 'payment'],
                ['title' => 'Diesel (1)', 'rows' => (clone $rows)->where('fuel_type', 'D')->get(), 'withFuel' => false, 'detail' => 'payment'],
            ],
        ]));
        $zip->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('<mergeCell ref="A1:H1"/>', $sheet);   // a side is 8 columns: the last one says Confirmed
        $this->assertStringContainsString('<mergeCell ref="I1:P1"/>', $sheet);
        $this->assertStringContainsString('Gasoline (0)', $sheet);
        $this->assertStringContainsString('Diesel (1)', $sheet);

        $this->as('accountant')->get('/confirmations/sales-export?date=2026-10-01&block=nowhere')->assertSessionHasErrors('block');
        $this->as('assistant')->get('/confirmations/sales-export?date=2026-10-01&block=spot')->assertForbidden();
    }

    public function testTheSupplementsHaveGasolineOnTopAndDieselUnderIt()
    {
        $diesel = $this->supplement('2026-10-01', ['fuel_type' => 'D']);
        $gasoline = $this->supplement('2026-10-01', ['fuel_type' => 'A']);

        $response = $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertOk();
        $byFuel = $response->viewData('byFuel');

        $this->assertSame(['A', 'D'], array_keys($byFuel));
        $this->assertSame([$gasoline->id], $byFuel['A']->pluck('id')->all());
        $this->assertSame([$diesel->id], $byFuel['D']->pluck('id')->all());

        // one table for each fuel type, one under the other: gasoline on top, diesel under it (not side by side)
        $html = $response->getContent();
        $html = substr($html, strpos($html, 'id="tab-supplements" role="tabpanel"'), strpos($html, 'id="tab-remainders" role="tabpanel"') - strpos($html, 'id="tab-supplements" role="tabpanel"'));
        $this->assertSame(2, substr_count($html, '<table'));
        $this->assertRegExp('/<h5[^>]*>Gasoline \(1\)<\/h5>.*?id="supplements-table-A".*?<h5[^>]*>Diesel \(1\)<\/h5>.*?id="supplements-table-D"/s', $html);
        $this->assertRegExp('/id="supplements-table-A".*?value="' . $gasoline->id . '".*?id="supplements-table-D".*?value="' . $diesel->id . '"/s', $html);
        $this->assertSame(2, substr_count($html, 'name="supplements[]"'));
        $this->assertStringNotContainsString('border-left', $html);
    }

    public function testTheSupplementsPageHasSupplementsAndRemaindersTabs()
    {
        $supplement = $this->supplement('2026-10-01', ['fuel_type' => 'A', 'comment' => 'hose was changed', 'preset_kg' => 10, 'out_amount_kg' => 9, 'out_amount_lt' => 12.5]);
        $made = \App\Models\Supplements\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000001', 'fuel_type' => 'A', 'amount_kg' => 1, 'remainder_date' => '2026-10-01', 'complete_date' => '2026-10-02']);
        \App\Models\Supplements\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000002', 'fuel_type' => 'A', 'amount_kg' => 2, 'remainder_date' => '2026-10-03']);   // another day

        $html = $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertOk()->getContent();

        $this->assertRegExp('/id="tab-supplements-link"[^>]*>Supplements \(1\)<\/a>/', $html);
        $this->assertRegExp('/id="tab-remainders-link"[^>]*>Remainders \(1\)<\/a>/', $html);

        // the supplements tab: the form, the table with the comment and no Tickets column
        $from = strpos($html, 'id="tab-supplements" role="tabpanel"');
        $to = strpos($html, 'id="tab-remainders" role="tabpanel"');
        $supplements = substr($html, $from, $to - $from);
        $this->assertStringContainsString('name="supplements[]"', $supplements);
        $this->assertStringContainsString('<td>hose was changed</td>', $supplements);
        $this->assertStringNotContainsString('>Tickets<', $supplements);
        $this->assertStringContainsString('btn btn-success', $supplements);

        // the remainders tab: the remainders of the date only, listed (no checkbox, no confirm button), with the supply and the complete date
        $remainders = substr($html, $to);
        $this->assertStringContainsString($made->ticket_barcode, $remainders);
        $this->assertStringNotContainsString('0000025000000002', $remainders);
        $this->assertStringContainsString('2026-10-02', $remainders);
        $this->assertStringContainsString('2026-10-01 11:00:00', $remainders);   // the supply it came from
        $this->assertStringNotContainsString('type="checkbox"', $remainders);
        $this->assertStringNotContainsString('btn btn-success', $remainders);
        $this->assertStringContainsString('1.00', $remainders);
    }

    public function testTheSupplementsTableExportsAsOneSheetWithTheFuelsSideBySide()
    {
        $this->supplement('2026-10-01', ['fuel_type' => 'A', 'preset_kg' => 10, 'out_amount_kg' => 9, 'out_amount_lt' => 12.5, 'supplied_at' => '2026-10-01 10:00:00']);
        $this->supplement('2026-10-01', ['fuel_type' => 'A', 'preset_kg' => 4, 'out_amount_kg' => 3, 'out_amount_lt' => 4, 'supplied_at' => '2026-10-01 12:00:00']);
        $this->supplement('2026-10-01', ['fuel_type' => 'D', 'preset_kg' => 6, 'out_amount_kg' => 6, 'out_amount_lt' => 7, 'mode' => 'manual']);
        $this->supplement('2026-10-02', ['fuel_type' => 'D']);                       // another day
        $this->supplement('2026-10-01', ['fuel_type' => 'D', 'confirmed_at' => now(), 'confirmed_by' => 1]); // confirmed: not in the table

        $html = $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'fa-file-excel'));   // the supplements tab and the remainders tab
        $this->assertStringContainsString('confirmations/supplements-export?date=2026-10-01', $html);

        $response = $this->as('accountant')->get('/confirmations/supplements-export?date=2026-10-01')->assertOk();
        $response->assertHeader('content-type', \App\Support\Reports\XlsxWriter::MIME);
        $this->assertStringContainsString('confirmation-supplements-2026-10-01.xlsx', $response->headers->get('content-disposition'));

        $sides = [];
        foreach (['A', 'D'] as $fuel) {
            $rows = Supplement::withCount('tickets')->with('remainder')->where('supply_date', '2026-10-01')->whereNull('confirmed_at')->where('fuel_type', $fuel)->orderBy('supplied_at')->get();
            $sides[] = ['title' => config('fuel_types')[$fuel] . ' (' . $rows->count() . ')', 'rows' => $rows];
        }
        $path = \App\Support\Reports\XlsxWriter::build(\App\Support\Reports\ReportSheets::confirmationSupplements(['title' => 'Supplements', 'sides' => $sides, 'nozzleLabels' => []]));
        $zip = new \ZipArchive();
        $zip->open($path);
        $this->assertFalse($zip->getFromName('xl/worksheets/sheet2.xml'));      // one sheet
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('<mergeCell ref="A1:H1"/>', $sheet);   // a side is 9 columns: the last one says Confirmed
        $this->assertStringContainsString('<mergeCell ref="I1:P1"/>', $sheet);
        $this->assertStringContainsString('Gasoline (2)', $sheet);
        $this->assertStringContainsString('Diesel (1)', $sheet);
        $this->assertRegExp('#<row r="3">.*<v>10</v>.*Manual.*<v>6</v>.*</row>#s', $sheet);   // the first of each fuel in the same row
        $this->assertRegExp('#<row r="4">.*<v>4</v>.*</row>#s', $sheet);
        $this->assertStringContainsString('Total', $sheet);
        $this->assertStringContainsString('<v>14</v>', $sheet);                                // gasoline preset total

        $this->as('accountant')->get('/confirmations/supplements-export?date=bad')->assertSessionHasErrors('date');
        $this->as('assistant')->get('/confirmations/supplements-export?date=2026-10-01')->assertForbidden();
    }

    public function testTheRemaindersTabExportsToExcel()
    {
        $supplement = $this->supplement('2026-10-01', ['fuel_type' => 'A']);
        \App\Models\Supplements\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000001', 'fuel_type' => 'A', 'amount_kg' => 1.5, 'remainder_date' => '2026-10-01', 'complete_date' => '2026-10-02']);
        \App\Models\Supplements\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000002', 'fuel_type' => 'A', 'amount_kg' => 2, 'remainder_date' => '2026-10-01']);
        \App\Models\Supplements\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000003', 'fuel_type' => 'A', 'amount_kg' => 9, 'remainder_date' => '2026-10-04']);   // another day

        $html = $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertOk()->getContent();
        $this->assertStringContainsString('confirmations/remainders-export?date=2026-10-01', $html);

        $response = $this->as('accountant')->get('/confirmations/remainders-export?date=2026-10-01')->assertOk();
        $response->assertHeader('content-type', \App\Support\Reports\XlsxWriter::MIME);
        $this->assertStringContainsString('confirmation-remainders-2026-10-01.xlsx', $response->headers->get('content-disposition'));

        $rows = \App\Models\Supplements\Remainder::with('supplement')->where('remainder_date', '2026-10-01')->orderBy('amount_kg')->get();
        $path = \App\Support\Reports\XlsxWriter::build(\App\Support\Reports\ReportSheets::confirmationRemainders(['title' => 'Remainders', 'rows' => $rows, 'fuels' => config('fuel_types'), 'nozzleLabels' => []]));
        $zip = new \ZipArchive();
        $zip->open($path);
        $this->assertFalse($zip->getFromName('xl/worksheets/sheet2.xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        $this->assertRegExp('#<row r="1">.*Barcode.*Fuel type.*Weight \(kg\).*From supply.*Complete date.*</row>#s', $sheet);
        $this->assertStringContainsString('0000025000000001', $sheet);
        $this->assertStringContainsString('0000025000000002', $sheet);
        $this->assertStringNotContainsString('0000025000000003', $sheet);
        $this->assertStringContainsString('2026-10-02', $sheet);                      // the complete date
        $this->assertStringContainsString('2026-10-01 11:00:00', $sheet);             // the supply it came from
        $this->assertStringContainsString('<v>3.5</v>', $sheet);                      // the total kg

        $this->as('accountant')->get('/confirmations/remainders-export?date=bad')->assertSessionHasErrors('date');
        $this->as('assistant')->get('/confirmations/remainders-export?date=2026-10-01')->assertForbidden();
    }

    public function testConfirmingOnTheSupplementsPageComesBackThereAndLeavesTheDeletedSalesAlone()
    {
        Bus::fake();   // confirming the whole day queues a dump of the database: not here
        $supplement = $this->supplement('2026-10-01');
        $deleted = $this->sale('2026-10-01');
        $deleted->delete();

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'from' => 'supplements', 'supplements' => [$supplement->id]])
            ->assertRedirect(route('confirmations.supplements', ['date' => '2026-10-01']));

        $this->assertNotNull($supplement->fresh()->confirmed_at);
        $this->assertNotNull(Sale::onlyTrashed()->find($deleted->id)); // erased from the Sales page only

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'from' => 'supplements'])->assertSessionHasErrors('supplements');
        $this->assertNotNull(Sale::onlyTrashed()->find($deleted->id));

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01'])->assertRedirect(route('confirmations.index', ['date' => '2026-10-01']));
        $this->assertNull(Sale::onlyTrashed()->find($deleted->id));
    }

    private function supplyTicket($supplement, $code, $fuel, $spec, $kg, $serial)
    {
        return \App\Models\Tickets\Ticket::forceCreate([
            'barcode' => str_pad($code . $serial, 16, '0', STR_PAD_LEFT), 'fuel_type' => $fuel, 'spec' => $spec, 'code' => $code, 'serial' => $serial,
            'amount_kg' => $kg, 'status' => config('ticket_statuses.supply'), 'supplement_id' => $supplement->id, 'synced' => false,
        ]);
    }

    public function testTheSummaryHasSalesSupplementsAndRemaindersSections()
    {
        // sales of the date: ticket (2 gasoline, 1 diesel), spot (1 diesel), others (1 self use, gasoline); confirmed or not; a deleted one and
        // one of another day are not counted
        $first = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'A', 'amount_kg' => 10, 'total_amount' => 20, 'paid_amount' => 15, 'unpaid_amount' => 5]);
        $this->item($first, 1, 1);                                                      // 1 x 10kg
        $second = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'A', 'amount_kg' => 4, 'total_amount' => 8, 'paid_amount' => 8, 'unpaid_amount' => 0,
            'payment_method' => 'bank', 'confirmed_at' => now(), 'confirmed_by' => 1]);
        $this->item($second, 0, 3);                                                     // 3 x 5kg (confirmed sales count too)
        $this->item($second, 0, 2);                                                     // another run of 5kg
        $third = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'D', 'amount_kg' => 6]);
        $this->item($third, 3, 1);                                                      // 1 x 50kg
        $this->sale('2026-10-01', ['sale_type' => 'spot', 'fuel_type' => 'D', 'amount_kg' => 7]);
        $this->sale('2026-10-01', ['sale_type' => 'spot', 'fuel_type' => 'A', 'usage' => 'self', 'amount_kg' => 2, 'total_amount' => 0, 'paid_amount' => 0]);
        $this->sale('2026-10-01', ['sale_type' => 'spot', 'fuel_type' => 'D', 'usage' => 'other', 'amount_kg' => 1, 'total_amount' => 0, 'paid_amount' => 0]);   // given away
        $this->sale('2026-10-02', ['amount_kg' => 99]);
        $this->sale('2026-10-01', ['amount_kg' => 77])->delete();

        // supplements of the date: one with a ticket of the stock (gasoline 10kg) and a remainder ticket (2.5 kg), one with a spot ticket
        // (diesel 8kg), one with a ticket of self use (gasoline 3kg)
        $one = $this->supplement('2026-10-01', ['fuel_type' => 'A', 'preset_kg' => 12.5, 'out_amount_lt' => 14, 'out_amount_kg' => 12, 'supplied_at' => '2026-10-01 10:00:00', 'comment' => 'hose changed']);
        $two = $this->supplement('2026-10-01', ['fuel_type' => 'D', 'preset_kg' => 8, 'out_amount_lt' => 9, 'out_amount_kg' => 8, 'supplied_at' => '2026-10-01 11:00:00',
            'confirmed_at' => now(), 'confirmed_by' => 1]);
        $three = $this->supplement('2026-10-01', ['fuel_type' => 'A', 'preset_kg' => 3, 'out_amount_lt' => 3, 'out_amount_kg' => 3, 'supplied_at' => '2026-10-01 12:00:00', 'comment' => 'meter reset']);
        $this->supplyTicket($one, '04', 'A', 1, 10, 1);          // gasoline 10kg, from the stock
        $this->supplyTicket($one, '25', 'A', null, 2.5, 2);      // a remainder ticket
        $this->supplyTicket($two, '21', 'D', null, 8, 3);        // a spot ticket
        $this->supplyTicket($three, '22', 'A', null, 3, 4);      // self use
        $this->supplyTicket($three, '24', 'A', null, 1, 5);      // ticket loan: the others
        $this->supplement('2026-10-02', ['fuel_type' => 'A']);   // another day: its tickets are not counted
        \App\Models\Supplements\Remainder::forceCreate(['supplement_id' => $one->id, 'ticket_barcode' => '0000025000000002', 'fuel_type' => 'A', 'amount_kg' => 2.5, 'remainder_date' => '2026-10-01', 'complete_date' => '2026-10-01']);
        \App\Models\Supplements\Remainder::forceCreate(['supplement_id' => $three->id, 'ticket_barcode' => '0000025000000009', 'fuel_type' => 'D', 'amount_kg' => 1, 'remainder_date' => '2026-10-01']);
        \App\Models\Supplements\Remainder::forceCreate(['supplement_id' => $two->id, 'ticket_barcode' => '0000025000000008', 'fuel_type' => 'D', 'amount_kg' => 4, 'remainder_date' => '2026-09-30', 'complete_date' => '2026-10-01']);

        $response = $this->as('accountant')->get('/confirmations/summary?date=2026-10-01')->assertOk();
        $summary = $response->viewData('summary');


        // sales by kind and fuel
        $this->assertSame(['ticket', 'spot', 'card', 'self', 'others'], array_keys($summary['salesBy']));
        $this->assertSame(['A', 'D'], array_keys($summary['salesBy']['ticket']));
        $this->assertEquals(['count' => 2, 'kg' => 14, 'total' => 28, 'paid' => 23, 'unpaid' => 5, 'specs' => [0 => 5, 1 => 1, 2 => 0, 3 => 0]], $summary['salesBy']['ticket']['A']);
        $this->assertEquals(6, $summary['salesBy']['ticket']['D']['kg']);
        $this->assertEquals([0 => 0, 1 => 0, 2 => 0, 3 => 1], $summary['salesBy']['ticket']['D']['specs']);
        $this->assertSame([0 => 5, 1 => 10, 2 => 15, 3 => 50], $summary['ticketSpecs']);
        $this->assertArrayNotHasKey('specs', $summary['salesBy']['spot']['D']);                 // only the ticket sales count tickets per spec
        $this->assertEquals(0, $summary['salesBy']['spot']['A']['count']);
        $this->assertEquals(7, $summary['salesBy']['spot']['D']['kg']);
        $this->assertEquals(1, $summary['salesBy']['self']['A']['count']);                       // self use is a kind of its own
        $this->assertEquals(2, $summary['salesBy']['self']['A']['kg']);
        $this->assertEquals(0, $summary['salesBy']['self']['D']['count']);
        $this->assertEquals(1, $summary['salesBy']['others']['D']['count']);                     // given away
        $this->assertEquals(1, $summary['salesBy']['others']['D']['kg']);
        $this->assertEquals(0, $summary['salesBy']['others']['A']['count']);

        // the supplements by kind (of their tickets) and fuel: $one used a ticket of the stock (10 kg) and a remainder ticket (2.5 kg): the
        // stock ticket covered more, so it is a supplement for tickets; $two used a spot ticket; $three used self use (3 kg) and a ticket loan (1 kg)
        $this->assertSame(['ticket', 'spot', 'card', 'remainder', 'self', 'others'], array_keys($summary['supplementsBy']));
        $this->assertSame(['A', 'D'], array_keys($summary['supplementsBy']['ticket']));
        $this->assertEquals(['count' => 1, 'preset' => 12.5, 'kg' => 12, 'remainder' => 2.5, 'comment' => 'hose changed'], $summary['supplementsBy']['ticket']['A']);
        $this->assertEquals(0, $summary['supplementsBy']['ticket']['D']['count']);
        $this->assertEquals(['count' => 1, 'preset' => 8, 'kg' => 8, 'remainder' => 4, 'comment' => ''], $summary['supplementsBy']['spot']['D']);
        $this->assertEquals(0, $summary['supplementsBy']['remainder']['A']['count']);
        $this->assertEquals(['count' => 1, 'preset' => 3, 'kg' => 3, 'remainder' => 1, 'comment' => 'meter reset'], $summary['supplementsBy']['self']['A']);
        $this->assertEquals(0, $summary['supplementsBy']['others']['A']['count']);
        $this->assertArrayNotHasKey('lt', $summary['supplementsBy']['ticket']['A']);                                // no litres

        // remainders made on the date and used up on it
        $this->assertEquals(['made' => 1, 'made_kg' => 2.5, 'used' => 1, 'used_kg' => 2.5], $summary['remaindersBy']['A']);
        $this->assertEquals(['made' => 1, 'made_kg' => 1, 'used' => 1, 'used_kg' => 4], $summary['remaindersBy']['D']);

        $html = $response->getContent();
        $this->assertStringNotContainsString('Deleted sales', $html);   // no table of counts on the page
        // the three sections, in this order, and the kinds in their tables
        $at = [];
        foreach (['<h5 class="mt-3">Sales</h5>', '<h5 class="mt-3">Supplements</h5>', '<h5 class="mt-3">Remainders</h5>'] as $heading) {
            $this->assertStringContainsString($heading, $html);
            $at[] = strpos($html, $heading);
        }
        $sorted = $at;
        sort($sorted);
        $this->assertSame($sorted, $at);
        foreach (['Ticket Sales', 'Spot Sales', 'Card Sales', 'Self', 'Others', 'Tickets', 'Spot', 'Card', 'Remainder'] as $label) {
            $this->assertStringContainsString('>' . $label . '<', $html, $label);
        }

        // the sales section: one table for each of ticket sales, spot sales, self and others, each under its own heading. Only the ticket one has
        // a row per fuel with the Tickets group over 5kg / 10kg / 15kg / 50kg; the other three have the fuel types side by side, one row
        $from = strpos($html, '<h5 class="mt-3">Sales</h5>');
        $sales = substr($html, $from, strpos($html, '<h5 class="mt-3">Supplements</h5>') - $from);
        $this->assertSame(5, substr_count($sales, '<table'));
        $parts = preg_split('/<h6 class="mt-3">/', $sales);
        $this->assertCount(6, $parts);
        $this->assertStringStartsWith('Ticket Sales</h6>', $parts[1]);
        $this->assertStringStartsWith('Spot Sales</h6>', $parts[2]);
        $this->assertStringStartsWith('Card Sales</h6>', $parts[3]);
        $this->assertStringStartsWith('Self</h6>', $parts[4]);
        $this->assertStringStartsWith('Others</h6>', $parts[5]);
        $this->assertRegExp('/<th colspan="4" class="text-center">Tickets<\/th>/', $parts[1]);
        $this->assertRegExp('/<th class="text-right">5kg<\/th>\s*<th class="text-right">10kg<\/th>\s*<th class="text-right">15kg<\/th>\s*<th class="text-right">50kg<\/th>/', $parts[1]);
        // gasoline: 2 sales, 5 tickets of 5kg, 1 of 10kg; the total row of the table adds the diesel 50kg
        preg_match_all('/<tr>\s*<td>Gasoline<\/td>\s*<td class="text-right">(\d+)<\/td>\s*<td class="text-right">(\d+)<\/td>\s*<td class="text-right">(\d+)<\/td>\s*<td class="text-right">(\d+)<\/td>\s*<td class="text-right">(\d+)<\/td>/', $parts[1], $m);
        $this->assertSame(['2', '5', '1', '0', '0'], [$m[1][0], $m[2][0], $m[3][0], $m[4][0], $m[5][0]]);
        $this->assertRegExp('/<td>Total<\/td>\s*<td class="text-right">3<\/td>\s*<td class="text-right">5<\/td>\s*<td class="text-right">1<\/td>\s*<td class="text-right">0<\/td>\s*<td class="text-right">1<\/td>/', $parts[1]);
        foreach ([2, 3, 4, 5] as $i) {
            $this->assertStringNotContainsString('>Tickets<', $parts[$i]);
            $this->assertStringNotContainsString('5kg', $parts[$i]);
            $this->assertRegExp('/<th colspan="5" class="text-center ">Gasoline<\/th>\s*<th colspan="5" class="text-center border-left">Diesel<\/th>/', $parts[$i]);
            $this->assertSame(1, preg_match_all('/<tbody>\s*<tr>/', $parts[$i]));
            $this->assertSame(1, substr_count($parts[$i], '<tr>', strpos($parts[$i], '<tbody>')), $i);   // a single row: both fuel types in it
        }
        // spot: no gasoline, 1 diesel sale of 7 kg; self: 1 gasoline sale of 2 kg; others: 1 diesel sale of 1 kg
        $this->assertRegExp('/<tbody>\s*<tr>\s*<td class="text-right ">0<\/td>.*?<td class="text-right border-left">1<\/td>\s*<td class="text-right">7\.00<\/td>/s', $parts[2]);
        $this->assertRegExp('/<tbody>\s*<tr>\s*<td class="text-right ">1<\/td>\s*<td class="text-right">2\.00<\/td>.*?<td class="text-right border-left">0<\/td>/s', $parts[4]);
        $this->assertRegExp('/<tbody>\s*<tr>\s*<td class="text-right ">0<\/td>.*?<td class="text-right border-left">1<\/td>\s*<td class="text-right">1\.00<\/td>/s', $parts[5]);

        // the supplements section: one table, a row for each kind (tickets, spot, remainder, self, others), gasoline on the left and diesel on the right
        $from = strpos($html, '<h5 class="mt-3">Supplements</h5>');
        $supplements = substr($html, $from, strpos($html, '<h5 class="mt-3">Remainders</h5>') - $from);
        $this->assertSame(1, substr_count($supplements, '<table'));
        $this->assertRegExp('/<th rowspan="2">Ticket<\/th>\s*<th colspan="5" class="text-center border-left">Gasoline<\/th>\s*<th colspan="5" class="text-center border-left">Diesel<\/th>/', $supplements);
        $this->assertSame(2, substr_count($supplements, '<th>Comment</th>'));
        $this->assertStringNotContainsString('Out amount (L)', $supplements);
        $this->assertStringNotContainsString('>Weight (kg)<', $supplements);
        // the rows, in this order
        $at = [];
        foreach (['Tickets', 'Spot', 'Card', 'Remainder', 'Self', 'Others'] as $kind) {
            $this->assertRegExp('/<td>' . $kind . '<\/td>/', $supplements, $kind);
            $at[] = strpos($supplements, '<td>' . $kind . '</td>');
        }
        $sorted = $at;
        sort($sorted);
        $this->assertSame($sorted, $at);
        // tickets: gasoline 1 supplement, preset 12.50, out 12.00, remainder 2.50 and its comment; diesel none
        $this->assertRegExp('/<td>Tickets<\/td>\s*<td class="text-right border-left">1<\/td>\s*<td class="text-right">12\.50<\/td>\s*<td class="text-right">12\.00<\/td>\s*<td class="text-right">2\.50<\/td>\s*<td class="text-wrap">hose changed<\/td>\s*<td class="text-right border-left">0<\/td>/', $supplements);
        // spot: none for gasoline, the diesel supplement on the right
        $this->assertRegExp('/<td>Spot<\/td>\s*<td class="text-right border-left">0<\/td>.*?<td class="text-right border-left">1<\/td>\s*<td class="text-right">8\.00<\/td>\s*<td class="text-right">8\.00<\/td>\s*<td class="text-right">4\.00<\/td>/s', $supplements);
        // the totals of the table: gasoline 2 supplements, preset 15.50, out 15.00, remainder 3.50; diesel 1, 8.00, 8.00, 4.00
        $this->assertRegExp('/<td>Total<\/td>\s*<td class="text-right border-left">2<\/td>\s*<td class="text-right">15\.50<\/td>\s*<td class="text-right">15\.00<\/td>\s*<td class="text-right">3\.50<\/td>\s*<td><\/td>\s*<td class="text-right border-left">1<\/td>\s*<td class="text-right">8\.00<\/td>\s*<td class="text-right">8\.00<\/td>\s*<td class="text-right">4\.00<\/td>/', $supplements);

        // the remainders section: gasoline and diesel side by side in one row
        $remainders = substr($html, strpos($html, '<h5 class="mt-3">Remainders</h5>'));
        $this->assertRegExp('/<th colspan="4" class="text-center ">Gasoline<\/th>\s*<th colspan="4" class="text-center border-left">Diesel<\/th>/', $remainders);
        $this->assertRegExp('/<tbody>\s*<tr>\s*<td class="text-right ">1<\/td>\s*<td class="text-right">2\.50<\/td>\s*<td class="text-right">1<\/td>\s*<td class="text-right">2\.50<\/td>\s*<td class="text-right border-left">1<\/td>\s*<td class="text-right">1\.00<\/td>\s*<td class="text-right">1<\/td>\s*<td class="text-right">4\.00<\/td>/', $remainders);
    }

    public function testTheSummaryExportsToExcelAsThreeSheets()
    {
        $ticketSale = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'fuel_type' => 'A', 'amount_kg' => 10, 'total_amount' => 20, 'paid_amount' => 15, 'unpaid_amount' => 5]);
        $this->item($ticketSale, 1, 1);
        $this->sale('2026-10-01', ['sale_type' => 'spot', 'fuel_type' => 'D', 'amount_kg' => 7, 'total_amount' => 14, 'paid_amount' => 14]);
        $this->sale('2026-10-01', ['sale_type' => 'spot', 'fuel_type' => 'A', 'usage' => 'self', 'amount_kg' => 2, 'total_amount' => 0, 'paid_amount' => 0]);
        $this->sale('2026-10-01')->delete();
        $this->sale('2026-10-02', ['amount_kg' => 99]);
        $supplement = $this->supplement('2026-10-01', ['fuel_type' => 'D', 'preset_kg' => 8, 'out_amount_kg' => 8, 'out_amount_lt' => 9, 'comment' => 'hose changed']);
        $this->supplyTicket($supplement, '21', 'D', null, 8, 1);                      // a spot ticket
        \App\Models\Supplements\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000001', 'fuel_type' => 'D', 'amount_kg' => 1.5, 'remainder_date' => '2026-10-01']);

        $html = $this->as('accountant')->get('/confirmations/summary?date=2026-10-01')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'fa-file-excel'));
        $this->assertStringContainsString('confirmations/summary-export?date=2026-10-01', $html);

        $response = $this->as('accountant')->get('/confirmations/summary-export?date=2026-10-01')->assertOk();
        $response->assertHeader('content-type', \App\Support\Reports\XlsxWriter::MIME);
        $this->assertStringContainsString('confirmation-summary-2026-10-01.xlsx', $response->headers->get('content-disposition'));

        $zip = new \ZipArchive();
        $zip->open($response->baseResponse->getFile()->getPathname());
        $workbook = $zip->getFromName('xl/workbook.xml');
        $sheets = [];
        for ($i = 1; $i <= 3; $i++) {
            $sheets[$i] = $zip->getFromName('xl/worksheets/sheet' . $i . '.xml');
        }
        $this->assertFalse($zip->getFromName('xl/worksheets/sheet4.xml'));
        $zip->close();

        // sheets: Sales, Supplements, Remainders
        $this->assertRegExp('/name="Sales".*name="Supplements".*name="Remainders"/s', $workbook);
        $this->assertStringNotContainsString('name="Summary"', $workbook);

        // sales: a row for each kind and fuel, the tickets per spec for the ticket sales only, the total at the foot
        $this->assertRegExp('#<row r="1">.*Sale type.*Fuel type.*Count.*Tickets 5kg.*Tickets 10kg.*Tickets 15kg.*Tickets 50kg.*Weight \(kg\).*Unpaid amount.*</row>#s', $sheets[1]);
        $this->assertRegExp('#<row r="2">.*Ticket Sales.*Gasoline.*<v>1</v>.*<v>1</v>.*<v>10</v>.*<v>20</v>.*<v>15</v>.*<v>5</v>.*</row>#s', $sheets[1]);   // 1 sale, 1 ticket of 10kg
        $this->assertStringContainsString('Spot Sales', $sheets[1]);
        $this->assertStringContainsString('>Self<', $sheets[1]);
        $this->assertStringContainsString('>Others<', $sheets[1]);
        $this->assertRegExp('#<row r="12">.*Total.*<v>3</v>.*<v>19</v>.*</row>#s', $sheets[1]);                                                              // 3 sales, 10 + 7 + 2 kg
        $this->assertStringNotContainsString('<row r="13">', $sheets[1]);
        $this->assertStringContainsString('Card Sales', $sheets[1]);

        // supplements: kinds as rows, gasoline and diesel side by side under merged headings, no litres
        $this->assertStringContainsString('<mergeCell ref="B1:F1"/>', $sheets[2]);
        $this->assertStringContainsString('<mergeCell ref="G1:K1"/>', $sheets[2]);
        $this->assertStringContainsString('<mergeCell ref="A1:A2"/>', $sheets[2]);
        $this->assertStringNotContainsString('Out amount (L)', $sheets[2]);
        $this->assertStringContainsString('Preset amount (kg)', $sheets[2]);
        $this->assertRegExp('#>Spot</t>.*<v>1</v>.*<v>8</v>.*<v>8</v>.*<v>1.5</v>.*hose changed#s', $sheets[2]);
        $this->assertStringContainsString('Total', $sheets[2]);

        // remainders: made 1 of 1.5 kg for diesel
        $this->assertStringContainsString('<mergeCell ref="A1:D1"/>', $sheets[3]);
        $this->assertStringContainsString('<mergeCell ref="E1:H1"/>', $sheets[3]);
        $this->assertStringContainsString('<v>1.5</v>', $sheets[3]);

        $this->as('accountant')->get('/confirmations/summary-export?date=bad')->assertSessionHasErrors('date');
        $this->as('assistant')->get('/confirmations/summary-export?date=2026-10-01')->assertForbidden();
    }

    public function testWithoutADateTodayIsShownOnEveryPage()
    {
        $old = $this->sale('2026-10-01');
        $today = $this->sale(today()->toDateString());

        // the oldest waiting date is not chosen for the cashier any more: it is one of the badges under the date box
        $this->as('manager')->get('/confirmations')->assertOk()
            ->assertSee($today->id)
            ->assertDontSee($old->id)
            ->assertSee('value="' . today()->toDateString() . '"', false)
            ->assertSee('2026-10-01 (1)');
        foreach (['overview', 'fuel', 'tickets', 'supplements', 'debts', 'summary'] as $page) {
            $this->get('/confirmations/' . $page)->assertOk()->assertSee('value="' . today()->toDateString() . '"', false);
        }
        $this->get('/confirmations?date=2026-10-01')->assertOk()->assertSee($old->id)->assertSee('value="2026-10-01"', false);
    }

    public function testTheWaitingDatesAreListedWithTheirCounts()
    {
        $this->sale('2026-10-01');
        $this->sale('2026-10-01');
        $this->supplement('2026-10-02');

        // each page shows the count of its own records, and only the dates that have some
        $this->as('manager')->get('/confirmations')->assertOk()
            ->assertSee('2026-10-01 (2)')
            ->assertDontSee('2026-10-02 (');
        $this->get('/confirmations/supplements')->assertOk()->assertSee('2026-10-02 (1)')->assertDontSee('2026-10-01 (');
        $this->get('/confirmations/overview')->assertOk()->assertSee('2026-10-01 (2)')->assertSee('2026-10-02 (1)');   // all the pages added up
        $this->get('/confirmations/summary')->assertOk()->assertSee('2026-10-01 (2)')->assertSee('2026-10-02 (1)');
        // on the summary a waiting date leads to the overview of that date, on the other pages to the same page
        $this->get('/confirmations/summary')->assertSee('href="' . route('confirmations.overview', ['date' => '2026-10-01']) . '"', false)
            ->assertDontSee('href="' . route('confirmations.summary', ['date' => '2026-10-01']) . '"', false);
        $this->get('/confirmations')->assertSee('href="' . route('confirmations.index', ['date' => '2026-10-01']) . '"', false);
    }

    public function testConfirmingStampsOnlyTheCheckedRecords()
    {
        $one = $this->sale('2026-10-01');
        $two = $this->sale('2026-10-01');
        $supplement = $this->supplement('2026-10-01');
        $left = $this->supplement('2026-10-01');

        $this->as('accountant')->post('/confirmations', [
            'date' => '2026-10-01', 'sales' => [$one->id], 'supplements' => [$supplement->id],
        ])->assertRedirect('/confirmations?date=2026-10-01')
            ->assertSessionHas('status', __('confirm.done', ['sales' => 1, 'supplements' => 1, 'date' => '2026-10-01']));

        $this->assertNotNull($one->fresh()->confirmed_at);
        $this->assertSame(User::where('role', 'accountant')->value('id'), (int) $one->fresh()->confirmed_by);
        $this->assertNull($two->fresh()->confirmed_at);
        $this->assertNotNull($supplement->fresh()->confirmed_at);
        $this->assertNull($left->fresh()->confirmed_at);
    }

    public function testConfirmedRecordsLeaveTheListAndAreCounted()
    {
        $sale = $this->sale('2026-10-01');
        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$sale->id]]);

        $this->get('/confirmations?date=2026-10-01')->assertOk()
            ->assertDontSee('name="sales[]"', false)
            ->assertSee(__('confirm.already', ['fuel' => 0, 'tickets' => 0, 'sales' => 1, 'supplements' => 0, 'debts' => 0]));
    }

    public function testARecordOfAnotherDateOrAlreadyConfirmedIsNotTouched()
    {
        $other = $this->sale('2026-10-02');
        $stamp = now()->subDays(3);
        $done = $this->sale('2026-10-01', ['confirmed_at' => $stamp, 'confirmed_by' => 99]);

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$other->id, $done->id]])
            ->assertSessionHas('status', __('confirm.done', ['sales' => 0, 'supplements' => 0, 'date' => '2026-10-01']));

        $this->assertNull($other->fresh()->confirmed_at);
        $this->assertSame(99, (int) $done->fresh()->confirmed_by); // not re-confirmed by someone else
        $this->assertSame($stamp->format('Y-m-d H:i:s'), $done->fresh()->confirmed_at->format('Y-m-d H:i:s'));
    }

    public function testNothingCheckedIsAnError()
    {
        $this->sale('2026-10-01');

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01'])->assertSessionHasErrors('sales');
        $this->post('/confirmations', ['date' => '2026-10-01', 'sales' => [], 'supplements' => []])->assertSessionHasErrors('sales');
    }

    public function testBadInputIsRejected()
    {
        $sale = $this->sale('2026-10-01');

        $this->as('accountant')->post('/confirmations', ['date' => today()->addDay()->toDateString(), 'sales' => [$sale->id]])->assertSessionHasErrors('date');
        $this->post('/confirmations', ['date' => 'yesterday', 'sales' => [$sale->id]])->assertSessionHasErrors('date');
        $this->post('/confirmations', ['date' => '2026-10-01', 'sales' => ['not-a-uuid']])->assertSessionHasErrors('sales.0');
        $this->assertNull($sale->fresh()->confirmed_at);
    }

    private function payment($date, $extra = [])
    {
        return \App\Models\Debts\DebtPayment::forceCreate($extra + [
            'customer_id' => \App\Models\Sales\Customer::first()->id, 'debt_id' => null, 'amount' => 50, 'payment_method' => 'bank',
            'paid_date' => $date, 'date' => $date, 'user_id' => 1,
        ]);
    }

    private function tank()
    {
        return \App\Models\Fuel\Tank::first() ?: \App\Models\Fuel\Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
    }

    private function fuelInbound($date, $extra = [])
    {
        return \App\Models\Fuel\FuelInbound::forceCreate($extra + [
            'tank_id' => $this->tank()->id, 'fuel_type' => 'D', 'amount_kg' => 1000, 'cost' => 6.5, 'inbound_date' => $date, 'user_id' => 1, 'from_name' => 'Refinery',
        ]);
    }

    private function fuelOutbound($date, $extra = [])
    {
        $outbound = new \App\Models\Fuel\FuelOutbound;
        $outbound->forceFill($extra + ['tank_id' => $this->tank()->id, 'outbound_date' => $date, 'amount_kg' => 40, 'to_name' => 'Tractor', 'user_id' => 1])->save();

        return $outbound;
    }

    private function settlement($date, $extra = [])
    {
        $customer = \App\Models\Sales\Customer::first();
        $loan = \App\Models\Debts\TicketLoan::first() ?: \App\Models\Debts\TicketLoan::forceCreate([
            'loaned_at' => $date . ' 09:00:00', 'customer_id' => $customer->id, 'fuel_type' => 'D', 'amount_kg' => 20, 'user_id' => 1,
        ]);

        return \App\Models\Debts\TicketLoanSettlement::forceCreate($extra + [
            'loan_id' => $loan->id, 'ticket_barcode' => '0000025500000001', 'amount_kg' => 5, 'settled_at' => $date . ' 10:00:00', 'date' => $date, 'user_id' => 1,
        ]);
    }

    public function testTheMenuOfTheDailyConfirmationHasTheOverviewTheStocksTheSalesTheSupplementsTheDebtsAndTheSummary()
    {
        $html = $this->as('accountant')->get('/')->assertOk()->getContent();
        $html = substr($html, strpos($html, __('menu.group_confirmations')));   // the group of the daily confirmation
        $labels = ['Overview', 'Fuel stock', 'Ticket stock', 'Sales', 'Supplements', 'Debts', 'Summary'];
        $at = -1;
        foreach ($labels as $label) {
            $pos = strpos($html, '>' . $label . '<', $at + 1);
            $this->assertNotFalse($pos, $label);
            $this->assertGreaterThan($at, $pos, $label . ' is in the order of the menu');
            $at = $pos;
        }
        foreach (['/confirmations/overview', '/confirmations/fuel', '/confirmations/tickets', '/confirmations', '/confirmations/supplements', '/confirmations/debts', '/confirmations/summary'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['assistant', 'cashier'] as $role) {
            foreach (['/confirmations/overview', '/confirmations/fuel', '/confirmations/debts'] as $path) {
                $this->as($role)->get($path)->assertForbidden();
            }
        }
    }

    public function testTheFuelStockPageListsAndConfirmsTheInboundsAndTheOutboundsMadeByHand()
    {
        $inbound = $this->fuelInbound('2026-10-01');
        $otherInbound = $this->fuelInbound('2026-10-03');
        $outbound = $this->fuelOutbound('2026-10-01');
        $otherOutbound = $this->fuelOutbound('2026-10-02');
        $supplement = $this->supplement('2026-10-01');   // the outbound of a supply follows the supply: not a line here
        $this->fuelOutbound('2026-10-01', ['supplement_id' => $supplement->id]);

        $html = $this->as('accountant')->get('/confirmations/fuel?date=2026-10-01')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'name="fuel_inbounds[]"'));
        $this->assertSame(1, substr_count($html, 'name="fuel_outbounds[]"'));            // the one made by hand, not the supply's
        $this->assertStringContainsString('Refinery', $html);
        $this->assertStringContainsString('Tractor', $html);

        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'fuel', 'fuel_inbounds' => [$inbound->id, $otherInbound->id], 'fuel_outbounds' => [$outbound->id, $otherOutbound->id]])
            ->assertRedirect(route('confirmations.fuel', ['date' => '2026-10-01']))->assertSessionHas('status');
        $this->assertStringContainsString('Confirmed 1 fuel inbounds and 1 fuel outbounds for 2026-10-01', session('status'));
        $this->assertNotNull($inbound->fresh()->confirmed_at);
        $this->assertNotNull($outbound->fresh()->confirmed_at);
        $this->assertNull($otherInbound->fresh()->confirmed_at);                          // another date: not touched
        $this->assertNull($otherOutbound->fresh()->confirmed_at);

        // confirmed ones have no check box; the accountant cannot take a confirmation back, a manager can
        $html = $this->get('/confirmations/fuel?date=2026-10-01')->getContent();
        $this->assertSame(0, substr_count($html, 'name="fuel_inbounds[]"') + substr_count($html, 'name="fuel_outbounds[]"'));
        $this->assertStringNotContainsString('/unconfirm', $html);
        $this->post('/inbound/' . $inbound->id . '/unconfirm')->assertForbidden();
        $this->post('/outbound/' . $outbound->id . '/unconfirm')->assertForbidden();
        $this->as('manager')->post('/inbound/' . $inbound->id . '/unconfirm')->assertRedirect()->assertSessionHas('status');
        $this->post('/outbound/' . $outbound->id . '/unconfirm')->assertRedirect()->assertSessionHas('status');
        $this->assertNull($inbound->fresh()->confirmed_at);
        $this->assertNull($outbound->fresh()->confirmed_at);
        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'fuel'])->assertSessionHasErrors('fuel');   // nothing checked
    }

    public function testTheDebtsPageListsAndConfirmsTheDebtPaymentsAndTheTicketSettlements()
    {
        \App\Models\Sales\Customer::forceCreate(['name' => 'ACME']);
        $payment = $this->payment('2026-10-01');
        $reversal = $this->payment('2026-10-01', ['amount' => -50, 'reverses_payment_id' => $payment->id]);
        $other = $this->payment('2026-10-02');
        $settlement = $this->settlement('2026-10-01');
        $otherSettlement = $this->settlement('2026-10-02');

        $html = $this->as('accountant')->get('/confirmations/debts?date=2026-10-01')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'name="debt_payments[]"'));             // the payment and its reversal (a line of its own, negative)
        $this->assertSame(1, substr_count($html, 'name="ticket_settlements[]"'));
        $this->assertStringContainsString('ACME', $html);
        $this->assertStringContainsString('-50.00', $html);
        $this->assertStringContainsString('0000025500000001', $html);

        $this->post('/confirmations', [
            'date' => '2026-10-01', 'from' => 'debts', 'debt_payments' => [$payment->id, $other->id], 'ticket_settlements' => [$settlement->id, $otherSettlement->id],
        ])->assertRedirect(route('confirmations.debts', ['date' => '2026-10-01']))->assertSessionHas('status');
        $this->assertStringContainsString('Confirmed 1 debt payments and 1 ticket settlements for 2026-10-01', session('status'));
        $this->assertNotNull($payment->fresh()->confirmed_at);
        $this->assertNotNull($settlement->fresh()->confirmed_at);
        $this->assertNull($reversal->fresh()->confirmed_at);                              // not checked
        $this->assertNull($other->fresh()->confirmed_at);                                 // another date: not touched
        $this->assertNull($otherSettlement->fresh()->confirmed_at);

        $html = $this->get('/confirmations/debts?date=2026-10-01')->getContent();
        $this->assertSame(1, substr_count($html, 'name="debt_payments[]"'));
        $this->assertSame(0, substr_count($html, 'name="ticket_settlements[]"'));
        $this->assertStringNotContainsString('/unconfirm', $html);
        $this->post('/payments/' . $payment->id . '/unconfirm')->assertForbidden();
        $this->post('/loans/settlements/' . $settlement->id . '/unconfirm')->assertForbidden();

        $html = $this->as('manager')->get('/confirmations/debts?date=2026-10-01')->getContent();
        $this->assertStringContainsString('formaction="' . url('/payments/' . $payment->id . '/unconfirm') . '"', $html);
        $this->assertStringContainsString('formaction="' . url('/loans/settlements/' . $settlement->id . '/unconfirm') . '"', $html);
        $this->post('/payments/' . $payment->id . '/unconfirm')->assertRedirect()->assertSessionHas('status');
        $this->post('/loans/settlements/' . $settlement->id . '/unconfirm')->assertRedirect()->assertSessionHas('status');
        $this->assertNull($payment->fresh()->confirmed_at);
        $this->assertNull($settlement->fresh()->confirmed_at);
        $this->post('/loans/settlements/' . $settlement->id . '/unconfirm')->assertSessionHasErrors('record');   // not confirmed any more
        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'debts'])->assertSessionHasErrors('debts');   // nothing checked
    }

    public function testTheWaitingDatesSuggestTheLatestFiveAndSayHowManyOlderOnesThereAre()
    {
        foreach (['2026-09-20', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26'] as $date) {
            $this->sale($date);
        }

        $page = $this->as('manager')->get('/confirmations?date=2026-09-20')->assertOk();
        foreach (['2026-09-26', '2026-09-25', '2026-09-24', '2026-09-23', '2026-09-22'] as $date) {
            $page->assertSee($date . ' (1)');                                      // the five newest
        }
        $page->assertDontSee('2026-09-21 (1)')->assertDontSee('2026-09-20 (1)')->assertSee('+ 2 older: choose the date');
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, '2026-09-22 (1)'), strpos($html, '2026-09-26 (1)'));   // newest first

        // the waiting dates stand under the date box, not beside it
        $this->assertStringNotContainsString('justify-content-between', substr($html, strpos($html, 'f_date') - 400, 900));
        $this->assertLessThan(strpos($html, __('confirm.waiting') . ':'), strpos($html, 'id="f_date"'));

        // five or fewer: no note
        $this->as('manager')->get('/confirmations/supplements')->assertOk()->assertDontSee('older: choose the date');
    }

    public function testTheWaitingDatesCountTheFuelStockAndTheDebtsToo()
    {
        \App\Models\Sales\Customer::forceCreate(['name' => 'ACME']);
        $this->payment('2026-10-01');
        $this->payment('2026-10-01');
        $this->settlement('2026-10-01');
        $this->fuelInbound('2026-10-01');
        $this->fuelOutbound('2026-10-01');
        $this->fuelInbound('2026-10-04', ['confirmed_at' => now(), 'confirmed_by' => 1]);     // confirmed: not waiting

        // each page counts its own: the fuel stock the inbound and the outbound, the debts the two payments and the settlement
        $this->as('manager')->get('/confirmations/fuel')->assertOk()->assertSee('2026-10-01 (2)')->assertDontSee('2026-10-04 (');
        $this->get('/confirmations/debts')->assertOk()->assertSee('2026-10-01 (3)')->assertDontSee('2026-10-04 (');
        $this->get('/confirmations')->assertOk()->assertDontSee('2026-10-01 (');                                   // nothing of the sales waits
        $this->get('/confirmations?date=2026-10-04')->assertOk()->assertSee(__('confirm.already', ['fuel' => 1, 'tickets' => 0, 'sales' => 0, 'supplements' => 0, 'debts' => 0]));
    }

    public function testAConfirmedFuelInboundOrOutboundCannotBeChangedOrDeletedUntilAManagerTakesTheConfirmationBack()
    {
        $inbound = $this->fuelInbound('2026-10-01');
        $outbound = $this->fuelOutbound('2026-10-01');
        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'from' => 'fuel', 'fuel_inbounds' => [$inbound->id], 'fuel_outbounds' => [$outbound->id]]);
        $this->assertNotNull($inbound->fresh()->confirmed_at);
        $this->assertNotNull($outbound->fresh()->confirmed_at);

        $this->get('/inbound/' . $inbound->id . '/edit')->assertRedirect('/inbound')->assertSessionHasErrors('inbound');
        $this->put('/inbound/' . $inbound->id, ['tank_id' => $inbound->tank_id, 'inbound_date' => '2026-10-01', 'amount_kg' => 5, 'cost' => 1])->assertSessionHasErrors('inbound');
        $this->assertEquals(1000, $inbound->fresh()->amount_kg);
        $this->delete('/inbound/' . $inbound->id)->assertSessionHasErrors('inbound');
        $this->assertNotNull(\App\Models\Fuel\FuelInbound::find($inbound->id));
        $this->get('/inbound')->assertOk()->assertDontSee(route('inbound.edit', $inbound->id));      // the list shows a lock instead

        $this->get('/outbound/' . $outbound->id . '/edit')->assertRedirect('/outbound')->assertSessionHasErrors('outbound');
        $this->put('/outbound/' . $outbound->id, ['tank_id' => $outbound->tank_id, 'outbound_date' => '2026-10-01', 'amount_kg' => 5])->assertSessionHasErrors('outbound');
        $this->assertEquals(40, $outbound->fresh()->amount_kg);
        $this->delete('/outbound/' . $outbound->id)->assertSessionHasErrors('outbound');
        $this->assertNotNull(\App\Models\Fuel\FuelOutbound::find($outbound->id));
        $this->get('/outbound')->assertOk()->assertDontSee(route('outbound.edit', $outbound->id));

        $this->as('manager')->post('/inbound/' . $inbound->id . '/unconfirm')->assertRedirect();
        $this->post('/outbound/' . $outbound->id . '/unconfirm')->assertRedirect();
        $this->as('accountant')->get('/inbound/' . $inbound->id . '/edit')->assertOk();
        $this->put('/inbound/' . $inbound->id, ['tank_id' => $inbound->tank_id, 'inbound_date' => '2026-10-01', 'amount_kg' => 1200, 'cost' => 6.5])->assertSessionHasNoErrors();
        $this->assertEquals(1200, $inbound->fresh()->amount_kg);
        $this->put('/outbound/' . $outbound->id, ['tank_id' => $outbound->tank_id, 'outbound_date' => '2026-10-01', 'amount_kg' => 55])->assertSessionHasNoErrors();
        $this->assertEquals(55, $outbound->fresh()->amount_kg);
    }

    public function testTheDayIsNotFullyConfirmedWhileSomethingOfTheFuelStockOrTheDebtsWaits()
    {
        config(['backup.dump_on_confirmation' => true]);
        Bus::fake();
        \App\Models\Sales\Customer::forceCreate(['name' => 'ACME']);
        $sale = $this->sale('2026-10-01');
        $payment = $this->payment('2026-10-01');
        $settlement = $this->settlement('2026-10-01');
        $inbound = $this->fuelInbound('2026-10-01');
        $outbound = $this->fuelOutbound('2026-10-01');

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$sale->id]]);
        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);
        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'debts', 'debt_payments' => [$payment->id]]);
        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);                    // the settlement still waits
        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'debts', 'ticket_settlements' => [$settlement->id]]);
        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);                    // the fuel stock still waits
        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'fuel', 'fuel_inbounds' => [$inbound->id]]);
        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);                    // the outbound still waits
        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'fuel', 'fuel_outbounds' => [$outbound->id]]);
        Bus::assertDispatchedAfterResponse(RunBackupDump::class);
    }

    public function testTheOverviewShowsEachAreaOfTheDateAndWhatToLookAt()
    {
        \App\Models\Sales\Customer::forceCreate(['name' => 'ACME']);
        $sale = $this->sale('2026-10-01');                                                                           // no cost, confirmed below
        $this->sale('2026-10-01', ['cost' => 5]);
        $this->supplement('2026-10-01');                                                                              // no tickets, no card
        $this->payment('2026-10-01');
        $this->settlement('2026-10-01');
        $this->fuelInbound('2026-10-01');
        $gone = $this->sale('2026-10-01', ['cost' => 5]);
        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$sale->id]]);
        $gone->delete();                                                                                              // waits to be erased (confirming the sales erases the deleted ones)

        $page = $this->get('/confirmations/overview?date=2026-10-01')->assertOk();
        $html = $page->getContent();
        $rows = function ($key) use ($html) {
            preg_match('/<tr class="overview-' . $key . '">(.*?)<\/tr>/s', $html, $m);
            preg_match_all('/<td[^>]*>\s*(\d+)\s*<\/td>/', $m[1], $n);

            return array_map('intval', $n[1]);                                                                       // records, confirmed, waiting
        };
        $this->assertSame([1, 0, 1], $rows('fuel'));
        $this->assertSame([0, 0, 0], $rows('tickets'));
        $this->assertSame([2, 1, 1], $rows('sales'));
        $this->assertSame([1, 0, 1], $rows('supplements'));
        $this->assertSame([2, 0, 2], $rows('debts'));

        // the order of the menu and the links to the pages of the date
        $this->assertTrue(strpos($html, 'overview-fuel') < strpos($html, 'overview-tickets') && strpos($html, 'overview-tickets') < strpos($html, 'overview-sales')
            && strpos($html, 'overview-sales') < strpos($html, 'overview-supplements') && strpos($html, 'overview-supplements') < strpos($html, 'overview-debts'));
        $this->assertStringContainsString(route('confirmations.debts', ['date' => '2026-10-01']), $html);

        // what to look at: the sale without a cost (the other one has it), the supply without tickets, the deleted sale; the terminals once the day is over
        $this->assertStringContainsString('1 sales of this date have no cost.', $html);                          // the confirmed one has none either: it is counted with the day
        $this->assertStringContainsString('1 supplies of this date have no tickets and no card.', $html);
        $this->assertStringContainsString('1 deleted sales of this date', $html);
        $device = \App\Models\Admin\Device::forceCreate(['name' => 'POS1', 'initial_sync_done' => true, 'last_synced_at' => '2026-10-01 18:00:00']);
        $this->get('/confirmations/overview?date=2026-10-01')->assertSee('POS1');                                // it uploaded before the day ended (9:00 of the next day)
        $device->forceFill(['last_synced_at' => '2026-10-02 10:00:00'])->save();
        $this->get('/confirmations/overview?date=2026-10-01')->assertDontSee('have not uploaded');
        $this->get('/confirmations/overview?date=' . today()->toDateString())->assertDontSee('have not uploaded');   // the running day: not a warning yet
    }

    public function testTheSummaryHasTheMoneyOfTheDayByCashAndBank()
    {
        \App\Models\Sales\Customer::forceCreate(['name' => 'ACME']);
        $this->sale('2026-10-01', ['payment_method' => 'cash', 'total_amount' => 100, 'paid_amount' => 100, 'unpaid_amount' => 0]);
        $this->sale('2026-10-01', ['payment_method' => 'cash', 'total_amount' => -30, 'paid_amount' => -30, 'unpaid_amount' => 0, 'reverses_sale_id' => $this->sale('2026-09-30')->id]);   // a return
        $this->sale('2026-10-01', ['payment_method' => 'bank', 'total_amount' => 200, 'paid_amount' => 50, 'unpaid_amount' => 150]);
        $this->sale('2026-10-02', ['payment_method' => 'cash', 'total_amount' => 999, 'paid_amount' => 999, 'unpaid_amount' => 0]);                         // another day
        $this->payment('2026-10-01', ['payment_method' => 'cash', 'amount' => 40]);
        $this->payment('2026-10-01', ['payment_method' => 'cash', 'amount' => -10]);
        $this->payment('2026-10-01', ['payment_method' => 'bank', 'amount' => 25]);
        $gone = $this->sale('2026-10-01', ['payment_method' => 'cash', 'total_amount' => 70, 'paid_amount' => 70, 'unpaid_amount' => 0]);
        $gone->delete();                                                                                                                                    // a deleted sale is not counted

        $summary = $this->as('accountant')->get('/confirmations/summary?date=2026-10-01')->assertOk()->viewData('summary');
        $this->assertEquals(['sales_received' => 100, 'sales_refunded' => -30, 'payments_received' => 40, 'payments_reversed' => -10, 'net' => 100, 'credit' => 0], $summary['money']['cash']);
        $this->assertEquals(['sales_received' => 50, 'sales_refunded' => 0, 'payments_received' => 25, 'payments_reversed' => 0, 'net' => 75, 'credit' => 150], $summary['money']['bank']);

        $html = $this->get('/confirmations/summary?date=2026-10-01')->getContent();
        $this->assertStringContainsString('Money of the day', $html);
        $this->assertRegExp('/money-net">\s*<td>Net received<\/td>\s*<td class="text-right">100.00<\/td>\s*<td class="text-right">75.00<\/td>\s*<td class="text-right">175.00<\/td>/', $html);
    }
    public function testConfirmingAWholeDayQueuesTheBackupAfterTheResponse()
    {
        config(['backup.dump_on_confirmation' => true]);
        Bus::fake();
        $sale = $this->sale('2026-10-01');
        $supplement = $this->supplement('2026-10-01');

        $response = $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$sale->id], 'supplements' => [$supplement->id]]);

        $response->assertRedirect()->assertSessionHas('status');
        $this->assertStringContainsString(__('confirm.backup_queued'), session('status'));
        Bus::assertDispatchedAfterResponse(RunBackupDump::class, function ($job) {
            return $job->note === 'confirmed 2026-10-01';
        });
    }

    public function testADayWithSomethingLeftToConfirmMakesNoBackupYet()
    {
        config(['backup.dump_on_confirmation' => true]);
        Bus::fake();
        $first = $this->sale('2026-10-01');
        $this->sale('2026-10-01'); // still waiting

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$first->id]])->assertRedirect();

        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);
        $this->assertStringNotContainsString(__('confirm.backup_queued'), session('status'));
    }

    public function testTheBackupWaitsForTheTerminalsThatHaveNotUploadedSinceTheDayEnded()
    {
        config(['backup.dump_on_confirmation' => true]);
        Bus::fake();
        $device = \App\Models\Admin\Device::forceCreate(['name' => 'POS1', 'initial_sync_done' => true, 'last_synced_at' => '2026-10-01 18:00:00']);
        $first = $this->sale('2026-10-01');
        $second = $this->sale('2026-10-01');

        // the terminal uploaded before the day ended (9:00 of the next day): it may still hold records of the day, so no backup yet, and the message says why
        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$first->id, $second->id]])->assertRedirect();
        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);
        $this->assertStringContainsString('these terminals have not uploaded since the business day ended: POS1', session('status'));
        $this->assertStringNotContainsString(__('confirm.backup_queued'), session('status'));

        // once it has uploaded, confirming the next record of the day makes the backup
        $device->forceFill(['last_synced_at' => '2026-10-02 10:00:00'])->save();
        $late = $this->sale('2026-10-01');
        $this->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$late->id]])->assertRedirect();
        Bus::assertDispatchedAfterResponse(RunBackupDump::class);
    }

    public function testTheBackupWaitsForTheBusinessDayToEnd()
    {
        config(['backup.dump_on_confirmation' => true]);
        Bus::fake();
        $sale = $this->sale(today()->toDateString());

        $this->as('accountant')->post('/confirmations', ['date' => today()->toDateString(), 'sales' => [$sale->id]])->assertRedirect();

        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);
        $this->assertStringContainsString('the business day is not over', session('status'));
    }

    public function testTheOverviewListsTheSuppliesWhoseTicketsAndRemaindersDoNotAgree()
    {
        $serial = 0;
        $supply = function ($preset, $out, $ticketKg, $remainderKg = null) use (&$serial) {
            $supplement = $this->supplement('2026-10-01', ['preset_kg' => $preset, 'out_amount_kg' => $out, 'out_amount_lt' => $out, 'supplied_at' => '2026-10-01 1' . $serial . ':00:00']);
            ++$serial;
            \App\Models\Tickets\Ticket::forceCreate([
                'barcode' => sprintf('0000006%09d', $serial), 'fuel_type' => 'D', 'spec' => null, 'code' => '06', 'serial' => $serial, 'amount_kg' => $ticketKg,
                'status' => 2, 'supplement_id' => $supplement->id,
            ]);
            if ($remainderKg !== null) {
                \App\Models\Supplements\Remainder::forceCreate([
                    'supplement_id' => $supplement->id, 'ticket_barcode' => sprintf('0000029%09d', $serial), 'fuel_type' => 'D', 'amount_kg' => $remainderKg,
                    'remainder_date' => '2026-10-01', 'created_at' => '2026-10-01 12:00:00',
                ]);
            }

            return $supplement;
        };
        $supply(5, 5, 5);                  // agrees
        $supply(5, 3, 5, 2);               // agrees: 2 kg left, a remainder of 2 kg
        $supply(5, 4.97, 5);               // 0.03 kg left: the station keeps it
        $short = $supply(10, 10, 5);       // the tickets add up to 5 kg but the preset is 10
        $lost = $supply(5, 3, 5);          // 2 kg left and nowhere
        $wrong = $supply(5, 3, 5, 1);      // 2 kg left but the remainder is 1 kg
        $this->supplement('2026-10-01');   // no tickets at all: has its own note, not listed here
        $twice = $supply(5, 3, 5, 2);
        \App\Models\Supplements\Remainder::forceCreate([
            'supplement_id' => $twice->id, 'ticket_barcode' => '0000029000009999', 'fuel_type' => 'D', 'amount_kg' => 2, 'remainder_date' => '2026-10-01', 'created_at' => '2026-10-01 12:30:00',
        ]);

        $found = $this->as('accountant')->get('/confirmations/overview?date=2026-10-01')->assertOk()->viewData('warnings')['supplyChecks'];
        $this->assertEqualsCanonicalizing([$short->id, $lost->id, $wrong->id, $twice->id], array_column($found, 'id'));
        $html = $this->get('/confirmations/overview?date=2026-10-01')->getContent();
        $this->assertStringContainsString('Supplies of this date whose figures do not agree', $html);
        $this->assertStringContainsString('the tickets add up to 5.00 kg but the supply was preset to 10.00 kg', $html);
        $this->assertStringContainsString('2.00 kg of the tickets was not dispensed but the remainder is 0.00 kg', $html);
        $this->assertStringContainsString('2.00 kg of the tickets was not dispensed but the remainder is 1.00 kg', $html);
        $this->assertStringContainsString('2 remainders came out of this one supply', $html);

        $this->get('/confirmations/overview?date=2026-10-02')->assertDontSee('whose figures do not agree');
    }

    // the supplies page shows the cost of a supply (a user who may see costs), and marks a cost that has no fuel behind it; the Overview lists those
    public function testTheCostOfASupplyIsShownAndACostWithNoFuelBehindItIsMarked()
    {
        $tank = $this->tank();
        \App\Models\Fuel\FuelInbound::forceCreate(['tank_id' => $tank->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 6.5, 'inbound_date' => '2026-09-01', 'user_id' => 1]);
        $device = \App\Models\Admin\Device::forceCreate(['name' => 'POS1']);
        $nozzle = \App\Models\Fuel\Nozzle::forceCreate(['device_id' => $device->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => 'D']);
        $good = $this->supplement('2026-10-01', ['out_amount_kg' => 40, 'nozzle_id' => $nozzle->id]);
        $good->forceFill(['cost' => 6.5])->save();                                         // 6.50: 100 kg came in at it
        $bad = $this->supplement('2026-10-01', ['out_amount_kg' => 70, 'nozzle_id' => $nozzle->id]);
        $bad->forceFill(['cost' => 8])->save();                                            // 8.00: nothing came in at it, a layer of -70 kg

        $page = $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertOk();
        $html = $page->getContent();
        $this->assertStringContainsString('Cost per kg', $html);
        $this->assertStringContainsString('6.5000', $html);
        $this->assertSame([$bad->id], array_keys($page->viewData('belowZero')));
        $this->assertSame(1, substr_count($html, 'fa-exclamation-triangle'));

        $found = $this->get('/confirmations/overview?date=2026-10-01')->assertOk()->viewData('warnings')['supplyChecks'];
        $this->assertContains($bad->id, array_column($found, 'id'));
        $this->assertStringContainsString('the cost 8.0000 has no fuel behind it', collect($found)->firstWhere('id', $bad->id)['text']);
        $this->assertNotContains($good->id, array_column($found, 'id'));
    }

    public function testTheOverviewCountsTheTicketsIssuedOnTheDateAndNotSuppliedByKind()
    {
        $ticket = function ($barcode, $code, $status, $saleDate) {
            return \App\Models\Tickets\Ticket::forceCreate([
                'barcode' => $barcode, 'fuel_type' => $code[0] === '1' ? 'A' : 'D', 'code' => $code, 'serial' => (int) substr($barcode, 7), 'status' => $status, 'sale_date' => $saleDate,
            ]);
        };
        $ticket('8676121500000001', '21', 1, '2026-10-01 09:30:00');     // a spot sale ticket, not supplied
        $ticket('8676121500000002', '21', 1, '2026-10-01 23:10:00');     // another
        $ticket('8676125500000003', '25', 1, '2026-10-01 10:00:00');     // made for a card
        $ticket('8676121500000004', '21', 2, '2026-10-01 11:00:00');     // supplied: not counted
        $ticket('8676121500000005', '21', 3, '2026-10-01 11:00:00');     // restricted: not counted
        $ticket('8676129500000006', '29', 1, '2026-10-01 12:00:00');     // a remainder waits for its supply by design
        $ticket('8676121500000007', '21', 1, '2026-10-02 08:00:00');     // another day

        $html = $this->as('accountant')->get('/confirmations/overview?date=2026-10-01')->assertOk()->getContent();
        $this->assertStringContainsString('Tickets issued on this date that nobody has supplied yet', $html);
        $this->assertRegExp('/overview-unused-tickets.*Spot sale: 2.*Card: 1/s', $html);
        $this->assertStringNotContainsString('Remainder: ', $html);

        $this->get('/confirmations/overview?date=2026-09-30')->assertDontSee('nobody has supplied yet');
    }

    public function testNothingNewlyConfirmedMakesNoBackup()
    {
        config(['backup.dump_on_confirmation' => true]);
        Bus::fake();
        $done = $this->sale('2026-10-01', ['confirmed_at' => now(), 'confirmed_by' => 99]);

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$done->id]])->assertRedirect();

        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);
    }

    public function testTheBackupCanBeSwitchedOff()
    {
        config(['backup.dump_on_confirmation' => false]);
        Bus::fake();
        $sale = $this->sale('2026-10-01');

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$sale->id]])->assertRedirect();

        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);
    }

    // ---- the blank ticket stock: ticket inbounds and publishes ----

    private function inbound($date, $quantity = 10, $extra = [])
    {
        return TicketInbound::forceCreate($extra + ['fuel_type' => 'D', 'spec' => 0, 'quantity' => $quantity, 'inbound_date' => $date, 'user_id' => 1]);
    }

    private function publishBatch($date, $start = 1, $end = 5, $extra = [])
    {
        return TicketPublish::forceCreate($extra + ['fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'start_serial' => $start, 'end_serial' => $end,
            'count' => $end - $start + 1, 'date' => $date, 'user_id' => 1]);
    }

    public function testTheTicketStockPageListsTheInboundsAndPublishesOfTheDate()
    {
        $in = $this->inbound('2026-10-01', 100);
        $out = $this->inbound('2026-10-01', -8, ['note' => 'spoiled']);
        $this->inbound('2026-10-02', 50);                                   // another date
        $batch = $this->publishBatch('2026-10-01', 1, 90);
        $this->publishBatch('2026-10-02', 91, 95);

        $response = $this->as('accountant')->get('/confirmations/tickets?date=2026-10-01')->assertOk();
        $this->assertSame([$in->id, $out->id], $response->viewData('inbounds')->pluck('id')->all());
        $this->assertSame([$batch->id], $response->viewData('publishes')->pluck('id')->all());
        $response->assertSee('name="ticket_inbounds[]"', false)->assertSee('name="ticket_publishes[]"', false)->assertSee('spoiled');
        $this->assertStringContainsString('<td class="text-right">92</td>', $response->getContent());      // the total of the quantities: 100 - 8

        $this->as('manager')->get('/confirmations/tickets')->assertOk();
        $this->as('assistant')->get('/confirmations/tickets')->assertForbidden();
        $this->as('cashier')->get('/confirmations/tickets')->assertForbidden();
        $this->as('accountant')->get('/confirmations/supplements')->assertOk()->assertSee('Ticket stock');   // the menu of the group
    }

    public function testTheWaitingDatesCountTheTicketStockToo()
    {
        $this->sale('2026-10-01');
        $this->inbound('2026-10-01');
        $this->inbound('2026-10-01', 5);
        $this->publishBatch('2026-10-01');
        $this->inbound('2026-10-03');

        $this->as('manager')->get('/confirmations/tickets')->assertOk()
            ->assertSee('2026-10-01 (3)')
            ->assertSee('2026-10-03 (1)');
        $this->get('/confirmations')->assertOk()->assertSee('2026-10-01 (1)')->assertDontSee('2026-10-03 (');     // the sales page counts the sale only
    }

    public function testConfirmingTheTicketStockStampsOnlyTheCheckedRecordsOfTheDate()
    {
        $one = $this->inbound('2026-10-01');
        $left = $this->inbound('2026-10-01');
        $other = $this->inbound('2026-10-02');
        $batch = $this->publishBatch('2026-10-01');
        $done = $this->inbound('2026-10-01', 7, ['confirmed_at' => now()->subDays(3), 'confirmed_by' => 99]);

        $this->as('accountant')->post('/confirmations', [
            'date' => '2026-10-01', 'from' => 'tickets', 'ticket_inbounds' => [$one->id, $other->id, $done->id], 'ticket_publishes' => [$batch->id],
        ])->assertRedirect('/confirmations/tickets?date=2026-10-01')
            ->assertSessionHas('status', __('confirm.done_tickets', ['inbounds' => 1, 'publishes' => 1, 'date' => '2026-10-01']));

        $this->assertNotNull($one->fresh()->confirmed_at);
        $this->assertNotNull($batch->fresh()->confirmed_at);
        $this->assertNull($left->fresh()->confirmed_at);                        // not checked
        $this->assertNull($other->fresh()->confirmed_at);                       // another date
        $this->assertSame(99, (int) $done->fresh()->confirmed_by);              // not confirmed again by someone else

        $page = $this->get('/confirmations/tickets?date=2026-10-01')->assertOk();
        $this->assertStringNotContainsString('value="' . $one->id . '" class="check-ticket-inbounds"', $page->getContent());   // no check box on a confirmed one
        $this->assertStringContainsString('value="' . $left->id . '" class="check-ticket-inbounds"', $page->getContent());
        $this->get('/confirmations?date=2026-10-01')->assertSee(__('confirm.already', ['fuel' => 0, 'tickets' => 3, 'sales' => 0, 'supplements' => 0, 'debts' => 0]));

        // nothing checked is an error
        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'tickets'])->assertSessionHasErrors('tickets');
    }

    public function testAManagerTakesTheConfirmationOfTheTicketStockBack()
    {
        $inbound = $this->inbound('2026-10-01');
        $batch = $this->publishBatch('2026-10-01');
        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'from' => 'tickets', 'ticket_inbounds' => [$inbound->id], 'ticket_publishes' => [$batch->id]]);

        $page = $this->get('/confirmations/tickets?date=2026-10-01')->assertOk()->getContent();
        $this->assertStringNotContainsString(route('ticket-inbound.unconfirm', $inbound->id), $page);         // the accountant has no such button
        $this->post('/ticket-inbound/' . $inbound->id . '/unconfirm')->assertForbidden();
        $this->post('/ticket-publish/' . $batch->id . '/unconfirm')->assertForbidden();

        $page = $this->as('manager')->get('/confirmations/tickets?date=2026-10-01')->assertOk()->getContent();
        $this->assertStringContainsString(route('ticket-inbound.unconfirm', $inbound->id), $page);
        $this->assertStringContainsString(route('ticket-publish.unconfirm', $batch->id), $page);
        $this->post('/ticket-inbound/' . $inbound->id . '/unconfirm')->assertRedirect();
        $this->post('/ticket-publish/' . $batch->id . '/unconfirm')->assertRedirect();
        $this->assertNull($inbound->fresh()->confirmed_at);
        $this->assertNull($batch->fresh()->confirmed_at);
        $this->post('/ticket-inbound/' . $inbound->id . '/unconfirm')->assertSessionHasErrors('record'); // not confirmed any more
    }

    public function testTheDumpAfterAFullyConfirmedDayWaitsForTheTicketStockToo()
    {
        Bus::fake();
        config(['backup.dump_on_confirmation' => true]);
        $sale = $this->sale('2026-10-01');
        $inbound = $this->inbound('2026-10-01');

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$sale->id]]);
        Bus::assertNotDispatchedAfterResponse(RunBackupDump::class);              // the inbound of the day is still waiting

        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'tickets', 'ticket_inbounds' => [$inbound->id]]);
        Bus::assertDispatchedAfterResponse(RunBackupDump::class);
    }
}
