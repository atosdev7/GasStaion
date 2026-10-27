<?php

namespace Tests\Feature;

use App\Support\Tickets\Barcode;
use App\Models\Tickets\TicketPublish;
use App\Support\Tickets\TicketStock;
use App\Models\Admin\User;
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

    // the code of a ticket a POS issues: the first digit is the fuel type (1 gasoline, 2 diesel), the second the kind (1 spot sale, 2 self use, 3 other use,
    // 4 ticket loan, 5 card, 9 remainder); the codes of the press (01 to 08) are never one of them
    public function testThePosCodeIsTheFuelTypeAndTheKindOfTheTicket()
    {
        $expected = [
            'A' => ['direct' => '11', 'self' => '12', 'other' => '13', 'ticket_loan' => '14', 'card' => '15', 'remainder' => '19'],
            'D' => ['direct' => '21', 'self' => '22', 'other' => '23', 'ticket_loan' => '24', 'card' => '25', 'remainder' => '29'],
        ];
        foreach ($expected as $fuel => $kinds) {
            foreach ($kinds as $kind => $code) {
                $this->assertSame($code, Barcode::posCode($kind, $fuel));
                $this->assertTrue(Barcode::isReservedCode($code));
                $this->assertSame($kind, Barcode::posType($code));
                $this->assertSame($fuel, Barcode::posFuel($code));
            }
        }
        $this->assertCount(12, Barcode::posCodes());
        $this->assertNull(Barcode::posCode('direct', 'X'));              // an unknown fuel type
        $this->assertNull(Barcode::posCode('nonsense', 'D'));            // an unknown kind

        foreach (['01', '08', '10', '16', '18', '20', '26', '28', '30', '99', '2', '211'] as $notPos) {
            $this->assertFalse(Barcode::isReservedCode($notPos), $notPos);
            $this->assertNull(Barcode::posType($notPos), $notPos);
        }
        foreach (config('ticket_codes') as $bySpec) {
            foreach ($bySpec as $pressCode) {
                $this->assertFalse(Barcode::isReservedCode($pressCode), $pressCode);
            }
        }
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
        // a (fuel type, spec) with no code configured in config/ticket_codes.php (gasoline 50kg had none before it got 08): receiving stock for it is
        // still allowed; publishing it isn't.
        config(['ticket_codes.A.3' => null]);
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

        $this->as('accountant')->get('/ticket-publish')->assertOk(); // the accountant publishes like the assistant
        $this->get('/ticket-publish/create')->assertOk();
        $this->receive(1);
        $this->publish(6, 6)->assertRedirect('/ticket-publish');

        $this->as('cashier')->get('/ticket-publish')->assertForbidden();
        $this->get('/ticket-publish/create')->assertForbidden();
        $this->publish(7, 7)->assertForbidden();
        $this->assertSame(6, DB::table('tickets')->count());
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
        return \App\Models\Tickets\TicketPublish::orderBy('id')->first();
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

    }

    public function testThePublishFormHasADateAndTheBatchKeepsIt()
    {
        $this->as('manager');
        $this->receive(30);

        $this->get('/ticket-publish/create')->assertOk()->assertSee('name="date"', false)->assertSee(date('Y-m-d'));

        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 1, 'end_serial' => 5, 'date' => '2026-09-18'])->assertRedirect('/ticket-publish');
        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 6, 'end_serial' => 8])->assertRedirect('/ticket-publish'); // no date: today
        $this->post('/ticket-publish', ['stock_item' => 'D:0', 'start_serial' => 9, 'end_serial' => 9, 'date' => 'nope'])->assertSessionHasErrors('date');

        $batches = \App\Models\Tickets\TicketPublish::orderBy('id')->get();
        $this->assertCount(2, $batches);
        $this->assertSame('2026-09-18', substr((string) $batches[0]->date, 0, 10));
        $this->assertSame(today()->toDateString(), substr((string) $batches[1]->date, 0, 10));

        $this->get('/ticket-publish'); // the old input of the refused form is shown on the next page only

        // the tickets carry the date of their batch as their publish date (the ticket lookup shows it)
        $this->assertSame('2026-09-18', substr((string) DB::table('tickets')->where('publish_id', $batches[0]->id)->value('publish_date'), 0, 10));
        $this->assertSame(today()->toDateString(), substr((string) DB::table('tickets')->where('publish_id', $batches[1]->id)->value('publish_date'), 0, 10));
        $this->get('/tickets/lookup?barcode=' . Barcode::make('D', 0, 2))->assertOk()->assertSee('2026-09-18');

    }

    public function testTheBatchTableFiltersByDateFuelTypeAndSpec()
    {
        $this->as('manager');
        $this->receive(100, 0);
        $this->receive(100, 2);
        $this->receive(100, 0, 'A');
        $this->post('/ticket-publish', ['stock_item' => $this->diesel . ':0', 'start_serial' => 1, 'end_serial' => 10, 'date' => '2026-10-01']);
        $this->post('/ticket-publish', ['stock_item' => $this->diesel . ':2', 'start_serial' => 1, 'end_serial' => 20, 'date' => '2026-10-02']);
        $this->post('/ticket-publish', ['stock_item' => 'A:0', 'start_serial' => 1, 'end_serial' => 30, 'date' => '2026-10-02']);
        $counts = function ($query) {
            return $this->get('/ticket-publish' . $query)->assertOk()->viewData('batches')->pluck('count')->map(function ($n) {
                return (int) $n;
            })->all();
        };

        $this->assertSame([30, 20, 10], $counts(''));                                // everything, the last batch first
        $this->assertSame([10], $counts('?date=2026-10-01'));                        // the day of the batch
        $this->assertSame([30, 20], $counts('?date=2026-10-02'));
        $this->assertSame([20, 10], $counts('?fuel_type=' . $this->diesel));         // the fuel type
        $this->assertSame([30], $counts('?fuel_type=A'));
        $this->assertSame([30, 10], $counts('?spec=0'));                             // the spec (spec 0 is 5 kg: a value, not "no filter")
        $this->assertSame([20], $counts('?spec=2'));
        $this->assertSame([], $counts('?spec=3'));
        $this->assertSame([10], $counts('?fuel_type=' . $this->diesel . '&spec=0'));  // together
        $this->assertSame([20], $counts('?date=2026-10-02&fuel_type=' . $this->diesel . '&spec=2'));

        $this->get('/ticket-publish?date=nope')->assertSessionHasErrors('date');
        $this->get('/ticket-publish?fuel_type=Z')->assertSessionHasErrors('fuel_type');
        $this->get('/ticket-publish?spec=9')->assertSessionHasErrors('spec');

        // the filters are in the head of the table and keep what was chosen; those who may not publish see them too
        $html = $this->get('/ticket-publish?date=2026-10-02&fuel_type=' . $this->diesel . '&spec=2')->assertOk()->getContent();
        $this->assertStringContainsString('id="ticket-publish-filters"', $html);
        $this->assertStringContainsString('name="date" form="ticket-publish-filters" type="date" value="2026-10-02"', $html);
        $this->assertRegExp('/<option value="' . $this->diesel . '" selected>/', $html);
        $this->assertRegExp('/<option value="2" selected>15 kg<\/option>/', $html);
        $this->assertStringContainsString('All fuel types', $html);
        $this->assertStringContainsString('All specs', $html);
        $this->as('accountant')->get('/ticket-publish?spec=2')->assertOk()->assertSee('ticket-publish-filters');
        $this->get('/ticket-publish?spec=3')->assertOk()->assertSee('No data');
    }

    public function testTheBatchTableIsPagedByFiftyAndKeepsTheFilters()
    {
        $this->as('manager');
        for ($i = 1; $i <= 52; $i++) {
            \App\Models\Tickets\TicketPublish::forceCreate([
                'fuel_type' => $this->diesel, 'spec' => 0, 'code' => '06', 'start_serial' => $i * 10, 'end_serial' => $i * 10 + 9, 'count' => 10,
                'date' => '2026-09-20', 'user_id' => 1,
            ]);
        }

        $first = $this->get('/ticket-publish?fuel_type=' . $this->diesel)->assertOk();
        $this->assertCount(50, $first->viewData('batches'));
        $this->assertSame(52, $first->viewData('batches')->total());
        $this->assertStringContainsString('fuel_type=' . $this->diesel . '&amp;page=2', $first->getContent());
        $this->assertCount(2, $this->get('/ticket-publish?fuel_type=' . $this->diesel . '&page=2')->viewData('batches'));
        $this->assertCount(0, $this->get('/ticket-publish?fuel_type=A')->viewData('batches'));
    }
    // ---- publishing cannot be reversed ----

    public function testABatchCanBeLookedAtButNeverChangedOrDeleted()
    {
        $this->as('manager');
        $this->receive(20);
        $this->publish(1, 5);
        $batch = $this->batch();

        $page = $this->get('/ticket-publish')->assertOk();
        $page->assertSee(route('ticket-publish.show', $batch->id));
        $this->assertStringNotContainsString('/ticket-publish/' . $batch->id . '/edit', $page->getContent());
        $this->assertStringNotContainsString('>Delete</button>', $page->getContent());

        // there are no routes for it, for any role
        foreach (['admin', 'manager', 'accountant', 'assistant'] as $role) {
            $this->as($role);
            $this->get('/ticket-publish/' . $batch->id . '/edit')->assertStatus(404);
            $this->put('/ticket-publish/' . $batch->id, ['start_serial' => 21, 'end_serial' => 25])->assertStatus(405); // (the address only has the view)
            $this->delete('/ticket-publish/' . $batch->id)->assertStatus(405);
        }
        $this->assertSame(5, DB::table('tickets')->where('publish_id', $batch->id)->count());
        $this->assertSame(1, (int) $batch->fresh()->start_serial);
        $this->as('cashier')->get('/ticket-publish/' . $batch->id)->assertForbidden();
    }

    public function testThePublishButtonAsksFirstAndSaysThatItCannotBeReversed()
    {
        $this->as('assistant');
        $this->receive(20);

        $html = $this->get('/ticket-publish/create')->assertOk()->getContent();
        $this->assertStringContainsString('id="publish-form"', $html);
        $this->assertStringContainsString('window.confirm(text)', $html);                                  // the question comes before the form is sent
        $this->assertStringContainsString('A published batch cannot be reversed', $html);                  // and says so
        $this->assertStringContainsString('Publish :count tickets (:item), serial :start to :end?', str_replace('\\u003A', ':', $html));
    }

    public function testGasoline50KgIsPublishedWithItsOwnCode()
    {
        $this->as('manager');
        $this->receive(10, 3, 'A');

        $this->publish(1, 10, 3, 'A')->assertRedirect('/ticket-publish')->assertSessionHasNoErrors();

        $batch = \App\Models\Tickets\TicketPublish::first();
        $this->assertSame('08', $batch->code);
        $this->assertSame(Barcode::make('A', 3, 1), DB::table('tickets')->where('publish_id', $batch->id)->orderBy('serial')->value('barcode'));
        $this->assertSame('08', substr(Barcode::make('A', 3, 1), 5, 2));
    }

    // ---- confirmed with the daily confirmation ----

    public function testABatchIsConfirmedWithTheDailyConfirmationAndOnlyAManagerTakesItBack()
    {
        $this->as('accountant');
        $this->receive(20);
        $this->publish(1, 5);
        $batch = $this->batch();
        $date = substr((string) $batch->date, 0, 10);
        $this->get('/ticket-publish')->assertSee('Not confirmed');

        $this->post('/confirmations', ['date' => $date, 'from' => 'tickets', 'ticket_publishes' => [$batch->id]])->assertRedirect();
        $this->assertTrue($batch->fresh()->isConfirmed());
        $this->assertSame($this->actingUserId(), (int) $batch->fresh()->confirmed_by);
        $this->get('/ticket-publish')->assertSee('Confirmed');

        $this->post('/ticket-publish/' . $batch->id . '/unconfirm')->assertForbidden();
        $this->as('manager')->post('/ticket-publish/' . $batch->id . '/unconfirm')->assertRedirect();
        $this->assertFalse($batch->fresh()->isConfirmed());
    }

    private function actingUserId()
    {
        return (int) auth()->id();
    }

    public function testTheListLinksToTheDailyConfirmationOfTheDayForThoseWhoConfirm()
    {
        $this->as('assistant');
        $this->receive(20);
        $this->publish(1, 5);
        $batch = $this->batch();
        $link = route('confirmations.tickets', ['date' => substr((string) $batch->date, 0, 10)]);

        $this->get('/ticket-publish')->assertOk()->assertDontSee($link);                  // the assistant cannot confirm: a plain badge
        $this->as('accountant')->get('/ticket-publish')->assertOk()->assertSee($link);
        $this->as('manager')->get('/ticket-publish')->assertOk()->assertSee($link);
    }
}
