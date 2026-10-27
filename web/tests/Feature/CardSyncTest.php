<?php

namespace Tests\Feature;

use App\Models\Cards\Card;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Nozzle;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Fuel\Tank;
use App\Models\Admin\User;
use App\Support\Admin\BackupRun;
use App\Support\Cards\CardPin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\RegistersDevice;
use Tests\TestCase;

/**
 * The cards between the web app and the Smart210: pushed (cards, card sales, supplies made with a card), pulled (cards with their balance and PIN hash), and
 * never cleared by a backup. Runs against the gasstation_test database (see phpunit.xml).
 */
class CardSyncTest extends TestCase
{
    use RefreshDatabase, RegistersDevice;

    private $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->seed();

        $this->cashier = User::forceCreate([
            'name' => 'Cash', 'username' => 'cash', 'password' => bcrypt('secret1'), 'role' => 'cashier', 'active' => true,
            'pos_salt' => 'aa11', 'pos_hash' => hash('sha256', 'aa11' . 'secret1'),
        ]);
        $this->registerDevice();
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        Nozzle::forceCreate(['device_id' => Device::first()->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => 'D']);
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7.5]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload($response)
    {
        return json_decode($response->getContent(), true);
    }

    private function push($table, array $rows)
    {
        return $this->payload($this->postJson('/api/push/' . $table, ['rows' => $rows])->assertOk());
    }

    // the card row the terminal pushes
    private function cardRow($overrides = [])
    {
        $pin = CardPin::make('1234');

        return $overrides + ['id' => (string) Str::uuid(), 'uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D', 'failed_attempts' => 0, 'status' => 'active'] + $pin;
    }

    private function saleRow($card, $overrides = [])
    {
        return $overrides + [
            'id' => (string) Str::uuid(), 'created_at' => '2026-10-04 09:00:00', 'card_id' => $card->id, 'customer_id' => null,
            'sale_type' => 'card', 'fuel_type' => 'D', 'price' => '7.50', 'amount_kg' => '40.00', 'payment_method' => 'cash',
            'total_amount' => '300.00', 'paid_amount' => '300.00', 'unpaid_amount' => '0.00', 'receipt' => null,
            'usage' => 'sale', 'comment' => null, 'sale_date' => '2026-10-04', 'user_id' => $this->cashier->id,
        ];
    }

    private function supplyRow($card, $overrides = [])
    {
        return $overrides + [
            'id' => (string) Str::uuid(), 'card_id' => $card->id, 'fuel_type' => 'D', 'nozzle_number' => 1, 'out_amount_lt' => '14.00',
            'out_amount_kg' => '12.00', 'preset_kg' => '12.00', 'supplied_at' => '2026-10-04 10:00:00', 'supply_date' => '2026-10-04',
        ];
    }

    public function testTheTerminalRegistersACardAndItIsActive()
    {
        $row = $this->cardRow();

        $result = $this->push('cards', [$row]);

        $this->assertSame([$row['id']], $result['saved']);
        $card = Card::find($row['id']);
        $this->assertSame('AA01', $card->uid);
        $this->assertSame('Li Wei', $card->holder_name);
        $this->assertSame('D', $card->fuel_type);
        $this->assertSame('active', $card->status);
        $this->assertTrue((bool) $card->synced);                                   // it came from the terminal: nothing to pull back
        $this->assertSame(Device::first()->id, (int) $card->device_id);
        $this->assertTrue(CardPin::check($card, '1234'));                          // the salted hash the terminal sent, kept as it is

        // pushed again: the same card
        $this->assertSame([$row['id']], $this->push('cards', [$row])['saved']);
        $this->assertSame(1, Card::count());
    }

    public function testACardPushIsRefusedWhenItIsBad()
    {
        $good = $this->cardRow();
        $this->push('cards', [$good]);

        $result = $this->push('cards', [
            $this->cardRow(['uid' => 'AA01']),                          // another card with the card id of the first
            $this->cardRow(['uid' => 'BB02', 'fuel_type' => 'X']),
            $this->cardRow(['uid' => 'CC03', 'pin_hash' => 'short']),
            $this->cardRow(['uid' => '', 'holder_name' => '']),
            $this->cardRow(['uid' => 'DD04', 'status' => 'lost']),
        ]);

        $this->assertSame([], $result['saved']);
        $this->assertCount(5, $result['rejected']);
        $this->assertStringContainsString('another card', $result['rejected'][0]['error']);
        $this->assertSame(1, Card::count());
    }

    public function testTheTerminalReportsWrongPinsAndALockButNeverUnlocksOrBlocks()
    {
        $row = $this->cardRow();
        $this->push('cards', [$row]);

        // the terminal counts wrong PINs and locks the card after 5
        $this->push('cards', [array_merge($row, ['failed_attempts' => 3])]);
        $this->assertSame(3, (int) Card::find($row['id'])->failed_attempts);
        $this->push('cards', [array_merge($row, ['failed_attempts' => 5, 'status' => 'locked'])]);
        $card = Card::find($row['id']);
        $this->assertSame('locked', $card->status);
        $this->assertSame(5, (int) $card->failed_attempts);

        // the terminal cannot unlock or block: that is done on the web
        $this->push('cards', [array_merge($row, ['failed_attempts' => 0, 'status' => 'active'])]);
        $this->assertSame('locked', Card::find($row['id'])->status);
        $this->push('cards', [array_merge($row, ['status' => 'blocked'])]);
        $this->assertSame('locked', Card::find($row['id'])->status);

        // the card, the PIN and the name are not changed by a push either
        $this->push('cards', [array_merge($row, ['holder_name' => 'Someone else', 'fuel_type' => 'D', 'pin_salt' => 'zz', 'pin_hash' => str_repeat('b', 64)])]);
        $card = Card::find($row['id']);
        $this->assertSame('Li Wei', $card->holder_name);
        $this->assertTrue(CardPin::check($card, '1234'));
    }

    public function testAChangeOfTheWebWaitsForThePullAndIsNotOverwrittenByTheTerminal()
    {
        $row = $this->cardRow();
        $this->push('cards', [$row]);

        // the manager unlocks a card that is locked on the web (the terminal's push of the lock was sent before)
        $this->push('cards', [array_merge($row, ['failed_attempts' => 5, 'status' => 'locked'])]);
        $this->actingAs(User::where('role', 'admin')->first())->post('/cards/' . $row['id'] . '/unlock')->assertRedirect('/cards');
        $this->assertFalse((bool) Card::find($row['id'])->synced);

        // the terminal, which has not pulled yet, pushes its old copy: ignored
        $this->push('cards', [array_merge($row, ['failed_attempts' => 5, 'status' => 'locked'])]);
        $card = Card::find($row['id']);
        $this->assertSame('active', $card->status);
        $this->assertSame(0, (int) $card->failed_attempts);
    }

    public function testACardSaleIsPushedAndTheBalanceGrows()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
        $row = $this->saleRow($card);

        $this->assertSame([$row['id']], $this->push('sales', [$row])['saved']);

        $sale = Sale::find($row['id']);
        $this->assertSame('card', $sale->sale_type);
        $this->assertSame($card->id, $sale->card_id);
        $this->assertNull($sale->customer_id);
        $this->assertEquals(40, $card->balance());
    }

    public function testACardSaleThatBreaksTheRulesIsRefused()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));

        $bad = [
            $this->saleRow($card, ['card_id' => null]),                                     // no card
            $this->saleRow($card, ['card_id' => (string) Str::uuid()]),                     // an unknown card
            $this->saleRow($card, ['fuel_type' => 'A']),                                    // not the fuel type of the card
            $this->saleRow($card, ['payment_method' => 'bank']),                            // a card is paid in cash
            $this->saleRow($card, ['usage' => 'self', 'total_amount' => '0.00', 'paid_amount' => '0.00']),
            $this->saleRow($card, ['unpaid_amount' => '300.00', 'paid_amount' => '0.00']), // no debt
            $this->saleRow($card, ['sale_type' => 'spot']),                                 // a card on another sale
        ];
        $result = $this->push('sales', $bad);

        $this->assertSame([], $result['saved']);
        $this->assertCount(7, $result['rejected']);
        $this->assertSame(0, Sale::count());
    }

    public function testASupplyMadeWithACardIsPushedWithoutTicketsAndTheBalanceDrops()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
        $this->push('sales', [$this->saleRow($card)]);
        $row = $this->supplyRow($card);

        $this->assertSame([$row['id']], $this->push('supplements', [$row])['saved']);

        $supplement = Supplement::find($row['id']);
        $this->assertSame($card->id, $supplement->card_id);
        $this->assertSame(0, $supplement->tickets()->count());                              // no tickets
        $this->assertEquals(28, $card->balance());                                          // 40 - 12 (the dispensed kg)

        // a supply with a card of another fuel type, or of an unknown card, is refused
        $result = $this->push('supplements', [$this->supplyRow($card, ['fuel_type' => 'A']), $this->supplyRow($card, ['card_id' => (string) Str::uuid()])]);
        $this->assertSame([], $result['saved']);
        $this->assertCount(2, $result['rejected']);

        // an ordinary supply has no card
        $plain = $this->supplyRow($card, ['card_id' => null]);
        $this->push('supplements', [$plain]);
        $this->assertNull(Supplement::find($plain['id'])->card_id);
    }

    public function testTheCardsArePulledWithTheirBalanceStatusAndPinHash()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'holder_phone' => '138 0000 1111', 'fuel_type' => 'D', 'failed_attempts' => 2] + CardPin::make('1234'));
        Sale::forceCreate(['card_id' => $card->id, 'sale_type' => 'card', 'fuel_type' => 'D', 'price' => 7.5, 'amount_kg' => 40, 'payment_method' => 'cash', 'total_amount' => 300,
            'paid_amount' => 300, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => '2026-10-04', 'user_id' => 1]);
        Supplement::forceCreate(['card_id' => $card->id, 'fuel_type' => 'D', 'nozzle_id' => Nozzle::first()->id, 'preset_kg' => 12, 'out_amount_lt' => 14, 'out_amount_kg' => 12,
            'supplied_at' => '2026-10-04 10:00:00', 'supply_date' => '2026-10-04']);
        Carbon::setTestNow(Carbon::now()->addSeconds(10));

        $page = $this->payload($this->get('/api/pull/cards')->assertOk());

        $this->assertCount(1, $page['rows']);
        $row = $page['rows'][0];
        $this->assertSame($card->id, $row['id']);
        $this->assertSame('AA01', $row['uid']);
        $this->assertSame('Li Wei', $row['holder_name']);
        $this->assertSame('138 0000 1111', $row['holder_phone']);
        $this->assertSame('D', $row['fuel_type']);
        $this->assertSame($card->pin_salt, $row['pin_salt']);
        $this->assertSame($card->pin_hash, $row['pin_hash']);
        $this->assertSame(2, $row['failed_attempts']);
        $this->assertSame('active', $row['status']);
        $this->assertSame('28.00', $row['balance_kg']);
        $this->assertFalse($row['deleted']);

        // acked, it is not sent again; a top-up on the web puts the card back to be pulled with its new balance
        $this->postJson('/api/pull/cards/ack', ['keys' => [$card->id], 'pulled_at' => $page['server_time']])->assertOk();
        $this->assertCount(0, $this->payload($this->get('/api/pull/cards')->assertOk())['rows']);

        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $this->actingAs(User::where('role', 'admin')->first())->post('/card-topups', ['card_id' => $card->id, 'amount_kg' => 10, 'price' => 7.5])->assertRedirect('/card-topups');
        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $again = $this->payload($this->get('/api/pull/cards')->assertOk());
        $this->assertCount(1, $again['rows']);
        $this->assertSame('38.00', $again['rows'][0]['balance_kg']);

        // a status change on the web (a lost card) is pulled too
        $this->postJson('/api/pull/cards/ack', ['keys' => [$card->id], 'pulled_at' => $again['server_time']])->assertOk();
        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $this->post('/cards/' . $card->id . '/block')->assertRedirect('/cards');
        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $this->assertSame('blocked', $this->payload($this->get('/api/pull/cards')->assertOk())['rows'][0]['status']);
    }

    public function testANewTerminalPullsTheCardsAgain()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D', 'synced' => true] + CardPin::make('1234'));

        $this->postJson('/api/sync/begin')->assertOk();

        $this->assertFalse((bool) $card->fresh()->synced);
        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $this->assertCount(1, $this->payload($this->get('/api/pull/cards')->assertOk())['rows']);
    }

    public function testABackupNeverClearsTheCardRecords()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
        $topUp = Sale::forceCreate(['card_id' => $card->id, 'sale_type' => 'card', 'fuel_type' => 'D', 'price' => 7.5, 'amount_kg' => 40, 'payment_method' => 'cash', 'total_amount' => 300,
            'paid_amount' => 300, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => '2026-09-01', 'user_id' => 1]);
        $plain = Sale::forceCreate(['sale_type' => 'spot', 'fuel_type' => 'D', 'price' => 1, 'amount_kg' => 5, 'payment_method' => 'cash', 'total_amount' => 5,
            'paid_amount' => 5, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => '2026-09-01', 'user_id' => 1]);
        $withCard = Supplement::forceCreate(['card_id' => $card->id, 'fuel_type' => 'D', 'nozzle_id' => Nozzle::first()->id, 'preset_kg' => 12, 'out_amount_lt' => 14, 'out_amount_kg' => 12,
            'supplied_at' => '2026-09-01 10:00:00', 'supply_date' => '2026-09-01']);
        Supplement::forceCreate(['fuel_type' => 'D', 'nozzle_id' => Nozzle::first()->id, 'preset_kg' => 7, 'out_amount_lt' => 8, 'out_amount_kg' => 7,
            'supplied_at' => '2026-09-01 11:00:00', 'supply_date' => '2026-09-01']);

        $preview = BackupRun::preview('2026-09-30');

        $this->assertSame(1, $preview['sales']);                                                // only the sale without a card
        $this->assertSame(1, $preview['supplements']);                                          // only the supply without a card
        // the tank carries only what it clears: the 7 kg of the supply without a card, not the 12 kg made with the card
        $carry = BackupRun::tankCarryForward('2026-09-30');
        $this->assertEquals(-7, array_sum($carry));

        $this->assertEquals(28, $card->balance());                                              // the balance counts them all
    }

    public function testAFillMadeAfterTheCardRanOutIsKeptAndTheCardIsListedBelowZero()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
        $this->push('sales', [$this->saleRow($card, ['amount_kg' => '20.00', 'total_amount' => '150.00', 'paid_amount' => '150.00'])]);       // the card holds 20 kg

        // 12 kg are fine, the next 12 kg are poured too: fuel that was dispensed is a fact, so it is stored and the balance is -4
        $first = $this->supplyRow($card);
        $second = $this->supplyRow($card);
        $this->assertSame([$first['id']], $this->push('supplements', [$first])['saved']);
        $this->assertSame(0, \App\Models\Admin\SyncError::where('entity', 'supplements')->count());
        $this->assertSame([$second['id']], $this->push('supplements', [$second])['saved']);
        $this->assertNotNull(Supplement::find($second['id']));
        $this->assertEquals(-4, $card->balance());
        $error = \App\Models\Admin\SyncError::where('entity', 'supplements')->first();
        $this->assertStringContainsString('the card held only 8.00 kg', $error->reason);
        $this->assertSame($second['id'], $error->record_id);

        // the same fill sent again (the terminal did not get the answer) is not counted twice and not reported twice
        $this->assertSame([$second['id']], $this->push('supplements', [$second])['saved']);
        $this->assertEquals(-4, $card->balance());
        $this->assertSame(1, \App\Models\Admin\SyncError::where('entity', 'supplements')->count());
        $this->assertSame(1, (int) \App\Models\Admin\SyncError::where('entity', 'supplements')->first()->occurrences);

        // the Cards page lists the card below zero, and shows its balance in red
        $manager = \App\Models\Admin\User::forceCreate(['name' => 'Boss', 'username' => 'boss', 'password' => bcrypt('x'), 'role' => 'manager', 'active' => true]);
        $page = $this->actingAs($manager)->get('/cards')->assertOk();
        $page->assertSee('Cards below zero.')->assertSee('-4.00 kg');
        $this->assertSame([$card->id], $page->viewData('overdrawn')->pluck('id')->all());

        // a top-up brings the card back above zero: it leaves the list
        $this->push('sales', [$this->saleRow($card, ['amount_kg' => '10.00', 'total_amount' => '75.00', 'paid_amount' => '75.00'])]);
        $this->assertEquals(6, $card->balance());
        $this->assertCount(0, $this->get('/cards')->assertOk()->assertDontSee('Cards below zero.')->viewData('overdrawn'));

        // an ordinary supply has no card and no limit
        $plain = $this->supplyRow($card, ['card_id' => null]);
        $this->assertSame([$plain['id']], $this->push('supplements', [$plain])['saved']);
    }
    public function testTheTerminalMaySendThePhoneNumberOfANewCardButNeverChangesOneThatExists()
    {
        $row = $this->cardRow(['holder_phone' => '138 0000 1111']);
        $this->push('cards', [$row]);
        $this->assertSame('138 0000 1111', Card::find($row['id'])->holder_phone);

        // the web changed the phone: the terminal's push of the same card does not overwrite it
        Card::find($row['id'])->forceFill(['holder_phone' => '999', 'comment' => 'kept'])->toBase()->update(['synced' => true]);
        Card::where('id', $row['id'])->update(['holder_phone' => '999', 'comment' => 'kept']);
        $this->push('cards', [array_merge($row, ['holder_phone' => '000', 'failed_attempts' => 2])]);
        $this->assertSame('999', Card::find($row['id'])->holder_phone);
        $this->assertSame('kept', Card::find($row['id'])->comment);

        // it is optional, and limited
        $other = $this->cardRow(['uid' => 'BB02']);
        $this->assertSame([$other['id']], $this->push('cards', [$other])['saved']);
        $this->assertNull(Card::find($other['id'])->holder_phone);
        $result = $this->push('cards', [$this->cardRow(['uid' => 'CC03', 'holder_phone' => str_repeat('9', 31)])]);
        $this->assertSame([], $result['saved']);
    }

    public function testAFreePaymentCardIsPulledWithItsFlagAndItsTopUpMayBeForNothing()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
        $free = ['price' => '0.00', 'total_amount' => '0.00', 'paid_amount' => '0.00'];

        // a normal card: a top-up for nothing is refused, as every sale without a price
        $nothing = $this->saleRow($card, $free);
        $result = $this->push('sales', [$nothing]);
        $this->assertSame([], $result['saved']);
        $this->assertCount(1, $result['rejected']);

        // the flag goes down with the card
        $this->assertFalse($this->payload($this->get('/api/pull/cards')->assertOk())['rows'][0]['free_payment']);
        $card->forceFill(['free_payment' => true])->save();
        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $row = $this->payload($this->get('/api/pull/cards')->assertOk())['rows'][0];
        $this->assertTrue($row['free_payment']);

        // a free payment card: the top-up for nothing is saved; the other rules stay (cash, no debt)
        $this->assertSame([$nothing['id']], $this->push('sales', [$nothing])['saved']);
        $this->assertEquals(40, $card->balance());
        $this->assertCount(1, $this->push('sales', [$this->saleRow($card, $free + ['payment_method' => 'bank'])])['rejected']);
        $spot = $this->saleRow($card, $free + ['sale_type' => 'spot', 'card_id' => null, 'customer_id' => null]);
        $this->assertSame([], $this->push('sales', [$spot])['saved']);                                   // only a card sale may be for nothing

        // the terminal never changes the flag, a card it pushes again keeps it
        $this->push('cards', [['id' => $card->id, 'uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D', 'failed_attempts' => 0, 'status' => 'active'] + CardPin::make('1234')]);
        $this->assertTrue((bool) $card->fresh()->free_payment);
    }

    // the row of a ticket made for a card, as the terminal pushes it: no sale, the card
    private function cardTicketRow($card, $offset, $kg = '15.00', $overrides = [])
    {
        return $overrides + [
            'barcode' => \App\Support\Tickets\Barcode::makeReserved('25', config('ticket_serial.device_base') + $offset), 'fuel_type' => 'D', 'amount_kg' => $kg,
            'status' => config('ticket_statuses.sale'), 'sale_id' => null, 'sale_date' => '2026-10-04 10:00:00', 'supply_date' => null, 'supplement_id' => null,
            'card_id' => $card->id,
        ];
    }

    public function testATicketMadeForACardIsPushedAndTheCardPaysForItEvenWhenItHoldsLess()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
        $this->push('sales', [$this->saleRow($card)]);                                    // 40 kg topped up
        $this->assertEquals(40, $card->balance());

        $row = $this->cardTicketRow($card, 1);
        $this->assertSame([$row['barcode']], $this->push('tickets', [$row])['saved']);
        $ticket = \App\Models\Tickets\Ticket::find($row['barcode']);
        $this->assertSame($card->id, $ticket->card_id);
        $this->assertNull($ticket->sale_id);
        $this->assertSame('25', $ticket->code);
        $this->assertEquals(25, $card->balance());                                       // the ledger has the fill
        $this->assertSame(['topup', 'fill'], \App\Models\Cards\CardTransaction::where('card_id', $card->id)->orderBy('seq')->pluck('type')->all());

        // sent again, and then as supplied: nothing is counted twice
        $this->push('tickets', [$row]);
        $this->push('tickets', [array_merge($row, ['status' => config('ticket_statuses.supply'), 'supply_date' => '2026-10-04 11:00:00'])]);
        $this->assertEquals(25, $card->balance());
        $this->assertSame(config('ticket_statuses.supply'), (int) $ticket->fresh()->status);

        // the card pays with kg it does not hold: the ticket is stored (the fuel is a fact) and the staff see it among the sync problems
        $big = $this->cardTicketRow($card, 2, '30.00');
        $this->assertSame([$big['barcode']], $this->push('tickets', [$big])['saved']);
        $this->assertEquals(-5, $card->balance());
        $this->assertSame(1, \App\Models\Admin\SyncError::where('entity', 'tickets')->where('record_id', $big['barcode'])->count());
    }

    public function testATicketMadeForACardThatBreaksTheRulesIsRefused()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
        $noSale = $this->cardTicketRow($card, 1, '15.00', ['card_id' => null]);

        $bad = [
            $noSale,                                                                                 // no card and no sale: a sold ticket needs its sale
            $this->cardTicketRow($card, 2, '15.00', ['card_id' => (string) Str::uuid()]),             // an unknown card
            $this->cardTicketRow($card, 3, '15.00', ['fuel_type' => 'A']),                            // not the fuel type of the card
            $this->cardTicketRow($card, 4, '15.00', ['barcode' => \App\Support\Tickets\Barcode::makeReserved('21', config('ticket_serial.device_base') + 4)]),   // not a card code
            $this->cardTicketRow($card, 5, '0'),                                                      // no weight
        ];
        $result = $this->push('tickets', $bad);

        $this->assertSame([], $result['saved']);
        $this->assertCount(5, $result['rejected']);
        $this->assertSame(0, \App\Models\Tickets\Ticket::count());
        $this->assertSame(0, \App\Models\Cards\CardTransaction::count());
    }

    public function testTheTicketsAreDownloadedWithTheirCard()
    {
        $card = Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
        $row = $this->cardTicketRow($card, 1);
        $this->push('tickets', [$row]);
        \App\Models\Tickets\Ticket::where('barcode', $row['barcode'])->update(['synced' => false]);
        Carbon::setTestNow(Carbon::now()->addSeconds(10));

        $page = $this->payload($this->get('/api/pull/tickets')->assertOk());

        $this->assertSame($card->id, collect($page['rows'])->firstWhere('barcode', $row['barcode'])['card_id']);
    }
}
