<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Sale;
use App\Models\Supplement;
use App\Models\Ticket;
use App\Models\TicketLoan;
use App\Models\User;
use App\Support\CardPin;
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

        // only the manager takes a confirmation back
        $this->as('accountant')->post('/ticket-sales/' . $sale->id . '/unconfirm')->assertForbidden();
        $this->assertNotNull($sale->fresh()->confirmed_at);
        $this->as('manager')->post('/ticket-sales/' . $sale->id . '/unconfirm')->assertRedirect()->assertSessionHas('status');
        $this->assertNull($sale->fresh()->confirmed_at);
        $this->assertNull($sale->fresh()->confirmed_by);

        // now it can be edited, and an unconfirmed record cannot be unconfirmed again
        $this->put('/ticket-sales/' . $sale->id, ['sale_date' => '2026-10-01', 'comment' => 'new'])->assertRedirect();
        $this->assertSame('new', $sale->fresh()->comment);
        $this->post('/ticket-sales/' . $sale->id . '/unconfirm')->assertSessionHasErrors('record');
    }

    public function testASupplementConfirmationIsTakenBackByTheManagerOnly()
    {
        $supplement = $this->supplement('2026-10-01', $this->confirmed());

        $this->as('accountant')->post('/supplements/' . $supplement->id . '/unconfirm')->assertForbidden();
        $this->as('assistant')->post('/supplements/' . $supplement->id . '/unconfirm')->assertForbidden();
        $this->as('manager')->post('/supplements/' . $supplement->id . '/unconfirm')->assertRedirect();
        $this->assertNull($supplement->fresh()->confirmed_at);
        $this->post('/supplements/' . $supplement->id . '/unconfirm')->assertSessionHasErrors('record');
    }

    public function testWhoMayEditWhichSale()
    {
        $spot = $this->sale('2026-10-01');
        $card = $this->cardSale('2026-10-01');

        foreach (['manager', 'accountant', 'assistant'] as $role) {
            $this->as($role)->get('/ticket-sales/' . $spot->id . '/edit')->assertOk();
        }
        foreach (['manager', 'accountant'] as $role) {
            $this->as($role)->get('/ticket-sales/' . $card->id . '/edit')->assertOk();
        }
        // the assistant edits ticket and spot sales, not the top-ups of a card
        $this->as('assistant')->get('/ticket-sales/' . $card->id . '/edit')->assertForbidden();
        $this->put('/ticket-sales/' . $card->id, ['sale_date' => '2026-10-01', 'comment' => 'x'])->assertForbidden();
        $this->assertNull($card->fresh()->comment);
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

        // the cashier does not see the sales, the assistant sees the edit
        $this->as('assistant')->get('/spot-sales')->assertOk()->assertSee('/ticket-sales/' . $open->id . '/edit');

        $card = $this->cardSale('2026-10-01');
        $this->as('manager')->get('/card-sales')->assertOk()->assertSee('/ticket-sales/' . $card->id . '/edit');
        $this->as('assistant')->get('/card-sales')->assertOk()->assertDontSee('/ticket-sales/' . $card->id . '/edit');
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
        $this->assertStringNotContainsString('/unconfirm', $html);                                                                           // only the manager
        $this->assertStringContainsString('id="record-modal"', $html);                                                                       // the popup

        $html = $this->as('manager')->get('/confirmations?date=2026-10-01')->getContent();
        $this->assertStringContainsString('formaction="' . url('/ticket-sales/' . $done->id . '/unconfirm') . '"', $html);
        $this->assertStringNotContainsString('/ticket-sales/' . $open->id . '/unconfirm', $html);   // an unconfirmed one has nothing to take back

        $html = $this->get('/confirmations/supplements?date=2026-10-01')->getContent();
        $this->assertStringContainsString('href="' . url('/supplements/' . $supplement->id) . '" class="dropdown-item record-detail"', $html);
        $this->assertStringContainsString('formaction="' . url('/supplements/' . $supplement->id . '/unconfirm') . '"', $html);
        $this->as('accountant')->get('/confirmations/supplements?date=2026-10-01')->assertDontSee('/unconfirm');

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
