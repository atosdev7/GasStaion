<?php

namespace Tests\Feature;

use App\Jobs\RunBackupDump;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Supplement;
use App\Models\User;
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
        // no Tickets column: the mode is followed by the preset, out kg and out litres, the remainder and the comment
        $this->assertRegExp('/<th>Nozzle<\/th>\s*<th>Mode<\/th>\s*<th class="text-right">Preset amount \(kg\)<\/th>\s*<th class="text-right">Out amount \(kg\)<\/th>\s*<th>Remainder<\/th>\s*<th>Comment<\/th>/', $html);
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
        $card = \App\Models\Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D']);
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
        // the spot tab is ONE table too, with gasoline on the left and diesel on the right in the same row
        $spot = $pane('spot');
        $this->assertSame(1, substr_count($spot, '<table'));
        $this->assertRegExp('/<th colspan="9" class="text-center ">Gasoline \(2\)<\/th>\s*<th colspan="9" class="text-center border-left">Diesel \(1\)<\/th>/', $spot);
        $this->assertSame(1, substr_count($spot, '<tbody>'));
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
        return \App\Models\SaleItem::forceCreate([
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
        $path = \App\Support\XlsxWriter::build(\App\Support\ReportSheets::confirmationSales([
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
            $response->assertHeader('content-type', \App\Support\XlsxWriter::MIME);
            $this->assertStringContainsString('confirmation-sales-2026-10-01-' . $block . '.xlsx', $response->headers->get('content-disposition'));
        }

        // the ticket file: one sheet, one plain table with a fuel column and the payment, the totals at the foot
        $zip = new \ZipArchive();
        $path = \App\Support\XlsxWriter::build(\App\Support\ReportSheets::confirmationSales([
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
        $path = \App\Support\XlsxWriter::build(\App\Support\ReportSheets::confirmationSales([
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

    public function testTheSupplementsHaveGasolineOnTheLeftAndDieselOnTheRight()
    {
        $diesel = $this->supplement('2026-10-01', ['fuel_type' => 'D']);
        $gasoline = $this->supplement('2026-10-01', ['fuel_type' => 'A']);

        $response = $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertOk();
        $byFuel = $response->viewData('byFuel');

        $this->assertSame(['A', 'D'], array_keys($byFuel));
        $this->assertSame([$gasoline->id], $byFuel['A']->pluck('id')->all());
        $this->assertSame([$diesel->id], $byFuel['D']->pluck('id')->all());

        // ONE table: gasoline on the left, diesel on the right, the two in the same row
        $html = $response->getContent();
        $html = substr($html, strpos($html, 'id="tab-supplements" role="tabpanel"'), strpos($html, 'id="tab-remainders" role="tabpanel"') - strpos($html, 'id="tab-supplements" role="tabpanel"'));
        $this->assertSame(1, substr_count($html, '<table'));
        $this->assertRegExp('/<th colspan="9" class="text-center ">Gasoline \(1\)<\/th>\s*<th colspan="9" class="text-center border-left">Diesel \(1\)<\/th>/', $html);
        $this->assertRegExp('/<tr>\s*<td[^>]*>\s*<input type="checkbox" name="supplements\[\]" value="' . $gasoline->id . '".*?value="' . $diesel->id . '"/s', $html);
        $this->assertSame(2, substr_count($html, 'name="supplements[]"'));
    }

    public function testTheSupplementsPageHasSupplementsAndRemaindersTabs()
    {
        $supplement = $this->supplement('2026-10-01', ['fuel_type' => 'A', 'comment' => 'hose was changed', 'preset_kg' => 10, 'out_amount_kg' => 9, 'out_amount_lt' => 12.5]);
        $made = \App\Models\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000001', 'fuel_type' => 'A', 'amount_kg' => 1, 'remainder_date' => '2026-10-01', 'complete_date' => '2026-10-02']);
        \App\Models\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000002', 'fuel_type' => 'A', 'amount_kg' => 2, 'remainder_date' => '2026-10-03']);   // another day

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
        $response->assertHeader('content-type', \App\Support\XlsxWriter::MIME);
        $this->assertStringContainsString('confirmation-supplements-2026-10-01.xlsx', $response->headers->get('content-disposition'));

        $sides = [];
        foreach (['A', 'D'] as $fuel) {
            $rows = Supplement::withCount('tickets')->with('remainder')->where('supply_date', '2026-10-01')->whereNull('confirmed_at')->where('fuel_type', $fuel)->orderBy('supplied_at')->get();
            $sides[] = ['title' => config('fuel_types')[$fuel] . ' (' . $rows->count() . ')', 'rows' => $rows];
        }
        $path = \App\Support\XlsxWriter::build(\App\Support\ReportSheets::confirmationSupplements(['title' => 'Supplements', 'sides' => $sides, 'nozzleLabels' => []]));
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
        \App\Models\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000001', 'fuel_type' => 'A', 'amount_kg' => 1.5, 'remainder_date' => '2026-10-01', 'complete_date' => '2026-10-02']);
        \App\Models\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000002', 'fuel_type' => 'A', 'amount_kg' => 2, 'remainder_date' => '2026-10-01']);
        \App\Models\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000003', 'fuel_type' => 'A', 'amount_kg' => 9, 'remainder_date' => '2026-10-04']);   // another day

        $html = $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertOk()->getContent();
        $this->assertStringContainsString('confirmations/remainders-export?date=2026-10-01', $html);

        $response = $this->as('accountant')->get('/confirmations/remainders-export?date=2026-10-01')->assertOk();
        $response->assertHeader('content-type', \App\Support\XlsxWriter::MIME);
        $this->assertStringContainsString('confirmation-remainders-2026-10-01.xlsx', $response->headers->get('content-disposition'));

        $rows = \App\Models\Remainder::with('supplement')->where('remainder_date', '2026-10-01')->orderBy('amount_kg')->get();
        $path = \App\Support\XlsxWriter::build(\App\Support\ReportSheets::confirmationRemainders(['title' => 'Remainders', 'rows' => $rows, 'fuels' => config('fuel_types'), 'nozzleLabels' => []]));
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
        return \App\Models\Ticket::forceCreate([
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
        \App\Models\Remainder::forceCreate(['supplement_id' => $one->id, 'ticket_barcode' => '0000025000000002', 'fuel_type' => 'A', 'amount_kg' => 2.5, 'remainder_date' => '2026-10-01', 'complete_date' => '2026-10-01']);
        \App\Models\Remainder::forceCreate(['supplement_id' => $three->id, 'ticket_barcode' => '0000025000000009', 'fuel_type' => 'D', 'amount_kg' => 1, 'remainder_date' => '2026-10-01']);
        \App\Models\Remainder::forceCreate(['supplement_id' => $two->id, 'ticket_barcode' => '0000025000000008', 'fuel_type' => 'D', 'amount_kg' => 4, 'remainder_date' => '2026-09-30', 'complete_date' => '2026-10-01']);

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
        \App\Models\Remainder::forceCreate(['supplement_id' => $supplement->id, 'ticket_barcode' => '0000025000000001', 'fuel_type' => 'D', 'amount_kg' => 1.5, 'remainder_date' => '2026-10-01']);

        $html = $this->as('accountant')->get('/confirmations/summary?date=2026-10-01')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'fa-file-excel'));
        $this->assertStringContainsString('confirmations/summary-export?date=2026-10-01', $html);

        $response = $this->as('accountant')->get('/confirmations/summary-export?date=2026-10-01')->assertOk();
        $response->assertHeader('content-type', \App\Support\XlsxWriter::MIME);
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

    public function testWithoutADateTheOldestWaitingOneIsShown()
    {
        $this->sale('2026-10-03');
        $old = $this->sale('2026-10-01');
        $this->sale('2026-10-02', ['confirmed_at' => now(), 'confirmed_by' => 1]);

        $this->as('manager')->get('/confirmations')->assertOk()
            ->assertSee($old->id)
            ->assertSee('value="2026-10-01"', false);
    }

    public function testTheWaitingDatesAreListedWithTheirCounts()
    {
        $this->sale('2026-10-01');
        $this->sale('2026-10-01');
        $this->supplement('2026-10-02');

        $this->as('manager')->get('/confirmations')->assertOk()
            ->assertSee('2026-10-01 (2 / 0)')
            ->assertSee('2026-10-02 (0 / 1)');
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
            ->assertSee(__('confirm.already', ['sales' => 1, 'supplements' => 0]));
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
}
