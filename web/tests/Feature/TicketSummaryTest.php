<?php

namespace Tests\Feature;

use App\Models\Tickets\TicketInbound;
use App\Models\Tickets\TicketPublish;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class TicketSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    // spec 0 = 5 kg (code 06), spec 1 = 10 kg (code 07); fuel D = diesel
    private function receive($date, $quantity, $spec = 0, $fuel = 'D')
    {
        $this->post('/ticket-inbound', ['fuel_type' => $fuel, 'spec' => $spec, 'quantity' => $quantity, 'inbound_date' => $date]);
    }

    private function publish($date, $start, $end, $spec = 0, $fuel = 'D')
    {
        $this->post('/ticket-publish', ['stock_item' => $fuel . ':' . $spec, 'start_serial' => $start, 'end_serial' => $end, 'date' => $date]);
    }

    private function summary($fuel = 'D', $spec = 0)
    {
        return $this->get('/ticket-summary?fuel_type=' . $fuel . '&spec=' . $spec)->assertOk();
    }

    private function line($row)
    {
        return [$row->day, $row->in, $row->first, $row->last, $row->out, $row->balance];
    }

    public function testThePageStartsWithTheChoiceOnlyAndShowsTheTableAfterShow()
    {
        $this->as('manager');
        $this->receive('2026-09-02', 100);

        // first the choice: no table, no "all", no date boxes
        $page = $this->get('/ticket-summary')->assertOk();
        $this->assertFalse($page->viewData('chosen'));
        $page->assertDontSee('id="ticket-summary"', false)->assertDontSee(__('cards.all_fuel_types'))->assertDontSee(__('tickets.all_specs'));
        $page->assertDontSee('name="from"', false)->assertDontSee('name="to"', false);
        $page->assertSee('<select id="f_fuel_type" name="fuel_type"', false)->assertSee('<select id="f_spec" name="spec"', false);

        // then the table below, for the chosen fuel type and spec, with the choice kept in the form
        $page = $this->summary('D', 0);
        $this->assertTrue($page->viewData('chosen'));
        $page->assertSee('id="ticket-summary"', false)->assertSee('2026-09-02');
        $page->assertSee('value="D" selected', false);
    }

    public function testItShowsInAndOutPerDayWithARunningBalance()
    {
        $this->as('manager');
        $this->receive('2026-09-02', 100);                 // 5 kg diesel: +100
        $this->receive('2026-09-02', 40, 1);               // 10 kg diesel: another spec, not in this table
        $this->publish('2026-09-03', 1, 30);               // -30 (serials 1-30)
        $this->receive('2026-09-05', 50);                  // +50
        $this->publish('2026-09-05', 31, 40);              // -10 on the same day (serials 31-40)

        $rows = $this->summary('D', 0)->viewData('rows');

        $this->assertCount(3, $rows);
        $this->assertSame(['2026-09-02', 100, null, null, 0, 100], $this->line($rows[0]));
        $this->assertSame(['2026-09-03', 0, 1, 30, 30, 70], $this->line($rows[1]));
        // in and out of the same day are one row: 70 + 50 - 10
        $this->assertSame(['2026-09-05', 50, 31, 40, 10, 110], $this->line($rows[2]));

        $other = $this->summary('D', 1)->viewData('rows');
        $this->assertSame([['2026-09-02', 40, null, null, 0, 40]], [$this->line($other[0])]);
    }

    public function testTwoBatchesOfOneDayShowTheFirstAndTheLastNumberAndTheSum()
    {
        $this->as('manager');
        $this->receive('2026-09-02', 100);
        $this->publish('2026-09-03', 1, 10);
        $this->publish('2026-09-03', 11, 25);

        $row = $this->summary('D', 0)->viewData('rows')->last();

        $this->assertSame(['2026-09-03', 0, 1, 25, 25, 75], $this->line($row));
    }

    public function testTheTableIsTheWholeHistoryOldestDayFirst()
    {
        $this->as('manager');
        $this->receive('2026-09-20', 10);
        $this->receive('2026-08-20', 200);
        $this->publish('2026-08-25', 1, 50);
        $this->receive('2027-01-05', 7);

        $rows = $this->summary('D', 0)->viewData('rows');

        $this->assertSame(['2026-08-20', '2026-08-25', '2026-09-20', '2027-01-05'], $rows->pluck('day')->all());
        $this->assertSame([200, 150, 160, 167], $rows->pluck('balance')->all());
    }

    public function testOtherFuelTypesAndSpecsAreNotMixedIn()
    {
        $this->as('manager');
        $this->receive('2026-09-02', 100);
        $this->receive('2026-09-02', 60, 0, 'A');

        $this->assertSame([100], $this->summary('D', 0)->viewData('rows')->pluck('in')->all());
        $this->assertSame([60], $this->summary('A', 0)->viewData('rows')->pluck('in')->all());
        $this->assertCount(0, $this->summary('A', 1)->viewData('rows'));
        $this->summary('A', 1)->assertSee(__('report.no_data'));
    }

    public function testDeletedRecordsAreNotCountedAndTheTotalsAreShown()
    {
        $this->as('manager');
        $this->receive('2026-09-02', 100);
        $this->receive('2026-09-03', 30);
        $this->publish('2026-09-04', 1, 20);
        TicketInbound::where('quantity', 30)->first()->delete();                      // a wrong record, deleted
        $this->delete('/ticket-publish/' . TicketPublish::first()->id);              // a cancelled batch

        $page = $this->summary('D', 0);
        $rows = $page->viewData('rows');

        $this->assertSame(['2026-09-02'], $rows->pluck('day')->all());
        $this->assertSame(100, $rows[0]->balance);
        $this->assertSame(100, $page->viewData('totalIn'));
        $this->assertSame(0, $page->viewData('totalOut'));
    }

    public function testTheChoiceIsValidated()
    {
        $this->as('manager');

        $this->get('/ticket-summary?fuel_type=D')->assertSessionHasErrors('spec');        // both are needed
        $this->get('/ticket-summary?spec=0')->assertSessionHasErrors('fuel_type');
        $this->get('/ticket-summary?fuel_type=XX&spec=0')->assertSessionHasErrors('fuel_type');
        $this->get('/ticket-summary?fuel_type=D&spec=9')->assertSessionHasErrors('spec');
    }

    public function testTheMenuAndWhoMayLookAtIt()
    {
        $this->as('manager')->get('/ticket-summary')->assertOk()->assertSee('Summary Tickets')->assertSee(route('ticket-summary.index'));
        $this->as('accountant')->get('/ticket-summary')->assertOk();
        $this->as('assistant')->get('/ticket-summary')->assertOk();
        $this->as('cashier')->get('/ticket-summary')->assertForbidden();
    }

    public function testThePublishColumnsAreGroupedUnderOneHeadAndTheOrderIsCountStartEnd()
    {
        $this->as('manager');
        $this->receive('2026-09-02', 100);
        $this->publish('2026-09-03', 11, 30);

        $page = $this->summary('D', 0);
        $page->assertSee('colspan="3"', false)->assertSee('rowspan="2"', false);

        // the head: Date, Inbound over (Count), Publish over (Count, Start, End), Balance
        $html = $page->getContent();
        $this->assertRegExp('/<th[^>]*>\s*Inbound\s*<\/th>\s*<th colspan="3"[^>]*>\s*Publish\s*<\/th>/', $html);
        $this->assertRegExp('/<th[^>]*>\s*Count<\/th>\s*<th[^>]*>\s*Count<\/th>\s*<th[^>]*>\s*Start<\/th>\s*<th[^>]*>\s*End<\/th>/', $html);

        // the cells of a row follow: day, in, count, start, end, balance
        $this->assertRegExp('/<td>2026-09-03<\/td>\s*<td[^>]*><\/td>\s*<td[^>]*>20<\/td>\s*<td[^>]*>11<\/td>\s*<td[^>]*>30<\/td>\s*<td[^>]*><strong>80<\/strong><\/td>/', $html);
    }

    private function sheetOf($response)
    {
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $workbook = $zip->getFromName('xl/workbook.xml');
        $zip->close();

        return [$xml, $workbook];
    }

    public function testTheTableCanBeExportedToExcelWithTheSameHead()
    {
        $this->as('manager');
        $this->receive('2026-09-02', 100);
        $this->publish('2026-09-03', 11, 30);

        $response = $this->get('/ticket-summary?fuel_type=D&spec=0&export=xls')->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('ticket-summary-D-5kg-' . today()->toDateString() . '.xlsx', $response->headers->get('content-disposition'));
        $this->assertSame(\App\Support\Reports\XlsxWriter::MIME, $response->headers->get('content-type'));

        list($xml, $workbook) = $this->sheetOf($response);

        // two head rows: Inbound over Count, the Publish head merged over its three columns, Date and Balance over both rows
        $this->assertStringContainsString('<mergeCell ref="C1:E1"/>', $xml);
        $this->assertStringContainsString('<mergeCell ref="A1:A2"/>', $xml);
        $this->assertStringContainsString('<mergeCell ref="F1:F2"/>', $xml);
        $this->assertStringNotContainsString('<mergeCell ref="B1:B2"/>', $xml);
        $this->assertStringContainsString('ySplit="2"', $xml);
        foreach (['Date', 'Inbound', 'Publish', 'Count', 'Start', 'End', 'Balance', 'Total', '2026-09-02', '2026-09-03'] as $text) {
            $this->assertStringContainsString('>' . $text . '</t>', $xml, $text);
        }
        // the numbers are numbers: day 1: in 100, balance 100; day 2: count 20, start 11, end 30, balance 80; the total row: 100 in, 20 out
        $this->assertRegExp('/<c r="B3"[^>]*><v>100<\/v><\/c>/', $xml);
        $this->assertRegExp('/<c r="F3"[^>]*><v>100<\/v><\/c>/', $xml);
        $this->assertRegExp('/<c r="C4"[^>]*><v>20<\/v><\/c>/', $xml);
        $this->assertRegExp('/<c r="D4"[^>]*><v>11<\/v><\/c>/', $xml);
        $this->assertRegExp('/<c r="E4"[^>]*><v>30<\/v><\/c>/', $xml);
        $this->assertRegExp('/<c r="F4"[^>]*><v>80<\/v><\/c>/', $xml);
        $this->assertRegExp('/<c r="B5"[^>]*><v>100<\/v><\/c>/', $xml);
        $this->assertRegExp('/<c r="C5"[^>]*><v>20<\/v><\/c>/', $xml);
        $this->assertStringContainsString('Diesel 5kg', $workbook);                 // the name of the sheet
    }

    public function testTheExportButtonIsOnTheTableAndNeedsAChoice()
    {
        $this->as('manager');
        $this->receive('2026-09-02', 100);

        $this->get('/ticket-summary')->assertOk()->assertDontSee('export=xls');             // no table, no button
        $this->summary('D', 0)->assertSee('export=xls')->assertSee(__('report.export_xls'));

        // without a choice there is nothing to export: the page is shown
        $this->get('/ticket-summary?export=xls')->assertOk()->assertDontSee('export=xls');
        $this->get('/ticket-summary?fuel_type=D&export=xls')->assertSessionHasErrors('spec');

        // an empty history still gives a file (the head only)
        $response = $this->get('/ticket-summary?fuel_type=A&spec=2&export=xls')->assertOk();
        list($xml) = $this->sheetOf($response);
        $this->assertStringContainsString('<mergeCell ref="C1:E1"/>', $xml);
        $this->assertStringNotContainsString('>Total</t>', $xml);

        // who may export: the same people who may look
        $this->as('accountant')->get('/ticket-summary?fuel_type=D&spec=0&export=xls')->assertOk();
        $this->as('cashier')->get('/ticket-summary?fuel_type=D&spec=0&export=xls')->assertForbidden();
    }

    public function testTheTableHasLinesRoundEveryCellOnThePageAndInTheFile()
    {
        $this->as('manager');
        $this->receive('2026-09-02', 100);

        $this->summary('D', 0)->assertSee('table-bordered');

        $response = $this->get('/ticket-summary?fuel_type=D&spec=0&export=xls')->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $zip->open($file);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $zip->close();

        $this->assertStringContainsString('<left style="thin">', $styles);
        $this->assertStringContainsString('<cellXfs count="6">', $styles);
        // every cell of the two head rows has the head style, the empty cells inside the merged heads too
        foreach (['A1', 'B1', 'C1', 'D1', 'E1', 'F1', 'A2', 'B2', 'C2', 'D2', 'E2', 'F2'] as $cell) {
            $this->assertRegExp('/<c r="' . $cell . '"[^>]* s="3"/', $xml, $cell);
        }
        // the body has the lined styles too, and a cell with nothing in it is drawn as well (no publish that day)
        $this->assertRegExp('/<c r="A3"[^>]* s="4">/', $xml);
        $this->assertRegExp('/<c r="B3" s="4"><v>100<\/v>/', $xml);
        foreach (['C3', 'D3', 'E3'] as $cell) {
            $this->assertRegExp('/<c r="' . $cell . '" s="4"\/>/', $xml, $cell);
        }
        $this->assertRegExp('/<c r="F3" s="4"><v>100<\/v>/', $xml);
    }
}
