<?php

namespace Tests\Feature;

use App\Support\Barcode;
use App\Models\TicketPublish;
use App\TicketStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketPublishTest extends TestCase
{
    use RefreshDatabase;

    private $diesel = 'D'; // config('fuel_types') key; config('ticket_codes.D') = [0=>06,1=>07,2=>02,3=>05]

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

    // spec 0 = 5kg (see config/ticket_specs.php)
    private function receive($quantity, $spec = 0, $fuel = null)
    {
        return $this->post('/ticket-inbound', [
            'fuel_type' => $fuel ?: $this->diesel, 'spec' => $spec, 'quantity' => $quantity, 'inbound_date' => '2026-09-20',
        ]);
    }

    private function publish($startSerial, $endSerial, $spec = 0, $fuel = null)
    {
        return $this->post('/ticket-publish', [
            'stock_item' => ($fuel ?: $this->diesel) . ':' . $spec,
            'start_serial' => $startSerial,
            'end_serial' => $endSerial,
        ]);
    }

    // ---- barcode ----

    public function testBarcodeUsesTheConfiguredSignConstant()
    {
        $b = Barcode::make('D', 0, 42); // spec 0 = 5kg -> code "06" (config/ticket_codes.php)

        $this->assertSame(sprintf('%05d%2s%09d', config('ticket_sign'), '06', 42), $b);
        $this->assertSame(16, strlen($b)); // zero-padded, never truncated by leading zeros
        $this->assertSame(['sign' => config('ticket_sign'), 'code' => '06', 'serial' => 42], Barcode::parse($b));
        $this->assertTrue(Barcode::isReservedCode('21'));
        $this->assertTrue(Barcode::isReservedCode('25'));
        $this->assertFalse(Barcode::isReservedCode('20'));
    }

    // ---- publish ----

    public function testPublishCreatesNumberedTicketsFromStockUsingTheFuelTypesCode()
    {
        $this->as('manager');
        $this->receive(300);
        $this->publish(1, 100)->assertRedirect('/ticket-publish');

        $batch = TicketPublish::first();
        $this->assertSame(1, $batch->start_serial);
        $this->assertSame(100, $batch->end_serial);
        $this->assertSame(100, $batch->count);
        $this->assertSame('06', $batch->code); // config('ticket_codes.D.0')
        $this->assertSame(0, $batch->spec);

        $this->assertSame(100, DB::table('tickets')->count());
        $first = DB::table('tickets')->where('serial', 1)->first();
        $this->assertSame(sprintf('%05d%2s%09d', config('ticket_sign'), '06', 1), $first->barcode);
        $this->assertSame(config('ticket_statuses.publish'), $first->status);
        $this->assertSame(0, (int) $first->spec);
        $this->assertSame($this->diesel, $first->fuel_type);
        $this->assertSame($batch->id, (int) $first->publish_id);
        $this->assertNotNull($first->publish_date);
        $this->assertNull($first->sale_id);
        $this->assertNull($first->supplement_id);

        $this->assertSame(200, TicketStock::available($this->diesel, 0));
    }

    public function testDifferentSpecUsesADifferentConfiguredCode()
    {
        $this->as('manager');
        $this->receive(20, 2); // 15kg
        $this->publish(1, 20, 2)->assertRedirect('/ticket-publish');

        $this->assertSame('02', TicketPublish::first()->code); // config('ticket_codes.D.2')
    }

    public function testSpecWithoutAConfiguredCodeCannotBePublished()
    {
        $this->as('manager');
        // Gasoline ('A') has no code configured for spec 3 (50kg) in config/ticket_codes.php
        // (only 0, 1, 2 are). Receiving stock for it is still allowed; publishing it isn't.
        $this->receive(10, 3, 'A');

        $this->publish(1, 10, 3, 'A')->assertSessionHasErrors('stock_item');
        $this->assertSame(0, DB::table('tickets')->count());
    }

    public function testTwoBatchesOfDifferentSpecsDoNotOverlap()
    {
        $this->as('manager');
        $this->receive(1000, 0);
        $this->receive(1000, 2);
        $this->publish(1, 10, 0);
        $this->publish(1, 3, 2); // different spec -> different code -> no clash even with overlapping serials

        $batches = TicketPublish::orderBy('id')->get();
        $this->assertSame([[1, 10], [1, 3]], $batches->map(function ($b) {
            return [$b->start_serial, $b->end_serial];
        })->all());
        $this->assertSame(13, DB::table('tickets')->count());
        $this->assertSame(13, DB::table('tickets')->distinct()->count('barcode'));
    }

    public function testCannotPublishMoreThanTheBlankStock()
    {
        $this->as('manager');
        $this->receive(10);

        $this->publish(1, 11)->assertSessionHasErrors('end_serial');
        $this->assertSame(0, DB::table('tickets')->count());

        $this->publish(1, 10)->assertSessionHasNoErrors();
        $this->publish(11, 11)->assertSessionHasErrors('end_serial'); // stock is now empty
    }

    public function testEndCannotBeBeforeStart()
    {
        $this->as('manager');
        $this->receive(50);

        $this->publish(10, 1)->assertSessionHasErrors('end_serial');
        $this->assertSame(0, DB::table('tickets')->count());
    }

    public function testOverlappingRangeIsRejected()
    {
        $this->as('manager');
        $this->receive(1000);
        $this->publish(21, 30); // existing batch: 21-30

        // every way a new range can intersect an existing one
        $this->publish(25, 35)->assertSessionHasErrors('start_serial'); // starts inside, extends past
        $this->publish(10, 25)->assertSessionHasErrors('start_serial'); // ends inside, starts before
        $this->publish(15, 40)->assertSessionHasErrors('start_serial'); // fully contains the batch
        $this->publish(23, 27)->assertSessionHasErrors('start_serial'); // fully inside the batch
        $this->publish(21, 30)->assertSessionHasErrors('start_serial'); // exact duplicate
        $this->assertSame(10, DB::table('tickets')->count()); // none of the rejected attempts created anything

        // adjacent, non-overlapping ranges are fine on both sides
        $this->publish(1, 20)->assertSessionHasNoErrors();
        $this->publish(31, 40)->assertSessionHasNoErrors();
        $this->assertSame(40, DB::table('tickets')->count());
    }

    public function testOverlapCheckIsScopedToTheSameFuelTypeAndSpec()
    {
        $this->as('manager');
        $other = 'A'; // Gasoline
        $this->receive(50, 0);              // Diesel, 5kg
        $this->receive(50, 0, $other);      // Gasoline, 5kg - different code, independent numbering
        $this->publish(1, 10, 0);           // Diesel 1-10

        // same serials, different fuel type: no conflict
        $this->publish(1, 10, 0, $other)->assertSessionHasNoErrors();
        $this->assertSame(20, DB::table('tickets')->count());
    }

    public function testBatchCannotExceedTheSizeCap()
    {
        $this->as('manager');
        $this->receive(60000);

        $this->publish(1, 50001)->assertSessionHasErrors('end_serial');
        $this->assertSame(0, DB::table('tickets')->count());
    }

    // ---- permissions ----

    public function testPermissions()
    {
        $this->as('assistant');
        $this->receive(5);
        $this->get('/ticket-publish')->assertOk();
        $this->publish(1, 5)->assertRedirect('/ticket-publish'); // assistant can publish
        $this->assertSame(5, DB::table('tickets')->count());

        $this->as('accountant')->get('/ticket-publish')->assertOk(); // view only
        $this->get('/ticket-publish/create')->assertForbidden();
        $this->publish(6, 6)->assertForbidden();

        $this->as('cashier')->get('/ticket-publish')->assertForbidden();
        $this->assertSame(5, DB::table('tickets')->count());
    }

    public function testScreensRender()
    {
        $this->as('manager');
        $this->receive(10);
        $this->publish(1, 4);

        // the list shows the published batches; the blank ticket stock is on the publish form
        $this->get('/ticket-publish')->assertOk()->assertSee('Diesel')->assertSee(__('tickets.recent_batches'))->assertSee(__('report.kg'))
            ->assertDontSee(__('tickets.blank_stock'));
        $this->get('/ticket-publish/create')->assertOk()->assertSee('Diesel')->assertSee(__('tickets.blank_stock'));
    }

    public function testCreateFormSuggestsTheNextFreeSerialPerStockItem()
    {
        $this->as('manager');
        $this->receive(20, 0); // 5kg, code 06
        $this->receive(20, 2); // 15kg, code 02

        // fresh stock: suggested start is 1 for both
        $html = $this->get('/ticket-publish/create')->assertOk()->getContent();
        $this->assertStringContainsString('value="' . $this->diesel . ':0" data-next="1"', $html);
        $this->assertStringContainsString('value="' . $this->diesel . ':2" data-next="1"', $html);

        // after publishing 1-10 of the 5kg spec, its suggestion continues at 11; the 15kg one is untouched
        $this->publish(1, 10, 0);
        $html = $this->get('/ticket-publish/create')->assertOk()->getContent();
        $this->assertStringContainsString('value="' . $this->diesel . ':0" data-next="11"', $html);
        $this->assertStringContainsString('value="' . $this->diesel . ':2" data-next="1"', $html);
    }

    public function testTheBusinessSignOfTheConfigIs86761()
    {
        // the tests run with TICKET_SIGN=00000 (phpunit.xml); the value in the config file is the real one of the business
        $this->assertStringContainsString("env('TICKET_SIGN', '86761')", file_get_contents(config_path('ticket_sign.php')));
    }

    // ---- actions on a batch ----

    private function batch()
    {
        return \App\Models\TicketPublish::orderBy('id')->first();
    }

    public function testViewTicketsListsTheTicketsOfTheBatch()
    {
        $this->as('manager');
        $this->receive(20);
        $this->publish(1, 5);
        $batch = $this->batch();

        $page = $this->get('/ticket-publish/' . $batch->id)->assertOk();
        $page->assertSee(Barcode::make('D', 0, 1))->assertSee(Barcode::make('D', 0, 5));
        $this->assertSame(5, $page->viewData('tickets')->total());
        $page->assertSee('Published: 5');

        $this->as('accountant')->get('/ticket-publish/' . $batch->id)->assertOk(); // view only
        $this->get('/ticket-publish/999999')->assertNotFound();
    }

    public function testEditGivesAnUntouchedBatchAnotherRangeAndNumbersItsTicketsAgain()
    {
        $this->as('manager');
        $this->receive(20);
        $this->publish(1, 5);
        $batch = $this->batch();

        $this->get('/ticket-publish/' . $batch->id . '/edit')->assertOk();
        $this->put('/ticket-publish/' . $batch->id, ['start_serial' => 101, 'end_serial' => 108])->assertRedirect('/ticket-publish');

        $batch = $batch->fresh();
        $this->assertSame([101, 108, 8], [(int) $batch->start_serial, (int) $batch->end_serial, (int) $batch->count]);
        $this->assertSame(8, DB::table('tickets')->where('publish_id', $batch->id)->count());
        $this->assertSame(8, DB::table('tickets')->count());
        $this->assertNull(DB::table('tickets')->where('barcode', Barcode::make('D', 0, 3))->first()); // the old numbers are gone
        $this->assertNotNull(DB::table('tickets')->where('barcode', Barcode::make('D', 0, 105))->first());
        $this->assertSame(12, \App\TicketStock::available('D', 0)); // 20 received, 8 published

        // a range that overlaps the old one of the same batch is fine
        $this->put('/ticket-publish/' . $batch->id, ['start_serial' => 105, 'end_serial' => 110])->assertRedirect('/ticket-publish');
        $this->assertSame(6, DB::table('tickets')->count());
    }

    public function testEditIsValidatedAndNeverTakesMoreThanTheStockOrTheNumbersOfAnotherBatch()
    {
        $this->as('manager');
        $this->receive(10);
        $this->publish(1, 5);
        $this->publish(11, 13);
        $batch = $this->batch();

        $this->put('/ticket-publish/' . $batch->id, ['start_serial' => 9, 'end_serial' => 8])->assertSessionHasErrors('end_serial');   // backwards
        $this->put('/ticket-publish/' . $batch->id, ['start_serial' => 1, 'end_serial' => 8])->assertSessionHasErrors('end_serial');   // 8 > 2 left + 5
        $this->put('/ticket-publish/' . $batch->id, ['start_serial' => 10, 'end_serial' => 12])->assertSessionHasErrors('start_serial'); // overlaps 11-13
        $this->put('/ticket-publish/' . $batch->id, ['start_serial' => 'x', 'end_serial' => 8])->assertSessionHasErrors('start_serial');
        $this->assertSame(5, (int) $batch->fresh()->count);
        $this->assertSame(8, DB::table('tickets')->count());
    }

    public function testDeleteGivesTheBlankTicketsBackAndKeepsTheNumbersReserved()
    {
        $this->as('manager');
        $this->receive(20);
        $this->publish(1, 5);
        $batch = $this->batch();
        $this->assertSame(15, \App\TicketStock::available('D', 0));

        $this->delete('/ticket-publish/' . $batch->id)->assertRedirect('/ticket-publish');

        $this->assertNull(\App\Models\TicketPublish::find($batch->id));
        $this->assertNotNull(\App\Models\TicketPublish::onlyTrashed()->find($batch->id)); // kept as deleted
        $this->assertSame(0, DB::table('tickets')->count());
        $this->assertSame(20, \App\TicketStock::available('D', 0));

        // its numbers stay reserved: nothing publishes over them, and the suggested start moves on
        $this->publish(3, 8)->assertSessionHasErrors('start_serial');
        $this->publish(6, 8)->assertRedirect('/ticket-publish');
        $suggest = $this->get('/ticket-publish/create')->assertOk()->viewData('nextSerials');
        $this->assertSame(9, $suggest['D:0']);
    }

    public function testATouchedBatchCannotBeChangedOrDeleted()
    {
        $this->as('manager');
        $this->receive(20);
        $this->publish(1, 5);
        $batch = $this->batch();
        $range = ['start_serial' => 21, 'end_serial' => 25];

        // downloaded by a terminal
        DB::table('tickets')->where('publish_id', $batch->id)->where('serial', 2)->update(['synced' => 1]);
        $this->delete('/ticket-publish/' . $batch->id)->assertSessionHasErrors('batch');
        $this->put('/ticket-publish/' . $batch->id, $range)->assertSessionHasErrors('start_serial');
        $this->get('/ticket-publish/' . $batch->id . '/edit')->assertRedirect('/ticket-publish');
        $this->assertSame(5, DB::table('tickets')->where('publish_id', $batch->id)->count());

        // sold
        DB::table('tickets')->where('publish_id', $batch->id)->update(['synced' => 0]);
        DB::table('tickets')->where('publish_id', $batch->id)->where('serial', 3)->update(['status' => config('ticket_statuses.sale')]);
        $this->delete('/ticket-publish/' . $batch->id)->assertSessionHasErrors('batch');
        $this->put('/ticket-publish/' . $batch->id, $range)->assertSessionHasErrors('start_serial');

        // a ticket gone from the batch (used and removed by a backup)
        DB::table('tickets')->where('publish_id', $batch->id)->where('serial', 3)->update(['status' => config('ticket_statuses.publish')]);
        DB::table('tickets')->where('publish_id', $batch->id)->where('serial', 4)->delete();
        $this->delete('/ticket-publish/' . $batch->id)->assertSessionHasErrors('batch');

        $this->assertNotNull(\App\Models\TicketPublish::find($batch->id));
    }

    public function testBatchActionsNeedTheStockPermission()
    {
        $this->as('manager');
        $this->receive(20);
        $this->publish(1, 5);
        $batch = $this->batch();

        $page = $this->as('accountant')->get('/ticket-publish')->assertOk();
        $page->assertSee(route('ticket-publish.show', $batch->id))->assertDontSee(route('ticket-publish.edit', $batch->id));
        $this->get('/ticket-publish/' . $batch->id . '/edit')->assertForbidden();
        $this->put('/ticket-publish/' . $batch->id, ['start_serial' => 21, 'end_serial' => 25])->assertForbidden();
        $this->delete('/ticket-publish/' . $batch->id)->assertForbidden();

        $this->as('assistant')->get('/ticket-publish')->assertOk()->assertSee(route('ticket-publish.edit', $batch->id));
        $this->as('cashier')->get('/ticket-publish/' . $batch->id)->assertForbidden();
        $this->assertNotNull(\App\Models\TicketPublish::find($batch->id));
    }

    public function testABatchHasTheDateItWasPublishedAndTheListAndThePageShowIt()
    {
        $this->as('manager');
        $this->receive(20);
        $this->publish(1, 5);
        $batch = $this->batch();

        $this->assertSame(today()->toDateString(), substr((string) $batch->date, 0, 10)); // set when it is published

        $batch->forceFill(['date' => '2026-09-21'])->save();
        $this->get('/ticket-publish')->assertOk()->assertSee(__('report.date'))->assertSee('2026-09-21');
        $this->get('/ticket-publish/' . $batch->id)->assertOk()->assertSee('2026-09-21');

        // changing the range later keeps the date of the batch
        $this->put('/ticket-publish/' . $batch->id, ['start_serial' => 11, 'end_serial' => 15])->assertRedirect('/ticket-publish');
        $this->assertSame('2026-09-21', substr((string) $batch->fresh()->date, 0, 10));
    }

    public function testThePublishFormHasADateAndTheBatchKeepsIt()
    {
        $this->as('manager');
        $this->receive(30);

        $this->get('/ticket-publish/create')->assertOk()->assertSee('name="date"', false)->assertSee(date('Y-m-d'));

        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 1, 'end_serial' => 5, 'date' => '2026-09-18'])->assertRedirect('/ticket-publish');
        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 6, 'end_serial' => 8])->assertRedirect('/ticket-publish'); // no date: today
        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 9, 'end_serial' => 9, 'date' => 'nope'])->assertSessionHasErrors('date');

        $batches = \App\Models\TicketPublish::orderBy('id')->get();
        $this->assertCount(2, $batches);
        $this->assertSame('2026-09-18', substr((string) $batches[0]->date, 0, 10));
        $this->assertSame(today()->toDateString(), substr((string) $batches[1]->date, 0, 10));

        $this->get('/ticket-publish'); // the old input of the refused form is shown on the next page only

        // the tickets carry the date of their batch as their publish date (the ticket lookup shows it)
        $this->assertSame('2026-09-18', substr((string) DB::table('tickets')->where('publish_id', $batches[0]->id)->value('publish_date'), 0, 10));
        $this->assertSame(today()->toDateString(), substr((string) DB::table('tickets')->where('publish_id', $batches[1]->id)->value('publish_date'), 0, 10));
        $this->get('/tickets/lookup?barcode=' . Barcode::make('D', 0, 2))->assertOk()->assertSee('2026-09-18');

        // the batch can be given another date (and keeps it when the date is not sent)
        $this->get('/ticket-publish/' . $batches[0]->id . '/edit')->assertOk()->assertSee('2026-09-18');
        $this->put('/ticket-publish/' . $batches[0]->id, ['start_serial' => 1, 'end_serial' => 5, 'date' => '2026-09-19'])->assertRedirect('/ticket-publish');
        $this->assertSame('2026-09-19', substr((string) $batches[0]->fresh()->date, 0, 10));
        $this->assertSame('2026-09-19', substr((string) DB::table('tickets')->where('publish_id', $batches[0]->id)->value('publish_date'), 0, 10));
        $this->put('/ticket-publish/' . $batches[0]->id, ['start_serial' => 1, 'end_serial' => 5])->assertRedirect('/ticket-publish');
        $this->assertSame('2026-09-19', substr((string) $batches[0]->fresh()->date, 0, 10));
        $this->put('/ticket-publish/' . $batches[0]->id, ['start_serial' => 1, 'end_serial' => 5, 'date' => 'nope'])->assertSessionHasErrors('date');
    }
}
