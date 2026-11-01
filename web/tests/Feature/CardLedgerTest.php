<?php

namespace Tests\Feature;

use App\Models\Cards\Card;
use App\Models\Cards\CardTransaction;
use App\Models\Fuel\FuelType;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Admin\User;
use App\Support\Cards\CardLedger;
use App\Support\Cards\CardPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CardLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7.5]);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function card($uid = 'AA01')
    {
        return Card::forceCreate(['uid' => $uid, 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
    }

    private function topUp($card, $kg, $at)
    {
        return Sale::forceCreate([
            'card_id' => $card->id, 'sale_type' => 'card', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => $kg, 'payment_method' => 'cash',
            'total_amount' => $kg * 2, 'paid_amount' => $kg * 2, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => substr($at, 0, 10), 'user_id' => 1,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function fill($card, $kg, $at)
    {
        return Supplement::forceCreate([
            'card_id' => $card->id, 'fuel_type' => 'D', 'nozzle_id' => 1, 'preset_kg' => $kg, 'out_amount_lt' => $kg, 'out_amount_kg' => $kg,
            'supplied_at' => $at, 'supply_date' => substr($at, 0, 10),
        ]);
    }

    private function ledger($card)
    {
        return CardTransaction::where('card_id', $card->id)->orderBy('occurred_at')->orderBy('seq')->get();
    }

    private function floats($collection)
    {
        return $collection->map(function ($v) {
            return (float) $v;
        })->all();
    }

    // Only the entries from the changed one on are walked, and the balances are the same as when all of them are: a top-up that is older than the fills after it, a
    // fill that moves to an earlier day, and a deleted top-up.
    public function testAnEntryInTheMiddleChangesOnlyTheBalancesFromItOn()
    {
        $card = $this->card();
        $this->topUp($card, 100, '2026-10-01 09:00:00');
        $fill = $this->fill($card, 30, '2026-10-03 10:00:00');
        $this->fill($card, 20, '2026-10-04 10:00:00');
        $this->assertSame([100.0, 70.0, 50.0], $this->floats($this->ledger($card)->pluck('balance_kg')));

        $this->topUp($card, 10, '2026-10-02 09:00:00');                                         // between the first two entries
        $this->assertSame([100.0, 110.0, 80.0, 60.0], $this->floats($this->ledger($card)->pluck('balance_kg')));

        $fill->forceFill(['supplied_at' => '2026-10-01 12:00:00', 'supply_date' => '2026-10-01', 'out_amount_kg' => 35])->save();     // moves back and changes
        $partial = $this->floats($this->ledger($card)->pluck('balance_kg'));
        DB::table('card_transactions')->update(['balance_kg' => null]);
        CardLedger::recompute($card->id);                                                           // all of them
        $this->assertSame($this->floats($this->ledger($card)->pluck('balance_kg')), $partial);
        $this->assertSame([100.0, 65.0, 75.0, 55.0], $partial);
    }

    public function testEveryTopUpAndFillIsAnEntryWithTheRunningBalance()
    {
        $card = $this->card();
        $sale = $this->topUp($card, 100, '2026-10-01 09:00:00');
        $supply = $this->fill($card, 30.5, '2026-10-02 10:00:00');
        $this->topUp($card, 50, '2026-10-03 11:00:00');

        $entries = $this->ledger($card);
        $this->assertSame(['topup', 'fill', 'topup'], $entries->pluck('type')->all());
        $this->assertEquals([100, -30.5, 50], $this->floats($entries->pluck('kg')));
        $this->assertEquals([100, 69.5, 119.5], $this->floats($entries->pluck('balance_kg')));
        $this->assertSame($sale->id, $entries[0]->source_id);
        $this->assertSame($supply->id, $entries[1]->source_id);
        $this->assertEquals(2, $entries[0]->price);                       // what the top-up cost
        $this->assertEquals(200, $entries[0]->total_amount);
        $this->assertEquals(119.5, $card->fresh()->balance());
        $this->assertEquals(119.5, Card::balances()[$card->id]);
    }

    public function testASavedRecordIsNotCountedTwiceAndAChangedWeightIsFollowed()
    {
        $card = $this->card();
        $sale = $this->topUp($card, 100, '2026-10-01 09:00:00');
        $sale->comment = 'fixed';
        $sale->save();
        $this->assertSame(1, CardTransaction::count());

        $sale->amount_kg = 80;
        $sale->save();
        $this->assertSame(1, CardTransaction::count());
        $this->assertEquals(80, CardLedger::balance($card->id));
        $this->assertEquals(80, $this->ledger($card)[0]->balance_kg);
    }

    public function testAnEntryOutOfOrderChangesTheBalancesAfterIt()
    {
        $card = $this->card();
        $this->topUp($card, 100, '2026-10-01 09:00:00');
        $this->fill($card, 40, '2026-10-05 09:00:00');
        $this->fill($card, 10, '2026-10-03 09:00:00');   // arrives later, happened earlier (a terminal that uploaded late)

        $entries = $this->ledger($card);
        $this->assertEquals([100, 90, 50], $this->floats($entries->pluck('balance_kg')));
        $this->assertEquals(['2026-10-01', '2026-10-03', '2026-10-05'], $entries->map(function ($e) {
            return substr((string) $e->occurred_at, 0, 10);
        })->all());
    }

    public function testADeletedTopUpGetsAReversalAndTheHistorySurvivesTheDeletedSale()
    {
        $card = $this->card();
        $sale = $this->topUp($card, 100, '2026-10-01 09:00:00');
        $this->topUp($card, 20, '2026-10-02 09:00:00');

        $sale->delete();                                   // deleted on the web (a mistaken top-up)
        $entries = $this->ledger($card);
        $this->assertSame(['topup', 'topup', 'reversal'], $entries->pluck('type')->all());
        $this->assertEquals(20, CardLedger::balance($card->id));
        $this->assertEquals(-100, (float) $entries->last()->kg);
        $this->assertEquals([100, 120, 20], $this->floats($entries->pluck('balance_kg')));

        $sale->delete();                                   // again: no second reversal
        $this->assertSame(3, CardTransaction::count());

        Sale::withTrashed()->where('id', $sale->id)->forceDelete();   // the sale is gone for good
        $this->assertSame(3, CardTransaction::count());
        $this->assertEquals(20, $card->fresh()->balance());           // the ledger still holds what happened
    }

    public function testEntriesOfTheSameSecondKeepTheOrderTheyWereMadeIn()
    {
        $card = $this->card();
        $sale = $this->topUp($card, 100, '2026-10-01 09:00:00');
        $now = '2026-10-02 12:00:00';
        $webTopUp = $this->topUp($card, 5, $now);          // a top-up and the deletion of another one in the same second
        $sale->deleted_at = $now;
        $sale->save();
        $sale->delete();

        $entries = $this->ledger($card);
        $this->assertSame(['topup', 'topup', 'reversal'], $entries->pluck('type')->all());
        $this->assertSame([$sale->id, $webTopUp->id, $sale->id], $entries->pluck('source_id')->all());
        $this->assertEquals([100, 105, 5], $this->floats($entries->pluck('balance_kg')));
        $this->assertTrue($entries[1]->seq < $entries[2]->seq);                 // made in this order, whatever their ids are
    }

    public function testADeletedFillIsUndoneByAReversal()
    {
        $card = $this->card();
        $this->topUp($card, 100, '2026-10-01 09:00:00');
        $supply = $this->fill($card, 30, '2026-10-02 09:00:00');
        $this->assertEquals(70, CardLedger::balance($card->id));

        $supply->delete();
        $this->assertEquals(100, CardLedger::balance($card->id));
        $this->assertSame('reversal', $this->ledger($card)->last()->type);
    }

    public function testOtherSalesAndPlainSuppliesAreNotInTheLedger()
    {
        $card = $this->card();
        Sale::forceCreate([
            'sale_type' => 'spot', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 10, 'payment_method' => 'cash', 'total_amount' => 20,
            'paid_amount' => 20, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => '2026-10-01', 'user_id' => 1,
        ]);
        Supplement::forceCreate(['fuel_type' => 'D', 'nozzle_id' => 1, 'preset_kg' => 5, 'out_amount_lt' => 5, 'out_amount_kg' => 5, 'supplied_at' => '2026-10-01 10:00:00']);

        $this->assertSame(0, CardTransaction::count());
        $this->assertEquals(0, $card->balance());
    }

    public function testTheLedgerCanBeRebuiltFromTheRecords()
    {
        $card = $this->card();
        $row = ['card_id' => $card->id, 'sale_type' => 'card', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 100, 'payment_method' => 'cash', 'total_amount' => 200,
            'paid_amount' => 200, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => '2026-10-01', 'user_id' => 1];
        // written straight to the table (no model events), as the records of before the ledger were
        DB::table('sales')->insert(['id' => '11111111-1111-1111-1111-111111111111', 'created_at' => '2026-10-01 09:00:00'] + $row);
        DB::table('sales')->insert(['id' => '22222222-2222-2222-2222-222222222222', 'created_at' => '2026-10-02 09:00:00', 'deleted_at' => '2026-10-03 09:00:00'] + $row);
        DB::table('supplements')->insert([
            'id' => '33333333-3333-3333-3333-333333333333', 'card_id' => $card->id, 'fuel_type' => 'D', 'nozzle_id' => 1,
            'preset_kg' => 30, 'out_amount_lt' => 30, 'out_amount_kg' => 30, 'supplied_at' => '2026-10-04 09:00:00',
        ]);
        $this->assertSame(0, CardTransaction::count());

        $this->assertSame(4, CardLedger::rebuild());       // two top-ups, the reversal of the deleted one, one fill
        $this->assertEquals(70, CardLedger::balance($card->id));
        $this->assertSame(0, CardLedger::rebuild());       // nothing is made twice

        \Artisan::call('cards:rebuild-ledger');
        $this->assertSame(4, CardTransaction::count());
    }

    public function testTheHistoryPageListsTheEntriesAndWarnsOfADifference()
    {
        $card = $this->card();
        $this->topUp($card, 100, '2026-10-01 09:00:00');
        $this->fill($card, 30, '2026-10-02 10:00:00');

        $page = $this->as('manager')->get('/cards/' . $card->id . '/history')->assertOk()
            ->assertSee(__('cards.entry_topup'))->assertSee(__('cards.entry_fill'))->assertSee('70.00');
        $this->assertSame(2, $page->viewData('entries')->total());
        $this->assertStringNotContainsString(htmlspecialchars(__('cards.ledger_differs', ['ledger' => '70.00', 'records' => '70.00']), ENT_QUOTES), $page->getContent());
        $this->assertSame(1, $this->as('manager')->get('/cards/' . $card->id . '/history?from=2026-10-02&to=2026-10-02')->assertOk()->viewData('entries')->total());

        DB::table('sales')->where('card_id', $card->id)->delete();   // a top-up removed for good, behind the ledger's back
        $this->as('accountant')->get('/cards/' . $card->id . '/history')->assertOk()
            ->assertSee(htmlspecialchars(__('cards.ledger_differs', ['ledger' => '70.00', 'records' => '-30.00']), ENT_QUOTES), false);

        $this->as('manager')->get('/cards')->assertOk()->assertSee(route('cards.history', $card->id));
    }

    public function testOnlyThoseWhoMaySeeCardsSeeTheHistory()
    {
        $card = $this->card();
        $this->as('cashier')->get('/cards/' . $card->id . '/history')->assertForbidden();
        $this->as('admin')->get('/cards/' . $card->id . '/history')->assertOk();
        $this->as('admin')->get('/cards/nothing/history')->assertNotFound();
    }

    // a ticket made for a card (tickets.card_id): the card paid with its kg when it was made; restricting it gives them back, lifting takes them again
    private function cardTicket($card, $kg, $serial = 1, $status = null)
    {
        return \App\Models\Tickets\Ticket::forceCreate([
            'barcode' => \App\Support\Tickets\Barcode::makeReserved('25', config('ticket_serial.device_base') + $serial), 'fuel_type' => 'D', 'spec' => null, 'code' => '25',
            'serial' => config('ticket_serial.device_base') + $serial, 'amount_kg' => $kg, 'status' => $status ?: config('ticket_statuses.sale'),
            'sale_date' => '2026-10-04 10:00:00', 'card_id' => $card->id,
        ]);
    }

    public function testATicketMadeForACardIsAFillEntryAndRestrictingItGivesTheKgBack()
    {
        $card = $this->card();
        $this->topUp($card, 40, '2026-10-01 09:00:00');
        $ticket = $this->cardTicket($card, 15);

        $entries = $this->ledger($card);
        $this->assertSame(['topup', 'fill'], $entries->pluck('type')->all());
        $this->assertEquals(-15, $entries[1]->kg);
        $this->assertSame($ticket->barcode, $entries[1]->source_id);
        $this->assertSame('ticket ' . $ticket->barcode, $entries[1]->comment);
        $this->assertEquals(25, CardLedger::balance($card->id));
        $this->assertEquals(25, $card->balanceFromRecords());                       // the records agree with the ledger

        // saved again (supplied): nothing is counted twice
        $ticket->forceFill(['status' => config('ticket_statuses.supply'), 'supply_date' => '2026-10-04 11:00:00'])->save();
        $this->assertSame(2, CardTransaction::count());
        $this->assertEquals(25, CardLedger::balance($card->id));

        // an unused ticket is restricted: a reversal gives the 15 kg back (once)
        $other = $this->cardTicket($card, 10, 2);
        $this->assertEquals(15, CardLedger::balance($card->id));
        $this->as('manager')->post('/tickets/' . $other->barcode . '/restrict')->assertSessionHas('status');
        $this->assertSame(['topup', 'fill', 'fill', 'reversal'], $this->ledger($card)->pluck('type')->all());
        $this->assertEquals(25, CardLedger::balance($card->id));
        $this->assertEquals(25, $card->balanceFromRecords());                       // a restricted ticket is not a fill
        $this->assertEquals(10, CardTransaction::where('type', 'reversal')->first()->kg);

        // the restriction is lifted: the ticket is sold again (not blank stock) and the card pays again, in the same entry
        $this->post('/tickets/' . $other->barcode . '/lift-restriction')->assertSessionHas('status');
        $this->assertSame(config('ticket_statuses.sale'), (int) $other->fresh()->status);
        $this->assertSame(4, CardTransaction::count());
        $this->assertEquals(0, CardTransaction::where('type', 'reversal')->first()->kg);
        $this->assertEquals(15, CardLedger::balance($card->id));

        // restricted again: the same reversal gives the kg back again
        $this->post('/tickets/' . $other->barcode . '/restrict')->assertSessionHas('status');
        $this->assertSame(4, CardTransaction::count());
        $this->assertEquals(25, CardLedger::balance($card->id));
    }

    public function testTheRebuildAlsoMakesTheEntriesOfTheTickets()
    {
        $card = $this->card();
        $this->topUp($card, 40, '2026-10-01 09:00:00');
        $this->cardTicket($card, 15);
        $this->cardTicket($card, 5, 2, config('ticket_statuses.restrict'));
        CardTransaction::query()->delete();

        CardLedger::rebuild();

        $this->assertSame(['topup', 'fill', 'fill', 'reversal'], $this->ledger($card)->pluck('type')->all());
        $this->assertEquals(25, CardLedger::balance($card->id));
        $this->assertEquals(25, $card->balanceFromRecords());
    }

    // the supply a terminal uploads: the supply first, then its tickets (they name the supply); an older terminal also makes a remainder ticket of the unused part
    public function testTheUnusedPartOfACardsTicketIsARefundWhenTheTicketJoinsTheSupplyAndNoneWhenARemainderExists()
    {
        $card = $this->card();
        $this->topUp($card, 40, '2026-10-01 09:00:00');
        $ticket = $this->cardTicket($card, 15);
        $supply = Supplement::forceCreate([
            'fuel_type' => 'D', 'nozzle_id' => 1, 'preset_kg' => 15, 'out_amount_lt' => 10, 'out_amount_kg' => 10, 'supplied_at' => '2026-10-04 11:00:00', 'supply_date' => '2026-10-04',
        ]);
        $this->assertSame(0, CardTransaction::where('type', 'refund')->count());          // no ticket on it yet

        $ticket->forceFill(['status' => config('ticket_statuses.supply'), 'supplement_id' => $supply->id])->save();
        $refund = CardTransaction::where('type', 'refund')->first();
        $this->assertEquals(5, $refund->kg);
        $this->assertEquals(30, CardLedger::balance($card->id));
        $this->assertSame('2026-10-04 11:00:00', (string) $refund->occurred_at);

        // the ticket leaves the supply again (it was deleted): the refund goes to 0
        $ticket->forceFill(['status' => config('ticket_statuses.sale'), 'supplement_id' => null])->save();
        $this->assertEquals(0, $refund->fresh()->kg);
        $this->assertEquals(25, CardLedger::balance($card->id));

        // an older terminal made a remainder ticket of it: the card gets nothing back
        $ticket->forceFill(['status' => config('ticket_statuses.supply'), 'supplement_id' => $supply->id])->save();
        $this->assertEquals(5, $refund->fresh()->kg);
        \App\Models\Supplements\Remainder::forceCreate([
            'supplement_id' => $supply->id, 'ticket_barcode' => '0000029500000001', 'fuel_type' => 'D', 'amount_kg' => 5, 'remainder_date' => '2026-10-04', 'created_at' => '2026-10-04 11:00:00',
        ]);
        $this->assertEquals(0, $refund->fresh()->kg);
        $this->assertEquals(25, CardLedger::balance($card->id));
    }
}
