<?php

namespace Tests\Feature;

use App\Models\Cards\Card;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Admin\User;
use App\Support\Cards\CardPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Editing a sale (its date, comment and receipt), the actions of the daily confirmation and taking a confirmation back.
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class SaleEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sales.backdate_days' => 100000]); // (the limit is tested in SaleTest; the dates here are fixed ones)
    }

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
            'sale_type' => 'spot', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 10, 'payment_method' => 'cash', 'total_amount' => 20,
            'paid_amount' => 20, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => $date, 'user_id' => 1,
        ]);
    }

    private function supplement($date, $extra = [])
    {
        return Supplement::forceCreate($extra + [
            'fuel_type' => 'D', 'nozzle_id' => 1, 'out_amount_lt' => 10, 'out_amount_kg' => 9, 'preset_kg' => 10,
            'supplied_at' => $date . ' 11:00:00', 'supply_date' => $date,
        ]);
    }

    private function cardSale($date, $extra = [])
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));

        return $this->sale($date, $extra + ['sale_type' => 'card', 'card_id' => $card->id]);
    }

    private function confirmed()
    {
        return ['confirmed_at' => now(), 'confirmed_by' => 1];
    }

    public function testTheDateCommentAndReceiptOfASaleAreEdited()
    {
        $sale = $this->sale('2026-10-01', ['comment' => 'wrong', 'receipt' => false]);

        $this->as('accountant')->get('/ticket-sales/' . $sale->id . '/edit')->assertOk()->assertSee('wrong')->assertSee('2026-10-01');
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-09-30', 'comment' => 'paid by card', 'receipt' => '1', 'back' => url('/spot-sales')])
            ->assertRedirect(url('/spot-sales'));

        $sale = $sale->fresh();
        $this->assertSame('2026-09-30', substr((string) $sale->sale_date, 0, 10));
        $this->assertSame('paid by card', $sale->comment);
        $this->assertTrue($sale->receipt);
        // nothing that moves fuel or money changes
        $this->assertEquals(10, $sale->amount_kg);
        $this->assertEquals(2, $sale->price);
        $this->assertEquals(20, $sale->total_amount);
        $this->assertSame('spot', $sale->sale_type);

        // the comment can be emptied and the receipt cleared
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-09-30', 'comment' => '', 'receipt' => '0'])->assertRedirect();
        $this->assertNull($sale->fresh()->comment);
        $this->assertFalse($sale->fresh()->receipt);
    }

    public function testTheTicketsAndTheLoanOfASaleFollowItsNewDate()
    {
        $sale = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'usage' => 'ticket_loan', 'customer_id' => 1]);
        $ticket = Ticket::forceCreate([
            'barcode' => '0000006000000001', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 1, 'status' => 1,
            'sale_id' => $sale->id, 'sale_date' => '2026-10-01 14:35:10',
        ]);
        $loan = TicketLoan::forceCreate([
            'loaned_at' => '2026-10-01 14:35:10', 'date' => '2026-10-01', 'customer_id' => 1, 'fuel_type' => 'D', 'amount_kg' => 10,
            'sale_id' => $sale->id, 'user_id' => 1,
        ]);
        $unrelated = Ticket::forceCreate([
            'barcode' => '0000006000000002', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 2, 'status' => 1,
            'sale_id' => 'someone-else', 'sale_date' => '2026-10-01 09:00:00',
        ]);

        $this->as('manager')->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-09-29', 'comment' => ''])->assertRedirect();

        $this->assertSame('2026-09-29 14:35:10', (string) Ticket::find($ticket->barcode)->sale_date);   // the same time of day, on the new day
        $this->assertSame('2026-09-29', substr((string) $loan->fresh()->date, 0, 10));
        $this->assertSame('2026-09-29 14:35:10', (string) $loan->fresh()->loaned_at);
        $this->assertSame('2026-10-01 09:00:00', (string) Ticket::find($unrelated->barcode)->sale_date);

        // an unchanged date leaves them alone
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-09-29', 'comment' => 'x'])->assertRedirect();
        $this->assertSame('2026-09-29 14:35:10', (string) Ticket::find($ticket->barcode)->sale_date);
    }

    // "choose the cost during create or update": the cost of a sale is one of the costs of the fuel in the tanks (and the one the sale has), for a user who may see costs
    public function testTheCostOfASaleIsChosenFromTheCostsOfTheFuelInTheTanks()
    {
        $tank = \App\Models\Fuel\Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        \App\Models\Fuel\TankBalance::create(['tank_id' => $tank->id, 'balance' => 400, 'cost' => 6, 'balance_date' => '2026-10-01']);
        \App\Models\Fuel\FuelInbound::forceCreate(['tank_id' => $tank->id, 'fuel_type' => 'D', 'amount_kg' => 100, 'cost' => 9, 'inbound_date' => '2026-10-05', 'user_id' => 1]);
        \App\Support\Fuel\DefaultCost::set('D', 9);
        $sale = $this->sale('2026-10-01', ['cost' => 5.25]);                         // a cost that is not in the tanks any more

        $page = $this->as('accountant')->get('/ticket-sales/' . $sale->id . '/edit')->assertOk();
        $this->assertSame([9.0, 6.0, 5.25], array_column($page->viewData('costChoices'), 'cost'));
        $html = $page->getContent();
        $this->assertStringContainsString('<select id="f_cost" name="cost"', $html);
        $this->assertRegExp('/<option value="5.25" selected>/', $html);                // the cost the sale has is the chosen one

        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'cost' => '6'])->assertSessionHasNoErrors();
        $this->assertEquals(6, $sale->fresh()->cost);
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'cost' => '', 'comment' => 'x'])->assertSessionHasNoErrors();
        $this->assertEquals(6, $sale->fresh()->cost);                                  // no cost sent: it stays
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'cost' => '-1'])->assertSessionHasErrors('cost');

        // a user who may not see costs has no cost field, and what is sent is not taken
        $ticketSale = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'cost' => 7]);
        $html = $this->as('assistant')->get('/ticket-sales/' . $ticketSale->id . '/edit')->assertOk()->getContent();
        $this->assertStringNotContainsString('name="cost"', $html);
        $this->put('/ticket-sales/' . $ticketSale->id, ['sale_date' => '2026-10-01', 'cost' => '1'])->assertSessionHasNoErrors();
        $this->assertEquals(7, $ticketSale->fresh()->cost);

        // a top-up of a card has no cost, and a return keeps the cost of its sale
        $topUp = $this->cardSale('2026-10-01');
        $this->as('manager')->get('/ticket-sales/' . $topUp->id . '/edit')->assertOk()->assertDontSee('name="cost"', false);
        $return = $this->sale('2026-10-02', ['cost' => 5, 'reverses_sale_id' => $sale->id]);
        $this->get('/ticket-sales/' . $return->id . '/edit')->assertOk()->assertDontSee('name="cost"', false);
    }

    public function testTheEditIsValidated()
    {
        $sale = $this->sale('2026-10-01');
        $this->as('manager');

        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '', 'comment' => ''])->assertSessionHasErrors('sale_date');
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => 'yesterday', 'comment' => ''])->assertSessionHasErrors('sale_date');
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => today()->addDay()->toDateString(), 'comment' => ''])->assertSessionHasErrors('sale_date');
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'comment' => str_repeat('x', 256)])->assertSessionHasErrors('comment');
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'receipt' => 'maybe'])->assertSessionHasErrors('receipt');
        $this->assertSame('2026-10-01', substr((string) $sale->fresh()->sale_date, 0, 10));
    }

    public function testASaleThatIsConfirmedCannotBeEditedUntilAManagerTakesTheConfirmationBack()
    {
        $sale = $this->sale('2026-10-01', ['comment' => 'old'] + $this->confirmed());

        $this->as('accountant')->get('/ticket-sales/' . $sale->id . '/edit')->assertRedirect()->assertSessionHasErrors('sale');
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-09-30', 'comment' => 'new'])->assertRedirect()->assertSessionHasErrors('sale');
        $this->assertSame('old', $sale->fresh()->comment);
        $this->assertSame('2026-10-01', substr((string) $sale->fresh()->sale_date, 0, 10));

        // only the manager and the accountant take a confirmation back
        $this->as('assistant')->post('/ticket-sales/' . $sale->id . '/unconfirm')->assertForbidden();
        $this->assertNotNull($sale->fresh()->confirmed_at);
        $this->as('manager')->post('/ticket-sales/' . $sale->id . '/unconfirm')->assertRedirect()->assertSessionHas('status');
        $this->assertNull($sale->fresh()->confirmed_at);
        $this->assertNull($sale->fresh()->confirmed_by);

        // now it can be edited, and an unconfirmed record cannot be unconfirmed again
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'comment' => 'new'])->assertRedirect();
        $this->assertSame('new', $sale->fresh()->comment);
        $this->post('/ticket-sales/' . $sale->id . '/unconfirm')->assertSessionHasErrors('record');
    }

    public function testASupplementConfirmationIsTakenBackByTheManagerAndTheAccountantOnly()
    {
        $supplement = $this->supplement('2026-10-01', $this->confirmed());

        foreach (['assistant', 'cashier'] as $role) {
            $this->as($role)->post('/supplements/' . $supplement->id . '/unconfirm')->assertForbidden();
        }
        $this->assertNotNull($supplement->fresh()->confirmed_at);
        $this->as('manager')->post('/supplements/' . $supplement->id . '/unconfirm')->assertRedirect();
        $this->assertNull($supplement->fresh()->confirmed_at);
        $this->post('/supplements/' . $supplement->id . '/unconfirm')->assertSessionHasErrors('record');
    }

    public function testWhoMayEditWhichSale()
    {
        $spot = $this->sale('2026-10-01');
        $card = $this->cardSale('2026-10-01');

        // admin, manager and accountant edit every kind of sale
        foreach (['admin', 'manager', 'accountant'] as $role) {
            $this->as($role)->get('/ticket-sales/' . $spot->id . '/edit')->assertOk();
            $this->as($role)->get('/ticket-sales/' . $card->id . '/edit')->assertOk();
        }
        // the assistant works on every ticket sale (his own and the ones of the others), not on a spot sale or a top-up
        $other = $this->sale('2026-10-01', ['sale_type' => 'ticket']);
        $this->as('assistant');
        $own = $this->sale('2026-10-01', ['sale_type' => 'ticket', 'user_id' => auth()->id()]);
        foreach ([$own, $other] as $sale) {
            $this->get('/ticket-sales/' . $sale->id . '/edit')->assertOk();
            $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'comment' => 'ticket'])->assertRedirect();
            $this->assertSame('ticket', $sale->fresh()->comment);
        }
        foreach ([$spot, $card] as $sale) {
            $this->get('/ticket-sales/' . $sale->id . '/edit')->assertForbidden();
            $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'comment' => 'x'])->assertForbidden();
            $this->assertNull($sale->fresh()->comment);
        }
        $this->as('cashier')->get('/ticket-sales/' . $spot->id . '/edit')->assertForbidden();
        $this->put('/ticket-sales/' . $spot->id, ['sale_date' => '2026-10-01', 'comment' => 'x'])->assertForbidden();
    }

    public function testADeletedSaleCannotBeEdited()
    {
        $sale = $this->sale('2026-10-01');
        $sale->delete();

        $this->as('manager')->get('/ticket-sales/' . $sale->id . '/edit')->assertNotFound();
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'comment' => 'x'])->assertNotFound();
    }

    public function testTheEditGoesBackOnlyToAPageOfThisSite()
    {
        $sale = $this->sale('2026-10-01');

        $this->as('manager')->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'comment' => '', 'back' => 'http://evil.example/steal'])
            ->assertRedirect(url('/spot-sales'));
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'comment' => '', 'back' => url('/confirmations?date=2026-10-01')])
            ->assertRedirect(url('/confirmations?date=2026-10-01'));
        // the address of this site with more behind it is not this site
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'comment' => '', 'back' => rtrim(url('/'), '/') . '.evil.example/x'])
            ->assertRedirect(url('/spot-sales'));
    }

    public function testTheSaleListsOfferTheEditAndLockItOnceConfirmed()
    {
        $open = $this->sale('2026-10-01');
        $done = $this->sale('2026-10-01', $this->confirmed());

        $html = $this->as('accountant')->get('/spot-sales')->assertOk()->getContent();
        $this->assertStringContainsString('/ticket-sales/' . $open->id . '/edit', $html);
        $this->assertStringNotContainsString('/ticket-sales/' . $done->id . '/edit', $html);
        $this->assertStringContainsString('Confirmed: take the confirmation back to edit it', $html);

        // the manager sees the edit; the assistant has no spot sales; the cashier has no edit
        $this->as('manager')->get('/spot-sales')->assertOk()->assertSee('/ticket-sales/' . $open->id . '/edit');
        $this->as('assistant')->get('/spot-sales')->assertForbidden();

        $card = $this->cardSale('2026-10-01');
        $this->as('manager')->get('/card-sales')->assertOk()->assertSee('/ticket-sales/' . $card->id . '/edit');
        $this->as('accountant')->get('/card-sales')->assertOk()->assertSee('/ticket-sales/' . $card->id . '/edit');
        $this->as('assistant')->get('/card-sales')->assertForbidden();
    }

    public function testEveryRecordOfTheDateStaysOnTheConfirmationPageAndAConfirmedOneShowsItsState()
    {
        $open = $this->sale('2026-10-01', ['comment' => 'open']);
        $done = $this->sale('2026-10-01', $this->confirmed());
        $other = $this->sale('2026-10-02');
        $supplementOpen = $this->supplement('2026-10-01');
        $supplementDone = $this->supplement('2026-10-01', $this->confirmed());

        $html = $this->as('accountant')->get('/confirmations?date=2026-10-01')->assertOk()->getContent();
        $this->assertStringContainsString($open->id, $html);
        $this->assertStringContainsString($done->id, $html);
        $this->assertStringNotContainsString($other->id, $html);
        $this->assertSame(1, substr_count($html, 'name="sales[]"'));                  // a confirmed record has no checkbox
        $this->assertSame(1, substr_count($html, 'title="Confirmed"'));                // it says that it is confirmed, and nothing more
        $this->assertStringContainsString('class="btn btn-success"', $html);          // there is still something to confirm

        $html = $this->get('/confirmations/supplements?date=2026-10-01')->assertOk()->getContent();
        $this->assertStringContainsString($supplementOpen->id, $html);
        $this->assertStringContainsString($supplementDone->id, $html);
        $this->assertSame(1, substr_count($html, 'name="supplements[]"'));
        $this->assertSame(1, substr_count($html, 'title="Confirmed"'));

        // after confirming the open ones both stay listed, all marked, with no checkbox and no Confirm button
        $this->post('/confirmations', ['date' => '2026-10-01', 'sales' => [$open->id], 'from' => 'sales'])->assertRedirect();
        $this->post('/confirmations', ['date' => '2026-10-01', 'supplements' => [$supplementOpen->id], 'from' => 'supplements'])->assertRedirect();

        $html = $this->get('/confirmations?date=2026-10-01')->getContent();
        $this->assertStringContainsString($open->id, $html);
        $this->assertSame(0, substr_count($html, 'name="sales[]"'));
        $this->assertSame(2, substr_count($html, 'title="Confirmed"'));
        $this->assertStringNotContainsString('class="btn btn-success"', $html);
        $html = $this->get('/confirmations/supplements?date=2026-10-01')->getContent();
        $this->assertStringContainsString($supplementOpen->id, $html);
        $this->assertSame(0, substr_count($html, 'name="supplements[]"'));
        $this->assertSame(2, substr_count($html, 'title="Confirmed"'));
        $this->assertStringNotContainsString('class="btn btn-success"', $html);

        // a record whose date is changed leaves the date
        $this->as('manager')->post('/ticket-sales/' . $open->id . '/unconfirm');
        $this->put('/ticket-sales/' . $open->id, ['sale_date' => '2026-09-30', 'comment' => 'moved']);
        $html = $this->get('/confirmations?date=2026-10-01')->getContent();
        $this->assertStringNotContainsString($open->id, $html);
        $this->assertStringContainsString($open->id, $this->get('/confirmations?date=2026-09-30')->getContent());
    }

    public function testEachRecordOfTheConfirmationHasAnActionsDropDown()
    {
        $open = $this->sale('2026-10-01');
        $done = $this->sale('2026-10-01', $this->confirmed());
        $card = $this->cardSale('2026-10-01');
        $supplement = $this->supplement('2026-10-01', $this->confirmed());

        $html = $this->as('accountant')->get('/confirmations?date=2026-10-01')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'class="btn-group confirm-actions"'));
        $this->assertStringContainsString('href="' . url('/ticket-sales/' . $open->id) . '" class="dropdown-item record-detail"', $html);   // view
        $this->assertStringContainsString('/ticket-sales/' . $open->id . '/edit', $html);                                                   // edit before confirmation
        $this->assertStringContainsString('/ticket-sales/' . $card->id . '/edit', $html);                                                   // the accountant edits a card sale too
        $this->assertStringNotContainsString('/ticket-sales/' . $done->id . '/edit', $html);                                                // locked once confirmed
        $this->assertStringContainsString('Confirmed: take the confirmation back to edit it', $html);
        $this->assertStringContainsString('/unconfirm', $html);                                                                              // the accountant takes a confirmation back too
        $this->assertStringContainsString('id="record-modal"', $html);                                                                       // the popup

        $html = $this->as('manager')->get('/confirmations?date=2026-10-01')->getContent();
        $this->assertStringContainsString('formaction="' . url('/ticket-sales/' . $done->id . '/unconfirm') . '"', $html);
        $this->assertStringNotContainsString('/ticket-sales/' . $open->id . '/unconfirm', $html);   // an unconfirmed one has nothing to take back

        $html = $this->get('/confirmations/supplements?date=2026-10-01')->getContent();
        $this->assertStringContainsString('href="' . url('/supplements/' . $supplement->id) . '" class="dropdown-item record-detail"', $html);
        $this->assertStringContainsString('formaction="' . url('/supplements/' . $supplement->id . '/unconfirm') . '"', $html);
        $this->as('assistant')->get('/confirmations/supplements?date=2026-10-01')->assertDontSee('/unconfirm');
        $this->as('accountant');

        // the popup shows the same detail as the list
        $this->get('/ticket-sales/' . $open->id . '?modal=1')->assertOk()->assertSee('Sale date')->assertDontSee('<html', false);
        $this->get('/supplements/' . $supplement->id . '?modal=1')->assertOk();
    }

    public function testTheExcelFilesListEveryRecordOfTheDateWithAConfirmedColumn()
    {
        $this->sale('2026-10-01');
        $this->sale('2026-10-01', $this->confirmed());
        $this->supplement('2026-10-01');
        $this->supplement('2026-10-01', $this->confirmed());

        $read = function ($response) {
            $zip = new \ZipArchive();
            $file = tempnam(sys_get_temp_dir(), 'x');
            copy($response->baseResponse->getFile()->getPathname(), $file);
            $zip->open($file);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            unlink($file);

            return $sheet;
        };

        $sheet = $read($this->as('accountant')->get('/confirmations/sales-export?date=2026-10-01&block=spot'));
        $this->assertStringContainsString('Confirmed', $sheet);
        $this->assertSame(1, substr_count($sheet, '>Yes<'));                        // one of the two sales is confirmed
        $this->assertStringContainsString('<v>20</v>', $sheet);                     // the total of the two: 10 + 10 kg

        $sheet = $read($this->get('/confirmations/supplements-export?date=2026-10-01'));
        $this->assertStringContainsString('Confirmed', $sheet);
        $this->assertSame(1, substr_count($sheet, '>Yes<'));
        $this->assertStringContainsString('<v>18</v>', $sheet);                     // 9 + 9 kg
    }
}
