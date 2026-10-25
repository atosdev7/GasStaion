<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\Nozzle;
use App\Models\Supplements\Remainder;
use App\Models\Sales\Sale;
use App\Models\Admin\SyncError;
use App\Models\Supplements\Supplement;
use App\Models\Fuel\Tank;
use App\Models\Tickets\Ticket;
use App\Models\Admin\User;
use App\Support\Tickets\Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\RegistersDevice;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class PushApiTest extends TestCase
{
    use RefreshDatabase, RegistersDevice;

    private $cashier;
    private $customer;
    private $nozzle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->cashier = User::forceCreate([
            'name' => 'Cash', 'username' => 'cash', 'password' => bcrypt('secret1'), 'role' => 'cashier', 'active' => true,
        ]);
        $this->registerDevice();

        $this->customer = Customer::forceCreate(['name' => 'ACME']);
        $tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D']);
        $this->nozzle = Nozzle::forceCreate(['device_id' => Device::first()->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => 'D']);
        FuelInbound::forceCreate([
            'tank_id' => $tank->id, 'fuel_type' => 'D', 'amount_kg' => 1000, 'cost' => 2.5, 'inbound_date' => '2026-10-01',
            'user_id' => 1, 'is_default' => true,
        ]);
    }

    private function payload($response)
    {
        return json_decode($response->getContent(), true);
    }

    private function push($table, array $rows)
    {
        return $this->payload($this->postJson('/api/push/' . $table, ['rows' => $rows])->assertOk());
    }

    private function sale($overrides = [])
    {
        return $overrides + [
            'id' => (string) Str::uuid(), 'created_at' => '2026-10-06 09:00:00', 'customer_id' => $this->customer->id,
            'sale_type' => 'spot', 'fuel_type' => 'D', 'price' => '2.00', 'amount_kg' => '10.00', 'payment_method' => 'cash',
            'total_amount' => '20.00', 'paid_amount' => '20.00', 'unpaid_amount' => '0.00', 'receipt' => null,
            'usage' => 'sale', 'comment' => null, 'sale_date' => '2026-10-06', 'user_id' => $this->cashier->id,
        ];
    }

    private function supplement($overrides = [])
    {
        return $overrides + [
            'id' => (string) Str::uuid(), 'fuel_type' => 'D', 'nozzle_number' => 1, 'out_amount_lt' => '12.00',
            'out_amount_kg' => '10.00', 'preset_kg' => '15.00', 'supplied_at' => '2026-10-06 09:30:00',
            'supply_date' => '2026-10-06',
        ];
    }

    private function remainder($supplementId, $overrides = [])
    {
        return $overrides + [
            'id' => (string) Str::uuid(), 'supplement_id' => $supplementId, 'ticket_barcode' => '0000025500000001',
            'fuel_type' => 'D', 'amount_kg' => '5.00', 'created_at' => '2026-10-06 09:31:00', 'remainder_date' => '2026-10-06',
        ];
    }

    public function testPushingRecordsTheUploadTime()
    {
        $this->assertNull(Device::where('name', 'POS1')->value('last_synced_at'));

        $this->push('sales', [$this->sale()]);

        $this->assertNotNull(Device::where('name', 'POS1')->value('last_synced_at'));
    }

    public function testTheReceiptOfAPushedSaleIsAYesNo()
    {
        $none = $this->sale();
        $text = $this->sale(['receipt' => 'R-7']);
        $this->push('sales', [$none, $text]);

        $this->assertFalse(Sale::find($none['id'])->receipt);
        $this->assertTrue(Sale::find($text['id'])->receipt);
    }

    public function testTheItemsOfASaleComeWithItAndAreReplacedWhenItIsSentAgain()
    {
        $row = $this->sale(['sale_type' => 'ticket', 'items' => [
            ['spec' => 0, 'start_serial' => 11, 'end_serial' => 13, 'quantity' => 3, 'amount_kg' => '15.00'],
            ['spec' => 1, 'start_serial' => 5, 'end_serial' => 5, 'quantity' => 1, 'amount_kg' => '10.00'],
        ]]);

        $this->assertSame([$row['id']], $this->push('sales', [$row])['saved']);
        $items = \App\Models\Sales\SaleItem::where('sale_id', $row['id'])->orderBy('id')->get();
        $this->assertSame([[0, 11, 13, 3], [1, 5, 5, 1]], $items->map(function ($i) { return [(int) $i->spec, (int) $i->start_serial, (int) $i->end_serial, (int) $i->quantity]; })->all());
        $this->assertEquals(15, $items->first()->amount_kg);

        // the same sale again (the cashier changed its comment): the items are replaced, not doubled
        $row['comment'] = 'changed';
        $row['items'] = [['spec' => 0, 'start_serial' => 11, 'end_serial' => 13, 'quantity' => 3, 'amount_kg' => '15.00']];
        $this->push('sales', [$row]);
        $this->assertSame(1, \App\Models\Sales\SaleItem::where('sale_id', $row['id'])->count());

        // a sale sent without items (a spot sale, or an older terminal) is stored as before; a bad item rejects the sale
        $spot = $this->sale();
        $this->assertSame([$spot['id']], $this->push('sales', [$spot])['saved']);
        $bad = $this->sale(['items' => [['spec' => 0, 'start_serial' => 0, 'end_serial' => 3, 'quantity' => 3, 'amount_kg' => '15.00']]]);
        $result = $this->push('sales', [$bad]);
        $this->assertSame([], $result['saved']);
        $this->assertCount(1, $result['rejected']);
        $this->assertNull(Sale::find($bad['id']));
    }

    public function testAnOlderTerminalsManualReadingBecomesTheManualMode()
    {
        $hand = $this->supplement(['manual_reading' => true]);       // an older terminal still sends manual_reading
        $auto = $this->supplement(['manual_reading' => false]);
        $both = $this->supplement(['manual_reading' => true, 'mode' => 'auto']); // a mode that is sent wins

        $result = $this->push('supplements', [$hand, $auto, $both]);

        $this->assertCount(3, $result['saved']);
        $this->assertSame('manual', Supplement::find($hand['id'])->mode);
        $this->assertSame('auto', Supplement::find($auto['id'])->mode);
        $this->assertSame('auto', Supplement::find($both['id'])->mode);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('supplements', 'manual_reading'));
    }

    public function testASupplementTakesTheOutAmountsFromTheNewNamesOrTheOlderTerminalsNames()
    {
        $new = $this->supplement(['out_amount_lt' => '12.00', 'out_amount_kg' => '10.00']);
        $old = $this->supplement();
        unset($old['out_amount_lt'], $old['out_amount_kg']);
        $old['liters_dispensed'] = '24.00';                // what an older terminal calls the litres and the kg that came out
        $old['amount_kg'] = '8.00';

        $result = $this->push('supplements', [$new, $old]);

        $this->assertEqualsCanonicalizing([$new['id'], $old['id']], $result['saved']);
        $this->assertEquals([12, 10], [Supplement::find($new['id'])->out_amount_lt, Supplement::find($new['id'])->out_amount_kg]);
        $this->assertEquals([24, 8], [Supplement::find($old['id'])->out_amount_lt, Supplement::find($old['id'])->out_amount_kg]);
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('supplements');
        $this->assertNotContains('amount_kg', $columns);
        $this->assertNotContains('liters_dispensed', $columns);
    }

    public function testASupplementKeepsTheModeTheTerminalSendsAndIsAutoWithoutOne()
    {
        $manual = $this->supplement(['mode' => 'manual']);
        $auto = $this->supplement(['mode' => 'auto']);
        $none = $this->supplement();                       // an older terminal sends no mode
        $bad = $this->supplement(['mode' => 'semi']);

        $result = $this->push('supplements', [$manual, $auto, $none, $bad]);

        $this->assertEqualsCanonicalizing([$manual['id'], $auto['id'], $none['id']], $result['saved']);
        $this->assertCount(1, $result['rejected']);
        $this->assertSame('manual', Supplement::find($manual['id'])->mode);
        $this->assertSame('auto', Supplement::find($auto['id'])->mode);
        $this->assertSame('auto', Supplement::find($none['id'])->mode);
        $this->assertNull(Supplement::find($bad['id']));
    }

    public function testASupplementTakesItsDayFromSupplyDateOrFromTheOldNameDate()
    {
        $new = $this->supplement(['supply_date' => '2026-10-05']);
        $old = $this->supplement();                       // an older terminal sends `date`, not `supply_date`
        unset($old['supply_date']);
        $old['date'] = '2026-10-04';

        $result = $this->push('supplements', [$new, $old]);

        $this->assertEqualsCanonicalizing([$new['id'], $old['id']], $result['saved']);
        $this->assertSame('2026-10-05', substr((string) Supplement::find($new['id'])->supply_date, 0, 10));
        $this->assertSame('2026-10-04', substr((string) Supplement::find($old['id'])->supply_date, 0, 10));
    }

    public function testASupplementHasTheDensityTheTerminalSendsOrWhatItsLitresAndKgGive()
    {
        $sent = $this->supplement(['density' => '0.8333', 'liters_dispensed' => '12.00', 'amount_kg' => '10.00']);
        $none = $this->supplement(['liters_dispensed' => '12.00', 'amount_kg' => '10.00']);     // an older terminal sends no density

        $this->push('supplements', [$sent, $none]);

        $this->assertEquals(0.8333, Supplement::find($sent['id'])->density);
        $this->assertEquals(0.8333, Supplement::find($none['id'])->density);                     // 10 / 12
    }

    public function testARemainderTakesItsMomentAndDayFromTheNewNamesOrTheOlderTerminalsNames()
    {
        $supplement = $this->supplement();
        $this->push('supplements', [$supplement]);
        $new = $this->remainder($supplement['id'], ['ticket_barcode' => '0000025500000011', 'comment' => 'typed at the terminal']);
        $old = $this->remainder($supplement['id'], ['ticket_barcode' => '0000025500000012']);
        unset($old['created_at'], $old['remainder_date']);
        $old['remainder_at'] = '2026-10-05 08:00:00';      // an older terminal
        $old['date'] = '2026-10-05';

        $result = $this->push('remainders', [$new, $old]);

        $this->assertEqualsCanonicalizing([$new['id'], $old['id']], $result['saved']);
        $this->assertSame('typed at the terminal', Remainder::find($new['id'])->comment);
        $this->assertNull(Remainder::find($old['id'])->comment);                          // an older terminal sends none
        $this->assertSame('2026-10-06 09:31:00', (string) Remainder::find($new['id'])->created_at);
        $this->assertSame('2026-10-06', substr((string) Remainder::find($new['id'])->remainder_date, 0, 10));
        $this->assertSame('2026-10-05 08:00:00', (string) Remainder::find($old['id'])->created_at);
        $this->assertSame('2026-10-05', substr((string) Remainder::find($old['id'])->remainder_date, 0, 10));
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('remainders');
        $this->assertSame(['id', 'supplement_id', 'ticket_barcode', 'fuel_type', 'amount_kg', 'comment', 'remainder_date', 'complete_date', 'created_at', 'updated_at'], $columns);
    }

    public function testPushNeedsARegisteredAddressAndAKnownTableAndAnEnvelope()
    {
        $this->fromAStranger('POST', '/api/push/sales', ['rows' => []])->assertStatus(403);

        $this->postJson('/api/push/nothing', ['rows' => []])->assertStatus(404);
        $this->postJson('/api/push/sales', [])->assertStatus(422);
        $this->postJson('/api/push/sales', ['rows' => 'x'])->assertStatus(422);
        $this->postJson('/api/push/sales', ['rows' => ['x']])->assertStatus(422);
        $this->postJson('/api/push/sales', ['rows' => array_fill(0, 201, [])])->assertStatus(422);
    }

    public function testASaleIsStoredWithTheWebsCostAndIsNotSentBack()
    {
        $row = $this->sale();

        $result = $this->push('sales', [$row]);

        $this->assertSame([$row['id']], $result['saved']);
        $this->assertSame([], $result['rejected']);
        $sale = Sale::find($row['id']);
        $this->assertEquals(2.5, $sale->cost); // from the default inbound, never from the device
        $this->assertEquals(20, $sale->total_amount);
        $this->assertSame('spot', $sale->sale_type);
        $this->assertSame('2026-10-06', $sale->sale_date);
        $this->assertSame('2026-10-06 09:00:00', (string) $sale->created_at); // the time of the sale at the terminal
        $this->assertTrue($sale->synced);
    }

    public function testOwnUseAndOtherUseFromTheTerminalAreTheIndividualCustomer()
    {
        $individual = Customer::where('is_default', true)->first();
        $rows = [];
        foreach (['self', 'other', 'sale'] as $usage) {
            $free = $usage === 'sale' ? [] : ['price' => '0.00', 'total_amount' => '0.00', 'paid_amount' => '0.00'];
            $rows[$usage] = $this->sale(['usage' => $usage] + $free); // (the terminal sent ACME for all three)
        }

        $result = $this->push('sales', array_values($rows));

        $this->assertSame([], $result['rejected']);
        $this->assertSame($individual->id, Sale::find($rows['self']['id'])->customer_id);
        $this->assertSame($individual->id, Sale::find($rows['other']['id'])->customer_id);
        $this->assertSame($this->customer->id, Sale::find($rows['sale']['id'])->customer_id);
    }

    public function testPushingTheSameSaleAgainKeepsOneRowAndItsCost()
    {
        $row = $this->sale();
        $this->push('sales', [$row]);
        FuelInbound::query()->update(['cost' => 9]); // the default cost moves on

        $this->push('sales', [array_merge($row, ['comment' => 'edited at the pump'])]);

        $this->assertSame(1, Sale::count());
        $sale = Sale::find($row['id']);
        $this->assertEquals(2.5, $sale->cost);
        $this->assertSame('edited at the pump', $sale->comment);
    }

    public function testAConfirmedSaleIsFinalAndAPushAgainChangesNothing()
    {
        $row = $this->sale();
        $this->push('sales', [$row]);
        Sale::whereKey($row['id'])->update(['confirmed_at' => now(), 'confirmed_by' => 1]);

        $result = $this->push('sales', [array_merge($row, ['amount_kg' => '99.00'])]);

        $this->assertSame([$row['id']], $result['saved']); // still acknowledged, so the device stops retrying
        $sale = Sale::find($row['id']);
        $this->assertEquals(10, $sale->amount_kg);
        $this->assertNotNull($sale->confirmed_at);
    }

    public function testAConfirmedSupplementIsFinalToo()
    {
        $row = $this->supplement();
        $this->push('supplements', [$row]);
        Supplement::whereKey($row['id'])->update(['confirmed_at' => now(), 'confirmed_by' => 1]);

        $result = $this->push('supplements', [array_merge($row, ['liters_dispensed' => '1.00', 'amount_kg' => '1.00'])]);

        $this->assertSame([$row['id']], $result['saved']);
        $supplement = Supplement::find($row['id']);
        $this->assertEquals(12, $supplement->out_amount_lt);
        $this->assertNotNull($supplement->confirmed_at);
    }

    public function testACommentOrDateChangedAfterTheUploadIsStoredAgain()
    {
        $sale = $this->sale();
        $this->push('sales', [$sale]);

        $result = $this->push('sales', [array_merge($sale, ['comment' => 'paid by card', 'sale_date' => '2026-10-05'])]);

        $this->assertSame([$sale['id']], $result['saved']);
        $stored = Sale::find($sale['id']);
        $this->assertSame('paid by card', $stored->comment);
        $this->assertSame('2026-10-05', substr((string) $stored->sale_date, 0, 10));
    }

    public function testAConfirmedRecordRefusesANewCommentOrDateWithTheReason()
    {
        $sale = $this->sale(['comment' => 'first']);
        $supplement = $this->supplement();
        $this->push('sales', [$sale]);
        $this->push('supplements', [$supplement]);
        Sale::whereKey($sale['id'])->update(['confirmed_at' => now(), 'confirmed_by' => 1]);
        Supplement::whereKey($supplement['id'])->update(['confirmed_at' => now(), 'confirmed_by' => 1]);

        $same = $this->push('sales', [$sale]); // the same data again is fine
        $moved = $this->push('sales', [array_merge($sale, ['sale_date' => '2026-10-05'])]);
        $noted = $this->push('supplements', [array_merge($supplement, ['comment' => 'late note'])]);

        $this->assertSame([$sale['id']], $same['saved']);
        $this->assertSame([], $moved['saved']);
        $this->assertStringContainsString('confirmed by the accountant', $moved['rejected'][0]['error']);
        $this->assertSame([], $noted['saved']);
        $this->assertStringContainsString('confirmed by the accountant', $noted['rejected'][0]['error']);
        $this->assertSame('first', Sale::find($sale['id'])->comment); // nothing changed
        $this->assertNull(Supplement::find($supplement['id'])->comment);
    }

    public function testASupplyWhoseConfirmationWasTakenBackTakesTheCorrectionOfTheCashier()
    {
        $row = $this->supplement(['comment' => 'first']);
        $this->push('supplements', [$row]);
        Supplement::whereKey($row['id'])->update(['confirmed_at' => now(), 'confirmed_by' => 1]);
        $corrected = array_merge($row, ['comment' => 'wrong nozzle written', 'supply_date' => '2026-10-05', 'out_amount_lt' => '11.00', 'out_amount_kg' => '9.00']);

        // confirmed: refused, and the reason says how it goes on
        $refused = $this->push('supplements', [$corrected]);
        $this->assertSame([], $refused['saved']);
        $this->assertStringContainsString('take the confirmation back', $refused['rejected'][0]['error']);
        $this->assertSame('first', Supplement::find($row['id'])->comment);

        // the manager or the accountant took the confirmation back on the web: the corrected record is taken
        Supplement::whereKey($row['id'])->update(['confirmed_at' => null, 'confirmed_by' => null]);
        $taken = $this->push('supplements', [$corrected]);
        $this->assertSame([$row['id']], $taken['saved']);
        $stored = Supplement::find($row['id']);
        $this->assertSame('wrong nozzle written', $stored->comment);
        $this->assertSame('2026-10-05', substr((string) $stored->supply_date, 0, 10));
        $this->assertEquals(11, $stored->out_amount_lt);
        $this->assertEquals(9, $stored->out_amount_kg);
    }

    public function testSupplementsLoansAndSettlementsKeepTheirComment()
    {
        $supplement = $this->supplement(['comment' => 'manual fill']);
        $this->push('supplements', [$supplement]);

        $this->assertSame('manual fill', Supplement::find($supplement['id'])->comment);
    }

    public function testRejectedRowsAreReportedAndLoggedWithoutBlockingTheRest()
    {
        $good = $this->sale();
        $unknownCustomer = $this->sale(['customer_id' => 999999]);
        $unpaid = $this->sale(['paid_amount' => '5.00', 'unpaid_amount' => '15.00']);

        $result = $this->push('sales', [$good, $unknownCustomer, $unpaid]);

        $this->assertSame([$good['id']], $result['saved']);
        $this->assertSame([$unknownCustomer['id'], $unpaid['id']], array_column($result['rejected'], 'key'));
        $this->assertSame(1, Sale::count());

        $errors = SyncError::orderBy('id')->get();
        $this->assertCount(2, $errors);
        $this->assertSame('sales', $errors[0]->entity);
        $this->assertSame(Device::first()->id, (int) $errors[0]->device_id);
        $this->assertSame($unknownCustomer['id'], $errors[0]->record_id);
        $this->assertNotSame('', $errors[0]->reason);
        $this->assertStringContainsString($unknownCustomer['id'], $errors[0]->payload);
        $this->assertStringContainsString('unpaid', $errors[1]->reason);
    }

    public function testARowThatFailsUnexpectedlyIsReportedAndTheOthersAreStillSaved()
    {
        Sale::creating(function ($sale) {
            if ($sale->comment === 'boom') {
                throw new \RuntimeException('lock wait timeout');
            }
        });
        $first = $this->sale();
        $poison = $this->sale(['comment' => 'boom']);
        $last = $this->sale();

        $result = $this->push('sales', [$first, $poison, $last]);

        $this->assertSame([$first['id'], $last['id']], $result['saved']);
        $this->assertSame([$poison['id']], array_column($result['rejected'], 'key'));
        $this->assertStringContainsString('lock wait timeout', $result['rejected'][0]['error']);
        $this->assertSame(2, Sale::count());
        $this->assertSame(1, SyncError::where('record_id', $poison['id'])->count());
    }

    public function testASaleTakesTheCostThatWasTheDefaultWhenItWasMade()
    {
        $old = FuelInbound::first();
        $old->forceFill(['default_from' => '2026-10-01 00:00:00', 'default_until' => '2026-10-08 08:00:00', 'is_default' => false])->save();
        FuelInbound::forceCreate([
            'tank_id' => $old->tank_id, 'fuel_type' => 'D', 'amount_kg' => 1000, 'cost' => 3.0, 'inbound_date' => '2026-10-08',
            'user_id' => 1, 'is_default' => true, 'default_from' => '2026-10-08 08:00:00',
        ]);
        $before = $this->sale(['sale_date' => '2026-10-07']);
        $after = $this->sale(['sale_date' => '2026-10-08']);

        // both arrive on the 10th, after the default changed
        $this->push('sales', [$before, $after]);

        $this->assertEquals(2.5, Sale::find($before['id'])->cost);
        $this->assertEquals(3.0, Sale::find($after['id'])->cost);
    }

    public function testARowTheDeviceKeepsRetryingIsLoggedOnceAndCounted()
    {
        $row = $this->sale(['customer_id' => 999999]);

        $this->push('sales', [$row]);
        $this->push('sales', [$row]);
        $third = $this->push('sales', [$row]);

        $this->assertSame([$row['id']], array_column($third['rejected'], 'key')); // still told every time
        $this->assertSame(1, SyncError::count());
        $this->assertSame(3, (int) SyncError::first()->occurrences);
    }

    public function testTotalsThatDoNotAddUpAreRejected()
    {
        $row = $this->sale(['paid_amount' => '10.00']); // 10 + 0 is not 20

        $result = $this->push('sales', [$row]);

        $this->assertSame([], $result['saved']);
        $this->assertStringContainsString('total', $result['rejected'][0]['error']);
    }

    public function testARowWithoutAnIdIsRejectedWithANullKey()
    {
        $row = $this->sale();
        unset($row['id']);

        $result = $this->push('sales', [$row]);

        $this->assertNull($result['rejected'][0]['key']);
        $this->assertSame(1, SyncError::count());
        $this->assertNull(SyncError::first()->record_id);
    }

    public function testASupplementTakesItsDeviceFromTheToken()
    {
        $row = $this->supplement(['device_id' => 999]);

        $result = $this->push('supplements', [$row]);

        $this->assertSame([$row['id']], $result['saved']);
        $supplement = Supplement::find($row['id']);
        $this->assertSame(Device::first()->id, (int) $supplement->device_id); // not the 999 the row claimed
        $this->assertEquals(10, $supplement->out_amount_kg);
        $this->assertEquals(15, $supplement->preset_kg);
        $this->assertTrue($supplement->synced);
    }

    public function testASupplementCannotDispenseMoreThanItsTicketsCover()
    {
        $result = $this->push('supplements', [$this->supplement(['out_amount_kg' => '16.00'])]);

        $this->assertSame([], $result['saved']);
        $this->assertStringContainsString('tickets', $result['rejected'][0]['error']);
        $this->assertSame(0, Supplement::count());
    }

    public function testASupplementMayFallShortOfItsTicketsButNeverExceedThem()
    {
        $within = $this->supplement(['out_amount_kg' => '14.95']);   // the station keeps 0.05 kg: fine
        $over = $this->supplement(['out_amount_kg' => '15.01']);     // more than the tickets cover: refused

        $result = $this->push('supplements', [$within, $over]);

        $this->assertSame([$within['id']], $result['saved']);
        $this->assertSame($over['id'], $result['rejected'][0]['key']);
    }

    public function testASupplementNeedsANozzleOfItsOwnDevice()
    {
        $result = $this->push('supplements', [$this->supplement(['nozzle_number' => 9])]);

        $this->assertSame([], $result['saved']);
        $this->assertStringContainsString('nozzle', $result['rejected'][0]['error']);
        $this->assertSame(0, Supplement::count());
    }

    public function testASupplementIsStoredAgainstTheNozzleWithThatNumber()
    {
        $result = $this->push('supplements', [$this->supplement()]);

        $this->assertCount(1, $result['saved']);
        $this->assertSame($this->nozzle->id, Supplement::first()->nozzle_id);
    }

    public function testNozzlesAreCreatedChangedAndDeletedByThePush()
    {
        $tank = Tank::forceCreate(['name' => 'T2', 'fuel_type' => 'A']);
        $device = Device::first();

        $result = $this->push('nozzles', [
            ['number' => 2, 'tank_id' => $tank->id, 'board_address' => 2, 'deleted' => false],
            ['number' => 1, 'tank_id' => $tank->id, 'board_address' => 7, 'deleted' => false],
        ]);

        $this->assertSame(['2', '1'], $result['saved']);
        $this->assertSame([], $result['rejected']);
        $two = Nozzle::where('device_id', $device->id)->where('number', 2)->first();
        $this->assertSame('A', $two->fuel_type); // the tank's fuel type
        $this->assertSame(2, (int) $two->board_address);
        $one = Nozzle::find($this->nozzle->id); // the same row: found by device and number
        $this->assertSame($tank->id, (int) $one->tank_id);
        $this->assertSame(7, (int) $one->board_address);
        $this->assertSame(2, Nozzle::count());

        $again = $this->push('nozzles', [['number' => 2, 'tank_id' => $tank->id, 'board_address' => 2, 'deleted' => false]]);
        $this->assertSame(['2'], $again['saved']);
        $this->assertSame(2, Nozzle::count()); // sending a row again changes nothing

        $this->push('nozzles', [['number' => 2, 'tank_id' => $tank->id, 'board_address' => 2, 'deleted' => true]]);
        $this->assertSame(1, Nozzle::count());
        $this->assertSame(2, Nozzle::withTrashed()->count()); // soft deleted: old supplements still point at it

        $this->push('nozzles', [['number' => 2, 'tank_id' => $tank->id, 'board_address' => 3, 'deleted' => false]]);
        $this->assertSame(2, Nozzle::count()); // the number is used again: the same row comes back
        $this->assertSame(2, Nozzle::withTrashed()->count());
    }

    public function testADeletedNozzleStillTakesItsOldSupplements()
    {
        $this->push('nozzles', [['number' => 1, 'tank_id' => $this->nozzle->tank_id, 'board_address' => null, 'deleted' => true]]);

        $result = $this->push('supplements', [$this->supplement()]);

        $this->assertCount(1, $result['saved']);
        $this->assertSame($this->nozzle->id, Supplement::first()->nozzle_id);
    }

    public function testABadNozzleRowIsRejected()
    {
        $result = $this->push('nozzles', [
            ['number' => 0, 'tank_id' => $this->nozzle->tank_id, 'board_address' => 1, 'deleted' => false],
            ['number' => 3, 'tank_id' => 999999, 'board_address' => 1, 'deleted' => false],
            ['number' => 4, 'tank_id' => $this->nozzle->tank_id, 'board_address' => 300, 'deleted' => false],
        ]);

        $this->assertSame([], $result['saved']);
        $this->assertCount(3, $result['rejected']);
        $this->assertSame(1, Nozzle::count());
    }

    public function testNozzlesOfAnotherDeviceAreNotTouched()
    {
        $other = Device::forceCreate(['name' => 'POS2', 'ip_address' => '10.9.9.9', 'purge_days' => 3]);
        Nozzle::forceCreate(['device_id' => $other->id, 'number' => 1, 'tank_id' => $this->nozzle->tank_id, 'fuel_type' => 'D', 'board_address' => 5]);

        $this->push('nozzles', [['number' => 1, 'tank_id' => $this->nozzle->tank_id, 'board_address' => 9, 'deleted' => true]]);

        $this->assertSame(5, (int) Nozzle::where('device_id', $other->id)->first()->board_address);
        $this->assertNull(Nozzle::find($this->nozzle->id)); // only this device's number 1 was deleted
    }

    public function testARemainderWaitsForItsSupplement()
    {
        $supplement = $this->supplement();
        $remainder = $this->remainder($supplement['id']);

        $early = $this->push('remainders', [$remainder]);
        $this->assertSame([], $early['saved']);
        $this->assertSame(0, Remainder::count());

        $this->push('supplements', [$supplement]);
        $later = $this->push('remainders', [$remainder]); // the same row, retried

        $this->assertSame([$remainder['id']], $later['saved']);
        $stored = Remainder::find($remainder['id']);
        $this->assertSame($supplement['id'], $stored->supplement_id);
        $this->assertSame('0000025500000001', $stored->ticket_barcode);
        $this->assertEquals(5, $stored->amount_kg);
    }

    // A press ticket the device already holds (so its synced flag is true on the server).
    private function pressTicket($serial, $status)
    {
        $ticket = Ticket::forceCreate([
            'barcode' => sprintf('0000006%09d', $serial), 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => $serial,
            'status' => $status,
        ]);
        Ticket::whereKey($ticket->barcode)->toBase()->update(['synced' => true]);

        return $ticket;
    }

    private function deviceBarcode($offset = 1, $code = '21')
    {
        return Barcode::makeReserved($code, config('ticket_serial.device_base') + $offset);
    }

    // A sale on the server, as a push of it would have stored it: the tickets of a sale are only accepted when their sale is there.
    private function storedSale($id)
    {
        Sale::forceCreate([
            'id' => $id, 'customer_id' => $this->customer->id, 'sale_type' => 'spot', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 5,
            'payment_method' => 'cash', 'total_amount' => 10, 'paid_amount' => 10, 'unpaid_amount' => 0, 'usage' => 'sale',
            'sale_date' => '2026-10-06', 'user_id' => $this->cashier->id,
        ]);

        return $id;
    }

    private function storedSupplement($id)
    {
        Supplement::forceCreate([
            'id' => $id, 'fuel_type' => 'D', 'nozzle_id' => $this->nozzle->id, 'device_id' => $this->nozzle->device_id, 'out_amount_lt' => 6,
            'out_amount_kg' => 5, 'preset_kg' => 5, 'supplied_at' => '2026-10-06 09:30:00', 'supply_date' => '2026-10-06',
        ]);

        return $id;
    }

    private function ticketRow($barcode, $overrides = [])
    {
        $row = $overrides + [
            'barcode' => $barcode, 'fuel_type' => 'D', 'amount_kg' => '5.00', 'status' => config('ticket_statuses.sale'),
            'sale_id' => (string) Str::uuid(), 'sale_date' => '2026-10-06 10:00:00', 'supply_date' => null, 'supplement_id' => null,
        ];
        if ($row['sale_id'] && !Sale::withTrashed()->whereKey($row['sale_id'])->exists()) {
            $this->storedSale($row['sale_id']);
        }
        if ($row['supplement_id'] && !Supplement::withTrashed()->whereKey($row['supplement_id'])->exists()) {
            $this->storedSupplement($row['supplement_id']);
        }

        return $row;
    }

    private function supplyRow($barcode, $supplementId = null)
    {
        return $this->ticketRow($barcode, [
            'status' => config('ticket_statuses.supply'), 'supply_date' => '2026-10-06 11:00:00',
            'supplement_id' => $supplementId ?: (string) Str::uuid(),
        ]);
    }

    private function conflicts()
    {
        return SyncError::where('entity', 'tickets')->where('reason', 'like', 'conflict:%')->count();
    }

    public function testADeviceCreatesItsOwnTicket()
    {
        $row = $this->ticketRow($this->deviceBarcode(1), ['amount_kg' => '7.50']);

        $result = $this->push('tickets', [$row]);

        $this->assertSame([$row['barcode']], $result['saved']);
        $ticket = Ticket::find($row['barcode']);
        $this->assertSame('21', $ticket->code);
        $this->assertSame(config('ticket_serial.device_base') + 1, (int) $ticket->serial);
        $this->assertEquals(7.5, $ticket->amount_kg);
        $this->assertNull($ticket->spec);
        $this->assertSame($row['sale_id'], $ticket->sale_id);
        $this->assertSame(config('ticket_statuses.sale'), (int) $ticket->status);
        $this->assertTrue((bool) $ticket->synced); // the device has it already: not sent back
    }

    public function testARemainderTicketNeedsNoSale()
    {
        $row = $this->ticketRow($this->deviceBarcode(1, '25'), ['sale_id' => null, 'sale_date' => '2026-10-06 11:01:00']);

        $this->assertSame([$row['barcode']], $this->push('tickets', [$row])['saved']);
        $this->assertNull(Ticket::find($row['barcode'])->sale_id);
    }

    public function testADeviceCannotInventOtherTickets()
    {
        $rows = [
            $this->ticketRow('0000006000000099'), // a press barcode nobody published
            $this->ticketRow(Barcode::makeReserved('21', 5)), // below the device range
            $this->ticketRow('9999921' . sprintf('%09d', config('ticket_serial.device_base') + 2)), // wrong sign
            $this->ticketRow($this->deviceBarcode(3), ['status' => config('ticket_statuses.publish')]), // a device cannot publish
            $this->ticketRow($this->deviceBarcode(4), ['status' => config('ticket_statuses.restrict')]),
            $this->ticketRow($this->deviceBarcode(5), ['amount_kg' => null]), // no amount
            $this->ticketRow($this->deviceBarcode(6), ['sale_id' => null]), // sold with no sale
        ];

        $result = $this->push('tickets', $rows);

        $this->assertSame([], $result['saved']);
        $this->assertCount(7, $result['rejected']);
        $this->assertSame(0, Ticket::count());
    }

    public function testTheTicketsOfASaleThatIsNotOnTheServerAreRefusedAndWaitForIt()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.publish'));
        $saleId = (string) Str::uuid();
        $row = [
            'barcode' => $ticket->barcode, 'fuel_type' => 'D', 'amount_kg' => null, 'status' => config('ticket_statuses.sale'),
            'sale_id' => $saleId, 'sale_date' => '2026-10-06 10:00:00', 'supply_date' => null, 'supplement_id' => null,
        ];

        // the sale was refused (it is not stored), its ticket is refused too and stays in stock
        $result = $this->push('tickets', [$row]);
        $this->assertSame([], $result['saved']);
        $this->assertStringContainsString('its sale is not on the server', json_encode($result['rejected']));
        $this->assertSame(config('ticket_statuses.publish'), (int) Ticket::find($ticket->barcode)->status);
        $this->assertNull(Ticket::find($ticket->barcode)->sale_id);

        // the sale is accepted later: the same ticket is accepted on its next upload
        $this->push('sales', [$this->sale(['id' => $saleId, 'sale_type' => 'ticket'])]);
        $this->assertSame([$ticket->barcode], $this->push('tickets', [$row])['saved']);
        $this->assertSame(config('ticket_statuses.sale'), (int) Ticket::find($ticket->barcode)->status);
        $this->assertSame($saleId, Ticket::find($ticket->barcode)->sale_id);
    }

    public function testASuppliedTicketWaitsForItsSupplementToo()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.sale'));
        $supplementId = (string) Str::uuid();
        $row = [
            'barcode' => $ticket->barcode, 'fuel_type' => 'D', 'amount_kg' => null, 'status' => config('ticket_statuses.supply'),
            'sale_id' => null, 'sale_date' => null, 'supply_date' => '2026-10-06 11:00:00', 'supplement_id' => $supplementId,
        ];

        $this->assertSame([], $this->push('tickets', [$row])['saved']);
        $this->assertSame(config('ticket_statuses.sale'), (int) Ticket::find($ticket->barcode)->status);

        $this->push('supplements', [$this->supplement(['id' => $supplementId])]);
        $this->assertSame([$ticket->barcode], $this->push('tickets', [$row])['saved']);
        $this->assertSame(config('ticket_statuses.supply'), (int) Ticket::find($ticket->barcode)->status);
    }

    public function testATicketAlreadySoldIsNotRefusedWhenItsSaleWasClearedByABackup()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.sale'));
        $row = [
            'barcode' => $ticket->barcode, 'fuel_type' => 'D', 'amount_kg' => null, 'status' => config('ticket_statuses.sale'),
            'sale_id' => (string) Str::uuid(), 'sale_date' => '2026-10-06 10:00:00', 'supply_date' => null, 'supplement_id' => null,
        ];

        // nothing changes on the server for it, so nothing is checked: the same push after a backup stays quiet
        $this->assertSame([$ticket->barcode], $this->push('tickets', [$row])['saved']);
    }

    public function testSellingAPublishedTicketMovesItForwardWithoutSendingItBack()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.publish'));
        $row = $this->ticketRow($ticket->barcode, ['amount_kg' => null]);

        $this->assertSame([$ticket->barcode], $this->push('tickets', [$row])['saved']);

        $fresh = Ticket::find($ticket->barcode);
        $this->assertSame(config('ticket_statuses.sale'), (int) $fresh->status);
        $this->assertSame($row['sale_id'], $fresh->sale_id);
        $this->assertSame('2026-10-06 10:00:00', (string) $fresh->sale_date);
        $this->assertTrue((bool) $fresh->synced);
    }

    public function testATicketSoldAndSuppliedBetweenTwoSyncsGetsBothSets()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.publish'));
        $row = $this->supplyRow($ticket->barcode) + $this->ticketRow($ticket->barcode);

        $this->push('tickets', [$row]);

        $fresh = Ticket::find($ticket->barcode);
        $this->assertSame(config('ticket_statuses.supply'), (int) $fresh->status);
        $this->assertSame($row['supplement_id'], $fresh->supplement_id);
        $this->assertSame($row['sale_id'], $fresh->sale_id);
        $this->assertSame(0, $this->conflicts());
    }

    public function testSupplyingASoldTicket()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.sale'));
        $row = $this->supplyRow($ticket->barcode);

        $this->push('tickets', [$row]);

        $fresh = Ticket::find($ticket->barcode);
        $this->assertSame(config('ticket_statuses.supply'), (int) $fresh->status);
        $this->assertSame($row['supplement_id'], $fresh->supplement_id);
        $this->assertSame('2026-10-06 11:00:00', (string) $fresh->supply_date);
        $this->assertTrue((bool) $fresh->synced);
        $this->assertSame(0, $this->conflicts());
    }

    public function testASaleThatArrivesAfterTheWebFinishedTheTicketIsLoggedNotApplied()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.supply'));

        $result = $this->push('tickets', [$this->ticketRow($ticket->barcode)]);

        $this->assertSame([$ticket->barcode], $result['saved']); // saved, so the device stops retrying
        $this->assertSame(config('ticket_statuses.supply'), (int) Ticket::find($ticket->barcode)->status);
        $this->assertSame(1, $this->conflicts());
    }

    public function testFuelDispensedBeatsARestrictionMadeMeanwhile()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.restrict'));

        $this->push('tickets', [$this->supplyRow($ticket->barcode)]);

        $this->assertSame(config('ticket_statuses.supply'), (int) Ticket::find($ticket->barcode)->status);
        $this->assertSame(1, $this->conflicts()); // staff can see it happened
    }

    public function testPushingTheSameSupplyAgainIsQuiet()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.sale'));
        $row = $this->supplyRow($ticket->barcode);

        $this->push('tickets', [$row]);
        $second = $this->push('tickets', [$row]);

        $this->assertSame([$ticket->barcode], $second['saved']);
        $this->assertSame(0, $this->conflicts());
        $this->assertSame($row['supplement_id'], Ticket::find($ticket->barcode)->supplement_id);
    }

    public function testASecondSupplementForTheSameTicketIsLoggedAndTheFirstKept()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.sale'));
        $first = $this->supplyRow($ticket->barcode);
        $this->push('tickets', [$first]);

        $this->push('tickets', [$this->supplyRow($ticket->barcode)]); // another supplement id

        $this->assertSame($first['supplement_id'], Ticket::find($ticket->barcode)->supplement_id);
        $this->assertSame(1, $this->conflicts());
    }

    public function testAnIncompleteSupplyOrSaleIsRejected()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.sale'));
        $noDate = $this->supplyRow($ticket->barcode);
        $noDate['supply_date'] = null;

        $result = $this->push('tickets', [$noDate, $this->ticketRow($ticket->barcode, ['sale_date' => null])]);

        $this->assertSame([], $result['saved']);
        $this->assertCount(2, $result['rejected']);
        $this->assertSame(config('ticket_statuses.sale'), (int) Ticket::find($ticket->barcode)->status);
    }

    public function testATicketSurrenderedForALoanIsSuppliedWithoutASupplement()
    {
        $ticket = $this->pressTicket(1, config('ticket_statuses.sale'));
        $row = $this->supplyRow($ticket->barcode);
        $row['supplement_id'] = null;

        $this->assertSame([$ticket->barcode], $this->push('tickets', [$row])['saved']);

        $fresh = Ticket::find($ticket->barcode);
        $this->assertSame(config('ticket_statuses.supply'), (int) $fresh->status);
        $this->assertNull($fresh->supplement_id);
    }

    private function loanRow($overrides = [])
    {
        return $overrides + [
            'id' => (string) Str::uuid(), 'loaned_at' => '2026-10-06 08:00:00', 'customer_id' => $this->customer->id,
            'fuel_type' => 'D', 'amount_kg' => '30.00', 'sale_id' => null, 'date' => '2026-10-06',
            'user_id' => $this->cashier->id,
        ];
    }

    private function settlementRow($loanId, $overrides = [])
    {
        return $overrides + [
            'id' => (string) Str::uuid(), 'loan_id' => $loanId, 'ticket_barcode' => '0000006000000001', 'amount_kg' => '5.00',
            'settled_at' => '2026-10-06 12:00:00', 'date' => '2026-10-06', 'user_id' => $this->cashier->id,
        ];
    }

    public function testALoanIsStoredAndTheDevicesSettledFigureIsIgnored()
    {
        $row = $this->loanRow();

        $result = $this->push('ticket_loans', [$row + ['settled_amount_kg' => '99.00']]);

        $this->assertSame([$row['id']], $result['saved']);
        $loan = \App\Models\Debts\TicketLoan::find($row['id']);
        $this->assertEquals(30, $loan->amount_kg);
        $this->assertEquals(0, $loan->settled_amount_kg); // the ledger's total, not the device's
        $this->assertTrue((bool) $loan->synced);
    }

    public function testSettlementsAddUpToTheLoansSettledTotalAndAreSentBack()
    {
        $loan = $this->loanRow();
        $this->push('ticket_loans', [$loan]);
        $a = $this->settlementRow($loan['id'], ['amount_kg' => '5.00']);
        $b = $this->settlementRow($loan['id'], ['amount_kg' => '10.00']);

        $result = $this->push('ticket_loan_settlements', [$a, $b]);

        $this->assertSame([$a['id'], $b['id']], $result['saved']);
        $stored = \App\Models\Debts\TicketLoan::find($loan['id']);
        $this->assertEquals(15, $stored->settled_amount_kg);
        $this->assertFalse((bool) $stored->synced); // the new total reaches the device with its next pull
        $this->assertSame(2, \App\Models\Debts\TicketLoanSettlement::count());
    }

    public function testASettlementDoneOnTheWebCountsToo()
    {
        $loan = $this->loanRow();
        $this->push('ticket_loans', [$loan]);
        \App\Models\Debts\TicketLoanSettlement::forceCreate([
            'loan_id' => $loan['id'], 'ticket_barcode' => '0000006000000002', 'amount_kg' => 5, 'settled_at' => now(), 'user_id' => 1,
        ]);

        $this->push('ticket_loan_settlements', [$this->settlementRow($loan['id'], ['amount_kg' => '10.00'])]);

        $this->assertEquals(15, \App\Models\Debts\TicketLoan::find($loan['id'])->settled_amount_kg);
    }

    public function testAConfirmedSettlementIsFinalToo()
    {
        $loan = $this->loanRow();
        $this->push('ticket_loans', [$loan]);
        $row = $this->settlementRow($loan['id'], ['comment' => 'first']);
        $this->push('ticket_loan_settlements', [$row]);
        \App\Models\Debts\TicketLoanSettlement::whereKey($row['id'])->update(['confirmed_at' => now(), 'confirmed_by' => 1]);

        $same = $this->push('ticket_loan_settlements', [$row]);                                                       // the same data again is fine
        $moved = $this->push('ticket_loan_settlements', [array_merge($row, ['date' => '2026-10-07'])]);
        $noted = $this->push('ticket_loan_settlements', [array_merge($row, ['comment' => 'late note'])]);

        $this->assertSame([$row['id']], $same['saved']);
        $this->assertSame([], $moved['saved']);
        $this->assertStringContainsString('confirmed by the accountant', $moved['rejected'][0]['error']);
        $this->assertStringContainsString('take the confirmation back', $noted['rejected'][0]['error']);
        $stored = \App\Models\Debts\TicketLoanSettlement::find($row['id']);
        $this->assertSame('first', $stored->comment);
        $this->assertSame('2026-10-06', substr((string) $stored->date, 0, 10));

        // taken back, the correction of the cashier is taken
        \App\Models\Debts\TicketLoanSettlement::whereKey($row['id'])->update(['confirmed_at' => null, 'confirmed_by' => null]);
        $taken = $this->push('ticket_loan_settlements', [array_merge($row, ['comment' => 'corrected', 'date' => '2026-10-07'])]);
        $this->assertSame([$row['id']], $taken['saved']);
        $this->assertSame('corrected', \App\Models\Debts\TicketLoanSettlement::find($row['id'])->comment);
    }

    public function testPushingASettlementTwiceDoesNotCountItTwice()
    {
        $loan = $this->loanRow();
        $this->push('ticket_loans', [$loan]);
        $row = $this->settlementRow($loan['id']);

        $this->push('ticket_loan_settlements', [$row]);
        $this->push('ticket_loan_settlements', [$row]);

        $this->assertSame(1, \App\Models\Debts\TicketLoanSettlement::count());
        $this->assertEquals(5, \App\Models\Debts\TicketLoan::find($loan['id'])->settled_amount_kg);
    }

    public function testASettlementWaitsForItsLoan()
    {
        $loan = $this->loanRow();
        $row = $this->settlementRow($loan['id']);

        $early = $this->push('ticket_loan_settlements', [$row]);
        $this->assertSame([], $early['saved']);
        $this->assertSame(0, \App\Models\Debts\TicketLoanSettlement::count());

        $this->push('ticket_loans', [$loan]);

        $this->assertSame([$row['id']], $this->push('ticket_loan_settlements', [$row])['saved']);
    }

    public function testPushingALoanAgainKeepsItsSettledTotal()
    {
        $loan = $this->loanRow();
        $this->push('ticket_loans', [$loan]);
        $this->push('ticket_loan_settlements', [$this->settlementRow($loan['id'], ['amount_kg' => '12.00'])]);

        $this->push('ticket_loans', [$loan]);

        $this->assertEquals(12, \App\Models\Debts\TicketLoan::find($loan['id'])->settled_amount_kg);
    }

    public function testASaleFromTheTerminalNeedsAPriceAboveZero()
    {
        $free = $this->sale(['price' => '0.00', 'total_amount' => '0.00', 'paid_amount' => '0.00']);
        $own = $this->sale(['price' => '0.00', 'total_amount' => '0.00', 'paid_amount' => '0.00', 'usage' => 'self']);

        $result = $this->push('sales', [$free, $own]);

        $this->assertSame([$free['id']], array_column($result['rejected'], 'key'));
        $this->assertStringContainsString('price above 0', $result['rejected'][0]['error']);
        $this->assertNull(Sale::find($free['id']));
        $this->assertNotNull(Sale::find($own['id']));
    }}
