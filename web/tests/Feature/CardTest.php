<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\FuelType;
use App\Models\Sale;
use App\Models\Supplement;
use App\Models\Ticket;
use App\Models\User;
use App\Support\CardPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7.5]);
        FuelType::forceCreate(['fuel_type' => 'A', 'price' => 9]);
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function card($uid, $name, $fuel = 'D', $extra = [])
    {
        return Card::forceCreate($extra + ['uid' => $uid, 'holder_name' => $name, 'fuel_type' => $fuel] + CardPin::make('1234'));
    }

    // a top-up sale of a card: ($card, $kg, $date, $extra)
    private function topUp($card, $kg, $date = null, $extra = [])
    {
        return Sale::forceCreate($extra + [
            'card_id' => $card->id, 'sale_type' => 'card', 'fuel_type' => $card->fuel_type, 'price' => 2, 'amount_kg' => $kg, 'payment_method' => 'cash',
            'total_amount' => $kg * 2, 'paid_amount' => $kg * 2, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => $date ?: today()->toDateString(), 'user_id' => 1,
        ]);
    }

    // a supply made with a card
    private function supply($card, $kg, $date = null)
    {
        $day = $date ?: today()->toDateString();

        return Supplement::forceCreate([
            'card_id' => $card->id, 'fuel_type' => $card->fuel_type, 'nozzle_id' => 1, 'preset_kg' => $kg, 'out_amount_lt' => $kg, 'out_amount_kg' => $kg,
            'supplied_at' => $day . ' 11:00:00', 'supply_date' => $day,
        ]);
    }

    public function testTheBalanceIsWhatWasToppedUpLessWhatWasSuppliedWithTheCard()
    {
        $card = $this->card('AA01', 'Li Wei');
        $other = $this->card('BB02', 'Wang Fang', 'A');
        $this->topUp($card, 100);
        $this->topUp($card, 50);
        $this->topUp($card, 999)->delete();                  // a deleted top-up does not count
        $this->supply($card, 30.5);
        $this->supply($card, 10);
        $this->topUp($other, 20);

        $this->assertEquals(109.5, $card->balance());
        $this->assertEquals(20, $other->balance());
        $balances = Card::balances();
        $this->assertEquals(109.5, $balances[$card->id]);
        $this->assertEquals(20, $balances[$other->id]);
        $this->assertSame(0, $this->card('CC03', 'New')->balance() <=> 0);                          // a card without records holds nothing
    }

    public function testThePermissions()
    {
        $card = $this->card('AA01', 'Li Wei');
        foreach (['manager', 'accountant'] as $role) {
            $this->as($role)->get('/cards')->assertOk();
            $this->as($role)->get('/card-topups')->assertOk();
            $this->as($role)->get('/card-summary')->assertOk();
            $this->as($role)->get('/cards/' . $card->id . '/pin')->assertOk();
        }
        foreach (['assistant', 'cashier'] as $role) {
            $this->as($role)->get('/cards')->assertForbidden();
            $this->as($role)->get('/card-topups')->assertForbidden();
            $this->as($role)->get('/card-summary')->assertForbidden();
            $this->as($role)->post('/cards/' . $card->id . '/block')->assertForbidden();
        }
        // the manager tops up on the web, the accountant does not
        $this->as('manager')->get('/card-topups/create')->assertOk();
        $this->as('accountant')->get('/card-topups/create')->assertForbidden();
        $this->as('accountant')->post('/card-topups', ['card_id' => $card->id, 'amount_kg' => 1, 'price' => 1])->assertForbidden();
        $this->assertSame(0, Sale::where('sale_type', 'card')->count());
        // the card sales are seen with the other sales
        foreach (['manager', 'accountant', 'assistant'] as $role) {
            $this->as($role)->get('/card-sales')->assertOk();
        }
        $this->as('cashier')->get('/card-sales')->assertForbidden();
    }

    public function testTheCardListShowsTheBalanceAndHasFilters()
    {
        $a = $this->card('AA01', 'Li Wei', 'D');
        $b = $this->card('BB02', 'Wang Fang', 'A', ['status' => 'blocked']);
        $c = $this->card('CC03', 'Li Na', 'D', ['status' => 'locked', 'failed_attempts' => 5]);
        $this->topUp($a, 40);
        $this->supply($a, 15);

        $names = function ($query) {
            return $this->as('accountant')->get('/cards' . $query)->assertOk()->viewData('cards')->pluck('holder_name')->all();
        };
        $this->assertSame(['Li Na', 'Li Wei', 'Wang Fang'], $names(''));                          // by name
        $this->assertSame(['Li Na', 'Li Wei'], $names('?q=li'));
        $this->assertSame(['Wang Fang'], $names('?q=BB02'));                                      // by card id
        $this->assertSame(['Wang Fang'], $names('?fuel_type=A'));
        $this->assertSame(['Li Na'], $names('?status=locked'));
        $this->assertSame(['Li Na'], $names('?q=li&status=locked&fuel_type=D'));                  // together
        $this->as('accountant')->get('/cards?status=nowhere')->assertSessionHasErrors('status');

        $html = $this->as('accountant')->get('/cards')->assertOk()->getContent();
        $this->assertRegExp('/<td>Li Wei<\/td>\s*<td><\/td>\s*<td>AA01<\/td>\s*<td>Diesel<\/td>\s*<td class="text-right"><a [^>]*>25\.00<\/a><\/td>/', $html);
        $this->assertStringContainsString('Locked (wrong PIN)', $html);
        $this->assertStringContainsString('5 wrong PINs', $html);
        $this->assertStringContainsString('Blocked (lost)', $html);
        foreach (['q', 'fuel_type', 'status'] as $name) {
            $this->assertRegExp('/name="' . $name . '" form="card-filters"/', $html, $name);
        }
        // the actions of a card in a drop-down: only what fits its state
        $this->assertStringContainsString(route('cards.unlock', $c->id), $html);                   // locked: unlock
        $this->assertStringNotContainsString(route('cards.unlock', $a->id), $html);
        $this->assertStringContainsString(route('cards.unblock', $b->id), $html);                  // blocked: unblock
        $this->assertStringNotContainsString(route('cards.block', $b->id), $html);
        $this->assertStringContainsString(route('cards.block', $a->id), $html);
        $this->assertStringNotContainsString(route('card-topups.create', ['card' => $b->id]), $this->as('accountant')->get('/cards')->getContent()); // accountant: no top-up
        $this->assertStringContainsString(route('card-topups.create', ['card' => $a->id]), $this->as('manager')->get('/cards')->getContent());
        $this->assertStringNotContainsString(route('card-topups.create', ['card' => $b->id]), $this->as('manager')->get('/cards')->getContent());  // a blocked card: no top-up
    }

    public function testALockedCardIsUnlockedAndALostCardBlockedAndUnblocked()
    {
        $locked = $this->card('AA01', 'Li Wei', 'D', ['status' => 'locked', 'failed_attempts' => 5]);
        Card::where('id', $locked->id)->toBase()->update(['synced' => true]);

        $this->as('accountant')->post('/cards/' . $locked->id . '/unlock')->assertRedirect('/cards');
        $card = $locked->fresh();
        $this->assertSame('active', $card->status);
        $this->assertSame(0, (int) $card->failed_attempts);
        $this->assertFalse((bool) $card->synced);                                                    // the Smart210 pulls it

        $this->post('/cards/' . $locked->id . '/unlock')->assertSessionHasErrors('card');           // it is not locked any more

        $this->post('/cards/' . $locked->id . '/block')->assertRedirect('/cards');
        $this->assertSame('blocked', $locked->fresh()->status);
        $this->post('/cards/' . $locked->id . '/block')->assertSessionHasErrors('card');
        $this->post('/cards/' . $locked->id . '/unlock')->assertSessionHasErrors('card');           // a lost card is not unlocked, it is unblocked

        $this->post('/cards/' . $locked->id . '/unblock')->assertRedirect('/cards');
        $this->assertSame('active', $locked->fresh()->status);
        $this->post('/cards/' . $locked->id . '/unblock')->assertSessionHasErrors('card');
    }

    public function testThePinIsResetWithADigitsOnlyPinAndUnlocksTheCard()
    {
        $locked = $this->card('AA01', 'Li Wei', 'D', ['status' => 'locked', 'failed_attempts' => 5]);
        $lost = $this->card('BB02', 'Wang Fang', 'D', ['status' => 'blocked', 'failed_attempts' => 2]);

        foreach (['12', '123456789', 'abcd', ''] as $bad) {
            $this->as('accountant')->post('/cards/' . $locked->id . '/pin', ['pin' => $bad])->assertSessionHasErrors('pin');
        }
        $this->assertTrue(CardPin::check($locked->fresh(), '1234'));                                // unchanged

        $this->post('/cards/' . $locked->id . '/pin', ['pin' => '987654'])->assertRedirect('/cards');
        $card = $locked->fresh();
        $this->assertTrue(CardPin::check($card, '987654'));
        $this->assertFalse(CardPin::check($card, '1234'));
        $this->assertSame(64, strlen($card->pin_hash));                                              // a salted SHA-256, as the Smart210 checks it
        $this->assertSame(hash('sha256', $card->pin_salt . '987654'), $card->pin_hash);
        $this->assertSame('active', $card->status);                                                  // the lock is lifted
        $this->assertSame(0, (int) $card->failed_attempts);
        $this->assertFalse((bool) $card->synced);

        $this->post('/cards/' . $lost->id . '/pin', ['pin' => '4321'])->assertRedirect('/cards');
        $this->assertSame('blocked', $lost->fresh()->status);                                        // a lost card stays blocked
        $this->assertSame(0, (int) $lost->fresh()->failed_attempts);
    }

    public function testTheManagerTopsUpACardWithTheFuelPriceThatCanBeChanged()
    {
        $card = $this->card('AA01', 'Li Wei', 'D');
        Card::where('id', $card->id)->toBase()->update(['synced' => true]);

        // the form: the card with its fuel, the price of the fuel as the default for a script
        $html = $this->as('manager')->get('/card-topups/create?card=' . $card->id)->assertOk()->getContent();
        $this->assertStringContainsString('data-price="7.50"', $html);
        $this->assertRegExp('/<option value="' . $card->id . '"[^>]*selected/s', $html);
        $this->assertStringContainsString('A card is paid in cash only', $html);

        $this->post('/card-topups', ['card_id' => $card->id, 'amount_kg' => '40', 'price' => '8.20'])->assertRedirect('/card-topups');

        $sale = Sale::where('sale_type', 'card')->first();
        $this->assertSame($card->id, $sale->card_id);
        $this->assertNull($sale->customer_id);                                                       // the card has the name, not a customer
        $this->assertSame('D', $sale->fuel_type);                                                    // the card's
        $this->assertEquals(40, $sale->amount_kg);
        $this->assertEquals(8.2, $sale->price);                                                      // typed, not the price of the fuel
        $this->assertEquals(328, $sale->total_amount);
        $this->assertEquals(328, $sale->paid_amount);                                                // paid in full: no debt
        $this->assertEquals(0, $sale->unpaid_amount);
        $this->assertSame('cash', $sale->payment_method);
        $this->assertSame('sale', $sale->usage);
        $this->assertSame(today()->toDateString(), substr((string) $sale->sale_date, 0, 10));
        $this->assertSame(0, \App\Models\Debt::count());
        $this->assertEquals(40, $card->fresh()->balance());
        $this->assertFalse((bool) $card->fresh()->synced);                                           // the Smart210 pulls the new balance

        $page = $this->get('/card-topups')->assertOk()->getContent();
        $this->assertRegExp('/<td>Li Wei<\/td>\s*<td>AA01<\/td>\s*<td>Diesel<\/td>\s*<td class="text-right">40\.00<\/td>\s*<td class="text-right">8\.20<\/td>\s*<td class="text-right">328\.00<\/td>/', $page);
    }

    public function testATopUpIsRefusedForABlockedCardAndForBadValues()
    {
        $lost = $this->card('AA01', 'Li Wei', 'D', ['status' => 'blocked']);
        $locked = $this->card('BB02', 'Wang Fang', 'D', ['status' => 'locked']);

        $this->as('manager');
        $this->post('/card-topups', ['card_id' => $lost->id, 'amount_kg' => 5, 'price' => 1])->assertSessionHasErrors('card_id');
        $this->post('/card-topups', ['card_id' => 'nope', 'amount_kg' => 5, 'price' => 1])->assertSessionHasErrors('card_id');
        $this->post('/card-topups', ['card_id' => $locked->id, 'amount_kg' => 0, 'price' => 1])->assertSessionHasErrors('amount_kg');
        $this->post('/card-topups', ['card_id' => $locked->id, 'amount_kg' => 5, 'price' => -1])->assertSessionHasErrors('price');
        $this->post('/card-topups', ['card_id' => $locked->id, 'amount_kg' => 5])->assertSessionHasErrors('price');
        $this->assertSame(0, Sale::where('sale_type', 'card')->count());

        // a locked card (wrong PINs) can still be topped up
        $this->post('/card-topups', ['card_id' => $locked->id, 'amount_kg' => 5, 'price' => 1])->assertRedirect('/card-topups');
        $this->assertSame(1, Sale::where('sale_type', 'card')->count());
        // the blocked card is not offered in the form
        $this->assertStringNotContainsString($lost->id, $this->get('/card-topups/create')->getContent());
    }

    public function testTheTopUpsAndCardSalesListsFilterByDateAndCard()
    {
        $a = $this->card('AA01', 'Li Wei');
        $b = $this->card('BB02', 'Wang Fang');
        $one = $this->topUp($a, 10, '2026-10-01');
        $two = $this->topUp($b, 20, '2026-10-01');
        $three = $this->topUp($a, 30, '2026-10-02');
        Sale::forceCreate(['sale_type' => 'spot', 'fuel_type' => 'D', 'price' => 1, 'amount_kg' => 99, 'payment_method' => 'cash', 'total_amount' => 99, 'paid_amount' => 99,
            'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => '2026-10-01', 'user_id' => 1]);      // not a card sale

        foreach (['/card-topups' => 'topUps', '/card-sales' => 'sales'] as $path => $var) {
            $ids = function ($query) use ($path, $var) {
                return $this->as('manager')->get($path . $query)->assertOk()->viewData($var)->pluck('id')->all();
            };
            $this->assertEqualsCanonicalizing([$one->id, $two->id, $three->id], $ids(''), $path);
            $this->assertEqualsCanonicalizing([$one->id, $two->id], $ids('?sale_date=2026-10-01'), $path);
            $this->assertEqualsCanonicalizing([$one->id, $three->id], $ids('?card=' . $a->id), $path);
            $this->assertSame([$one->id], $ids('?card=' . $a->id . '&sale_date=2026-10-01'), $path);
        }
        $response = $this->as('manager')->get('/card-topups?card=' . $a->id)->assertOk();
        $this->assertEquals(40, $response->viewData('totalKg'));
        $this->assertRegExp('/id="topup-card-filter" class="[^"]*js-select2/', $response->getContent());
        $this->as('manager')->get('/card-sales?card=nope')->assertSessionHasErrors('card');
    }

    public function testOnlyTheManagerDeletesACardSaleAndOnlyWhileTheBalanceStaysAboveZero()
    {
        $card = $this->card('AA01', 'Li Wei');
        $sale = $this->topUp($card, 50);

        // the actions: the manager sees Delete, the accountant and the assistant do not
        $this->assertStringContainsString(route('card-sales.destroy', $sale->id), $this->as('manager')->get('/card-sales')->getContent());
        foreach (['accountant', 'assistant'] as $role) {
            $this->assertStringNotContainsString(route('card-sales.destroy', $sale->id), $this->as($role)->get('/card-sales')->getContent(), $role);
            $this->as($role)->delete('/card-sales/' . $sale->id)->assertForbidden();
            $this->assertNotNull(Sale::find($sale->id), $role);
        }
        // the generic sale route does not delete it either (the manager has sales.delete)
        $this->as('manager')->delete('/ticket-sales/' . $sale->id)->assertSessionHasErrors('sale');
        $this->assertStringContainsString('Card Sales page', session('errors')->first('sale'));
        $this->assertNotNull(Sale::find($sale->id));

        // used: 30 kg were supplied, deleting 50 would leave -30
        $this->supply($card, 30);
        $this->delete('/card-sales/' . $sale->id)->assertSessionHasErrors('sale');
        $this->assertStringContainsString('below zero', session('errors')->first('sale'));
        $this->assertNotNull(Sale::find($sale->id));
        $this->assertStringContainsString('fa-lock', $this->get('/card-sales')->getContent());      // shown locked, with the reason

        // a top-up whose kg were not touched can go: the balance stays at 0 or more
        $second = $this->topUp($card, 40);                                                            // balance 60
        $this->delete('/card-sales/' . $second->id)->assertRedirect('/card-sales');
        $this->assertNull(Sale::find($second->id));
        $this->assertNotNull(Sale::withTrashed()->find($second->id));                                 // kept for review
        $this->assertEquals(20, $card->fresh()->balance());
        $this->assertFalse((bool) $card->fresh()->synced);

        // confirmed or too old: never
        $confirmed = $this->topUp($card, 5, null, ['confirmed_at' => now(), 'confirmed_by' => 1]);
        $this->delete('/card-sales/' . $confirmed->id)->assertSessionHasErrors('sale');
        $this->assertStringContainsString('confirmed', session('errors')->first('sale'));
        $old = $this->topUp($card, 5, today()->subDays(config('sales.delete_days') + 1)->toDateString());
        $this->delete('/card-sales/' . $old->id)->assertSessionHasErrors('sale');
        $this->assertStringContainsString('too old', session('errors')->first('sale'));
    }

    public function testACardSaleIsOnlyOpenedOnItsSalePageAndCannotBeReturned()
    {
        $card = $this->card('AA01', 'Li Wei');
        $sale = $this->topUp($card, 50);

        $html = $this->as('manager')->get('/ticket-sales/' . $sale->id)->assertOk()->getContent();
        $this->assertStringContainsString('Li Wei (AA01)', $html);                                    // the card's holder, not a customer
        $this->assertStringContainsString('Card Sales', $html);
        // a top-up is never refunded: no return
        $this->as('manager')->get('/ticket-sales/' . $sale->id . '/return')->assertNotFound();
        $this->post('/ticket-sales/' . $sale->id . '/return', ['all' => 1])->assertNotFound();
        $this->assertSame(0, Sale::where('reverses_sale_id', $sale->id)->count());
    }

    public function testTheSummarySumsEachCardForThePeriod()
    {
        $a = $this->card('AA01', 'Li Wei', 'D');
        $b = $this->card('BB02', 'Wang Fang', 'A');
        $this->card('CC03', 'Idle');                                                                   // nothing at all: left out
        // before the period: 100 topped up, 30 used; in it: 50 topped up (price 2) and 20 used; after it: 999
        $this->topUp($a, 100, '2026-09-20');
        $this->supply($a, 30, '2026-09-25');
        $this->topUp($a, 50, '2026-10-05');
        $this->supply($a, 20, '2026-10-06');
        $this->topUp($a, 999, '2026-11-01');
        $this->topUp($b, 8, '2026-10-07');

        $response = $this->as('accountant')->get('/card-summary?from=2026-10-01&to=2026-10-31')->assertOk();
        $rows = collect($response->viewData('rows'))->keyBy(function ($row) {
            return $row['card']->uid;
        });

        $this->assertSame(['AA01', 'BB02'], $rows->keys()->all());                                  // by name; the idle card is left out
        $this->assertEquals(['opening' => 70, 'topped_kg' => 50, 'topped_amount' => 100, 'used_kg' => 20, 'closing' => 100],
            collect($rows['AA01'])->only(['opening', 'topped_kg', 'topped_amount', 'used_kg', 'closing'])->all());
        $this->assertEquals(['opening' => 0, 'topped_kg' => 8, 'topped_amount' => 16, 'used_kg' => 0, 'closing' => 8],
            collect($rows['BB02'])->only(['opening', 'topped_kg', 'topped_amount', 'used_kg', 'closing'])->all());

        $html = $response->getContent();
        $this->assertRegExp('/<td>Li Wei<\/td>\s*<td>AA01<\/td>\s*<td>Diesel<\/td>\s*<td class="text-right">70\.00<\/td>\s*<td class="text-right">50\.00<\/td>\s*<td class="text-right">100\.00<\/td>\s*<td class="text-right">20\.00<\/td>\s*<td class="text-right">100\.00<\/td>/', $html);
        $this->assertStringContainsString('Total Diesel', $html);
        $this->assertStringContainsString('Total Gasoline', $html);

        // the export: one sheet with the same figures and the totals of each fuel type
        $this->assertStringContainsString('card-summary/export?from=2026-10-01&amp;to=2026-10-31', $html);
        $file = $this->get('/card-summary/export?from=2026-10-01&to=2026-10-31')->assertOk();
        $file->assertHeader('content-type', \App\Support\XlsxWriter::MIME);
        $this->assertStringContainsString('cards-summary-2026-10-01_2026-10-31.xlsx', $file->headers->get('content-disposition'));
        $zip = new \ZipArchive();
        $zip->open($file->baseResponse->getFile()->getPathname());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertFalse($zip->getFromName('xl/worksheets/sheet2.xml'));
        $zip->close();
        $this->assertRegExp('#<row r="1">.*Customer name.*Card ID.*Fuel type.*Opening balance.*Topped up.*Used.*Closing balance.*</row>#s', $sheet);
        $this->assertRegExp('#<row r="2">.*Li Wei.*AA01.*Diesel.*<v>70</v>.*<v>50</v>.*<v>100</v>.*<v>20</v>.*<v>100</v>.*</row>#s', $sheet);
        $this->assertStringContainsString('Total Diesel', $sheet);

        $this->get('/card-summary?from=2026-10-31&to=2026-10-01')->assertSessionHasErrors('to');
    }

    public function testCardsManagementIsAMenuAndCardSalesIsInSalesManagement()
    {
        $html = $this->as('manager')->get('/')->assertOk()->getContent();

        $group = strpos($html, '>Cards Management<');
        $this->assertNotFalse($group);
        $last = $group;
        foreach (['/cards', '/card-topups', '/card-summary'] as $path) {
            $at = strpos($html, 'href="' . url($path) . '"', $last);
            $this->assertNotFalse($at, $path);
            $last = $at;
        }
        $sales = strpos($html, '>Sales Management<');
        $this->assertTrue($sales < strpos($html, 'href="' . url('/card-sales') . '"') && strpos($html, 'href="' . url('/card-sales') . '"') < $group);
        $this->assertStringContainsString('>Card Sales<', $html);

        // the cashier sees none of it, the assistant only Card Sales
        $this->assertStringNotContainsString('Cards Management', $this->as('cashier')->get('/')->assertOk()->getContent());
        $assistant = $this->as('assistant')->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('Cards Management', $assistant);
        $this->assertStringContainsString('>Card Sales<', $assistant);

        // each page marks only itself in the menu
        foreach (['/cards', '/card-topups', '/card-summary', '/card-sales'] as $path) {
            $page = $this->as('manager')->get($path)->assertOk()->getContent();
            $this->assertRegExp('/<a href="' . preg_quote(url($path), '/') . '" class="nav-link active">/', $page, $path);
            $this->assertSame(1, preg_match_all('/<a href="' . preg_quote(url('/card'), '/') . '[^"]*" class="nav-link active">/', $page), $path);
        }
    }

    public function testTheDailyConfirmationSummaryHasCardSalesAndCardSupplements()
    {
        $a = $this->card('AA01', 'Li Wei', 'D');
        $b = $this->card('BB02', 'Wang Fang', 'A');
        $this->topUp($a, 40, '2026-10-01', ['price' => 7.5, 'total_amount' => 300, 'paid_amount' => 300]);
        $this->topUp($a, 10, '2026-10-01', ['price' => 8, 'total_amount' => 80, 'paid_amount' => 80]);
        $this->topUp($b, 5, '2026-10-01', ['total_amount' => 45, 'paid_amount' => 45]);
        $this->topUp($a, 99, '2026-10-02');                                                                    // another day
        $this->supply($a, 12, '2026-10-01');
        $this->supply($b, 3, '2026-10-01');
        $this->supply($a, 77, '2026-10-02');

        $response = $this->as('accountant')->get('/confirmations/summary?date=2026-10-01')->assertOk();
        $summary = $response->viewData('summary');

        // the sales: the top-ups are a kind of their own, by fuel, cash and fully paid
        $this->assertEquals(['count' => 2, 'kg' => 50, 'total' => 380, 'paid' => 380, 'unpaid' => 0], $summary['salesBy']['card']['D']);
        $this->assertEquals(['count' => 1, 'kg' => 5, 'total' => 45, 'paid' => 45, 'unpaid' => 0], $summary['salesBy']['card']['A']);
        $this->assertEquals(0, $summary['salesBy']['spot']['D']['count']);                          // not counted as spot sales
        // the supplements: those made with a card are the kind Card (they have no tickets)
        $this->assertEquals(1, $summary['supplementsBy']['card']['D']['count']);
        $this->assertEquals(12, $summary['supplementsBy']['card']['D']['kg']);
        $this->assertEquals(1, $summary['supplementsBy']['card']['A']['count']);
        $this->assertEquals(3, $summary['supplementsBy']['card']['A']['kg']);
        $this->assertEquals(0, $summary['supplementsBy']['ticket']['D']['count']);

        $html = $response->getContent();
        $this->assertStringContainsString('<h6 class="mt-3">Card Sales</h6>', $html);
        $this->assertRegExp('/<td>Card<\/td>\s*<td class="text-right border-left">1<\/td>\s*<td class="text-right">[\d.]+<\/td>\s*<td class="text-right">3\.00<\/td>/', $html);   // gasoline: 1 supply of 3 kg
    }

    public function testACardSupplementOfTheDayIsConfirmedWithTheOthers()
    {
        \Illuminate\Support\Facades\Bus::fake();   // confirming the whole day queues a dump of the database: not here
        $a = $this->card('AA01', 'Li Wei', 'D');
        $supply = $this->supply($a, 12, '2026-10-01');
        $top = $this->topUp($a, 40, '2026-10-01');

        $this->as('accountant')->post('/confirmations', ['date' => '2026-10-01', 'from' => 'sales', 'tab' => 'card', 'sales' => [$top->id]])
            ->assertRedirect(route('confirmations.index', ['date' => '2026-10-01']) . '#tab-card');
        $this->assertNotNull($top->fresh()->confirmed_at);

        $this->post('/confirmations', ['date' => '2026-10-01', 'from' => 'supplements', 'supplements' => [$supply->id]])->assertRedirect();
        $this->assertNotNull($supply->fresh()->confirmed_at);
        // a confirmed top-up is no longer deleted
        $this->as('manager')->delete('/card-sales/' . $top->id)->assertSessionHasErrors('sale');
    }

    public function testTheDetailOfASupplyMadeWithACardNamesTheCard()
    {
        $card = $this->card('AA01', 'Li Wei');
        $supply = $this->supply($card, 12);
        $plain = Supplement::forceCreate(['fuel_type' => 'D', 'nozzle_id' => 1, 'preset_kg' => 5, 'out_amount_lt' => 5, 'out_amount_kg' => 5, 'supplied_at' => today()->toDateString() . ' 12:00:00', 'supply_date' => today()->toDateString()]);

        $html = $this->as('manager')->get('/supplements/' . $supply->id . '?modal=1')->assertOk()->getContent();
        $this->assertStringContainsString('Li Wei (AA01)', $html);
        $this->assertStringNotContainsString('Li Wei', $this->get('/supplements/' . $plain->id . '?modal=1')->assertOk()->getContent());
    }

    public function testTheHolderNamePhoneAndCommentOfACardAreChangedOnTheWeb()
    {
        $card = $this->card('AA01', 'Li Wei');
        $this->assertNull($card->holder_phone);
        $hash = $card->pin_hash;

        $this->as('manager')->get('/cards/' . $card->id . '/edit')->assertOk()->assertSee('Li Wei');
        $this->put('/cards/' . $card->id, ['holder_name' => 'Li Wei Jr', 'holder_phone' => '+86 138 0000 1111', 'comment' => 'company truck'])->assertRedirect('/cards');

        $card = $card->fresh();
        $this->assertSame('Li Wei Jr', $card->holder_name);
        $this->assertSame('+86 138 0000 1111', $card->holder_phone);
        $this->assertSame('company truck', $card->comment);
        $this->assertFalse($card->synced);                    // the new name goes down to the terminal with the next pull
        $this->assertSame('AA01', $card->uid);                // the card id, the fuel type and the PIN are not touched
        $this->assertSame($hash, $card->pin_hash);

        // the list shows them, and the search finds a card by the phone number too
        $this->get('/cards')->assertSee('Li Wei Jr')->assertSee('+86 138 0000 1111')->assertSee('company truck');
        $this->get('/cards?q=0000+1111')->assertSee('Li Wei Jr');
        $this->get('/cards?q=nobody')->assertDontSee('Li Wei Jr');

        // a name is needed; the lengths are limited
        $this->put('/cards/' . $card->id, ['holder_name' => '', 'holder_phone' => '1'])->assertSessionHasErrors('holder_name');
        $this->put('/cards/' . $card->id, ['holder_name' => 'X', 'holder_phone' => str_repeat('1', 31)])->assertSessionHasErrors('holder_phone');
        $this->put('/cards/' . $card->id, ['holder_name' => 'X', 'comment' => str_repeat('c', 256)])->assertSessionHasErrors('comment');
        $this->assertSame('Li Wei Jr', $card->fresh()->holder_name);

        // the accountant edits too; the assistant and the cashier do not
        $this->as('accountant')->put('/cards/' . $card->id, ['holder_name' => 'Li Wei Jr', 'holder_phone' => '', 'comment' => ''])->assertRedirect('/cards');
        $this->assertNull(\DB::table('cards')->where('id', $card->id)->value('comment') ?: null);
        foreach (['assistant', 'cashier'] as $role) {
            $this->as($role)->get('/cards/' . $card->id . '/edit')->assertForbidden();
            $this->as($role)->put('/cards/' . $card->id, ['holder_name' => 'Hacked'])->assertForbidden();
        }
        $this->assertSame('Li Wei Jr', $card->fresh()->holder_name);
    }
}
