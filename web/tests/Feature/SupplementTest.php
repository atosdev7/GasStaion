<?php

namespace Tests\Feature;

use App\Models\Admin\Device;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Nozzle;
use App\Models\Supplements\Remainder;
use App\Models\Supplements\Supplement;
use App\Models\Fuel\Tank;
use App\Models\Tickets\Ticket;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplementTest extends TestCase
{
    use RefreshDatabase;

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    // A sold press ticket; $spec 0 = 5 kg, 1 = 10 kg, 2 = 15 kg.
    private function soldTicket($serial, $spec = 0, $fuelType = 'D')
    {
        return Ticket::forceCreate([
            'barcode' => sprintf('0000006%09d', $serial), 'fuel_type' => $fuelType, 'spec' => $spec, 'code' => '06',
            'serial' => $serial, 'status' => config('ticket_statuses.sale'), 'sale_date' => now(),
        ]);
    }

    private function nozzle($fuelType = 'D')
    {
        $device = Device::forceCreate(['name' => 'Device 1']);
        $tank = Tank::forceCreate(['name' => 'Tank 1', 'fuel_type' => $fuelType]);

        return Nozzle::forceCreate([
            'device_id' => $device->id, 'number' => 1, 'tank_id' => $tank->id, 'fuel_type' => $fuelType,
        ]);
    }

    private function density($density = 1)
    {
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 1, 'density' => $density]);
    }

    private function supply($barcodes, $nozzle, $liters, $extra = [])
    {
        return $this->as('manager')->post('/supplements', $extra + [
            'ticket_barcodes' => is_array($barcodes) ? implode("\n", $barcodes) : $barcodes,
            'nozzle_id' => $nozzle->id,
            'out_amount_lt' => $liters,
            'supplied_at' => '2026-09-20 08:30:00',
        ]);
    }

    public function testASupplementKeepsItsModeAndIsAutoWhenNoneIsSent()
    {
        $this->density(0.89);
        $nozzle = $this->nozzle('D');
        $first = $this->soldTicket(1, 0);
        $second = $this->soldTicket(2, 0);
        $third = $this->soldTicket(3, 0);

        $this->supply($first->barcode, $nozzle, 5.62)->assertRedirect('/supplements');                           // no mode sent
        $this->supply($second->barcode, $nozzle, 5.62, ['mode' => 'manual'])->assertRedirect('/supplements');
        $this->supply($third->barcode, $nozzle, 5.62, ['mode' => 'auto'])->assertRedirect('/supplements');

        $this->assertSame(['auto', 'manual', 'auto'], Supplement::orderBy('created_at')->orderBy('id')->get()
            ->sortBy(function ($s) { return $s->tickets()->first()->barcode; })->pluck('mode')->values()->all());

        // a mode that is neither is refused and nothing is saved
        $fourth = $this->soldTicket(4, 0);
        $this->supply($fourth->barcode, $nozzle, 5.62, ['mode' => 'semi'])->assertSessionHasErrors('mode');
        $this->assertSame(3, Supplement::count());
    }

    public function testTheListCanBeFilteredByDayNozzleAndMode()
    {
        $one = $this->nozzle('D');
        $two = Nozzle::forceCreate(['device_id' => $one->device_id, 'number' => 2, 'tank_id' => $one->tank_id, 'fuel_type' => 'D']);
        $make = function ($nozzle, $kg, $at, $date, $mode) {
            return Supplement::forceCreate([
                'fuel_type' => 'D', 'nozzle_id' => $nozzle->id, 'device_id' => $nozzle->device_id, 'out_amount_lt' => $kg, 'out_amount_kg' => $kg,
                'preset_kg' => $kg, 'supplied_at' => $at, 'supply_date' => $date, 'mode' => $mode,
            ]);
        };
        $make($one, 10, '2026-09-20 10:00:00', '2026-09-20', 'auto');
        $make($one, 20, '2026-09-21 10:00:00', '2026-09-21', 'manual');
        $make($two, 40, '2026-09-21 11:00:00', '2026-09-21', 'auto');
        $make($two, 80, '2026-09-22 02:00:00', '2026-09-21', 'manual');   // 02:00 of the 22nd belongs to the business day of the 21st

        $shown = function ($query) {
            $page = $this->as('manager')->get('/supplements' . $query)->assertOk();
            preg_match_all('/<tr>\s*<td>(\d{4}-\d\d-\d\d)<\/td>\s*<td>/', $page->getContent(), $m);

            return [count($m[1]), $page->getContent()];
        };

        $this->assertSame(4, $shown('')[0]);
        $this->assertSame(3, $shown('?supply_date=2026-09-21')[0]);                                    // the day is its date field, not the time stamp
        $this->assertSame(1, $shown('?supply_date=2026-09-20')[0]);
        $this->assertSame(2, $shown('?nozzle_id=' . $two->id)[0]);
        $this->assertSame(2, $shown('?mode=manual')[0]);
        $this->assertSame(1, $shown('?mode=manual&nozzle_id=' . $two->id . '&supply_date=2026-09-21')[0]);
        $this->assertSame(0, $shown('?supply_date=2026-09-19')[0]);

        // the totals are those of the filtered supplies
        $html = $shown('?mode=manual')[1];
        $this->assertStringContainsString('100.00', $html);   // 20 + 80 kg
        $this->assertStringNotContainsString('150.00', $html);

        // the filters sit in the heads of the columns and the chosen ones stay chosen
        $html = $shown('?mode=manual&nozzle_id=' . $two->id)[1];
        $this->assertStringContainsString('name="supply_date" form="supplement-filters"', $html);
        $this->assertStringContainsString('<option value="manual" selected>Manual</option>', $html);
        $this->assertStringContainsString('<option value="' . $two->id . '" selected>', $html);

        // bad values are refused
        $this->get('/supplements?supply_date=21/09/2026')->assertSessionHasErrors('supply_date');
        $this->get('/supplements?mode=semi')->assertSessionHasErrors('mode');
    }

    public function testTheListIsPagedAndKeepsItsFilters()
    {
        $nozzle = $this->nozzle('D');
        for ($i = 0; $i < 53; $i++) {
            Supplement::forceCreate([
                'fuel_type' => 'D', 'nozzle_id' => $nozzle->id, 'device_id' => $nozzle->device_id, 'out_amount_lt' => 1, 'out_amount_kg' => 1,
                'preset_kg' => 1, 'supplied_at' => '2026-09-20 10:00:00', 'supply_date' => '2026-09-20', 'mode' => 'manual',
            ]);
        }

        $first = $this->as('manager')->get('/supplements?mode=manual')->assertOk()->getContent();
        $this->assertSame(50, substr_count($first, '>Manual<') - 1);  // 50 rows (the filter's own option is the other one)
        $this->assertStringContainsString('page=2', $first);
        $this->assertStringContainsString('mode=manual', $first);
        $this->assertStringContainsString('53.00', $first);          // the total counts every page
    }

    public function testTheListHasTheSupplyDateNozzleModePresetOutAmountRemainderAndActions()
    {
        $this->density(0.89);
        $nozzle = $this->nozzle('D');
        $this->supply($this->soldTicket(1, 0)->barcode, $nozzle, 4.49, ['supplied_at' => '2026-09-20 08:30:00']); // 3.996 kg of a 5 kg ticket: a remainder

        $html = $this->as('manager')->get('/supplements')->assertOk()->getContent();
        $head = substr($html, strpos($html, '<thead>'), strpos($html, '<tr class="filters">') - strpos($html, '<thead>'));
        preg_match_all('/<th[^>]*>\s*([^<]*?)\s*<\/th>/', $head, $m);
        $this->assertSame(['Supply date', 'Nozzle', 'Mode', 'Tickets total (kg)', 'Density (kg/L)', 'Out amount (kg / L)', 'Remainder', 'Actions'], array_map('html_entity_decode', $m[1]));

        // the row: the day of the supply, the nozzle, the mode, what the tickets cover, kg / litres dispensed, the remainder ticket, the actions
        $this->assertRegExp('/<tr>\s*<td>2026-09-20<\/td>\s*<td>[^<]*<\/td>\s*<td><span class="badge badge-info">Auto<\/span><\/td>\s*<td class="text-right">5\.00<\/td>\s*<td class="text-right">0\.89<\/td>\s*<td class="text-right">4\.00 kg \/ 4\.49 L<\/td>.*?<td class="text-nowrap text-right">\s*.*?supplement-actions/s', $html);
        $this->assertStringNotContainsString('<th>Supplied at</th>', $html);
    }

    public function testASupplyCanBeViewedAsAPopupAndAsAPageWithItsTickets()
    {
        $this->density(0.89);
        $nozzle = $this->nozzle('D');
        $ticket = $this->soldTicket(1, 0);
        $this->supply($ticket->barcode, $nozzle, 4.49);
        $supplement = Supplement::first();

        $list = $this->as('manager')->get('/supplements')->assertOk()->getContent();
        $this->assertStringContainsString('href="' . url('/supplements/' . $supplement->id) . '"', $list);
        $this->assertStringContainsString('class="dropdown-item supplement-detail"', $list);

        $popup = $this->get('/supplements/' . $supplement->id . '?modal=1')->assertOk()->assertDontSee('<html', false)->assertSee($ticket->barcode)->assertSee('2026-09-20');
        $this->assertStringContainsString('Tickets used (1)', $popup->getContent());
        $this->get('/supplements/' . $supplement->id)->assertOk()->assertSee('<html', false)->assertSee('Back to the list');
        $this->get('/supplements/11111111-1111-1111-1111-111111111111')->assertNotFound();
        $this->as('cashier')->get('/supplements/' . $supplement->id)->assertForbidden();
    }

    public function testASupplementKeepsTheDensityItWasConvertedWith()
    {
        $this->density(0.89);
        $nozzle = $this->nozzle('D');
        $first = $this->soldTicket(1, 0);
        $second = $this->soldTicket(2, 0);

        // no density sent: the one of the fuel type
        $this->supply($first->barcode, $nozzle, 5.62)->assertRedirect('/supplements');
        $this->assertEquals(0.89, Supplement::first()->density);
        $this->assertEquals(5, Supplement::first()->out_amount_kg);

        // a density typed in the form is the one used, and it stays with the supply when the fuel type's density changes later
        $this->supply($second->barcode, $nozzle, 5, ['density' => '0.9'])->assertRedirect('/supplements');
        $typed = Supplement::where('density', '0.9')->first();
        $this->assertEquals(4.5, $typed->out_amount_kg);                 // 5 L x 0.9
        \App\Models\Fuel\FuelType::where('fuel_type', 'D')->update(['density' => 0.5]);
        $this->assertEquals(0.9, $typed->fresh()->density);
        $this->assertSame(1, Supplement::where('density', '0.89')->count());   // the first one kept its own

        // a bad density is refused; no density anywhere is refused too
        $this->supply($this->soldTicket(3, 0)->barcode, $nozzle, 5, ['density' => '0'])->assertSessionHasErrors('density');
        $this->supply($this->soldTicket(4, 0)->barcode, $nozzle, 5, ['density' => '99999'])->assertSessionHasErrors('density');
    }

    public function testTheFormFillsTheDensityOfTheNozzlesFuelAndTheDetailShowsIt()
    {
        $this->density(0.89);
        $nozzle = $this->nozzle('D');

        $form = $this->as('manager')->get('/supplements/create')->assertOk();
        $this->assertStringContainsString('id="f_density" name="density"', $form->getContent());
        $this->assertEquals(0.89, $form->viewData('densities')[$nozzle->id]);          // the density of its fuel type, for the form to fill in
        $this->assertStringContainsString("densities[nozzle.val()]", $form->getContent());

        $this->supply($this->soldTicket(1, 0)->barcode, $nozzle, 5.62);
        $popup = $this->get('/supplements/' . Supplement::first()->id . '?modal=1')->assertOk()->getContent();
        $this->assertRegExp('/<th>Density \(kg\/L\)<\/th><td>0\.89<\/td>/', $popup);
    }

    public function testTheFieldsOfASupplyAreInTheOrderItIsRead()
    {
        // the columns of the table (card_id: the card a supply was made with, none for tickets): its day, nozzle, mode, the preset, the density, the litres and the kg that came out, then the rest
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('supplements');
        $this->assertSame(['id', 'fuel_type', 'nozzle_id', 'device_id', 'card_id', 'supply_date', 'supplied_at', 'mode', 'preset_kg', 'density', 'out_amount_lt', 'out_amount_kg', 'comment'],
            array_slice($columns, 0, 13));
        $this->assertNotContains('manual_reading', $columns);

        // and the form follows it: when, nozzle, mode, the tickets (the preset), the density, the litres
        $form = $this->as('manager')->get('/supplements/create')->assertOk()->getContent();
        $positions = array_map(function ($id) use ($form) { return strpos($form, 'id="' . $id . '"'); },
            ['f_supplied_at', 'f_nozzle_id', 'f_mode', 'f_ticket_barcodes', 'f_density', 'f_out_amount_lt']);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertNotContains(false, $positions);
    }

    public function testTheFormTakesTheOutAmountInLitresUnderItsNewName()
    {
        $form = $this->as('manager')->get('/supplements/create')->assertOk()->getContent();
        $this->assertStringContainsString('id="f_out_amount_lt" name="out_amount_lt"', $form);
        $this->assertStringContainsString('Out amount (L)', $form);
        $this->assertStringNotContainsString('liters_dispensed', $form);
    }

    public function testARemainderIsCompleteWhenItsTicketIsUsedUp()
    {
        $this->density(0.89);
        $nozzle = $this->nozzle('D');
        $this->supply($this->soldTicket(1, 0)->barcode, $nozzle, 4.49)->assertRedirect('/supplements');   // 4.00 kg of a 5 kg ticket: 1.00 kg comes back
        $remainder = Remainder::first();
        $this->assertNull($remainder->complete_date);

        // the remainder ticket is supplied itself: its remainder is complete on the day it was used
        $this->supply($remainder->ticket_barcode, $nozzle, 1.1, ['supplied_at' => '2026-09-25 09:00:00'])->assertRedirect('/supplements');
        $this->assertSame(today()->toDateString(), substr((string) $remainder->fresh()->complete_date, 0, 10));

        // a ticket that goes back to sold takes the completion away again
        Ticket::find($remainder->ticket_barcode)->forceFill(['status' => config('ticket_statuses.sale')])->save();
        $this->assertNull($remainder->fresh()->complete_date);
    }

    public function testARemainderCanBeAddedByHandToASupplyThatGaveLessThanItsTickets()
    {
        $nozzle = $this->nozzle('D');
        $make = function ($preset, $out) use ($nozzle) {
            return Supplement::forceCreate([
                'fuel_type' => 'D', 'nozzle_id' => $nozzle->id, 'device_id' => $nozzle->device_id, 'out_amount_lt' => $out, 'out_amount_kg' => $out,
                'preset_kg' => $preset, 'supplied_at' => '2026-09-20 08:30:00', 'supply_date' => '2026-09-20',
            ]);
        };
        $short = $make(5, 3);      // 2 kg not dispensed
        $full = $make(5, 5);       // nothing left

        // the form offers only the supplies that can still give one
        $form = $this->as('manager')->get('/remainders/create')->assertOk();
        $this->assertSame([$short->id], $form->viewData('supplies')->pluck('id')->all());
        $this->assertStringContainsString('data-room="2"', $form->getContent());
        $this->assertStringContainsString('Left: 2.00 kg', $form->getContent());

        // a remainder ticket and the remainder are made, like a supply makes them
        $this->post('/remainders', ['supplement_id' => $short->id, 'amount_kg' => '1.5', 'remainder_date' => '2026-09-22', 'comment' => '  short pour, customer waited  '])->assertRedirect('/remainders')->assertSessionHas('status');
        $remainder = Remainder::first();
        $this->assertSame($short->id, $remainder->supplement_id);
        $this->assertEquals(1.5, $remainder->amount_kg);
        $this->assertSame('2026-09-22', substr((string) $remainder->remainder_date, 0, 10));
        $this->assertNull($remainder->complete_date);
        $this->assertSame('short pour, customer waited', $remainder->comment);             // the comment is kept, trimmed
        $ticket = Ticket::find($remainder->ticket_barcode);
        $this->assertSame('D', $ticket->fuel_type);
        $this->assertEquals(1.5, $ticket->amount_kg);
        $this->assertSame(config('ticket_statuses.sale'), (int) $ticket->status);          // already sold, to be used for a supply
        $this->assertSame(config('ticket_reserved_codes.remainder'), $ticket->code);
        $this->assertFalse((bool) $ticket->synced);                                         // the Smart210 gets it with its next download
        $this->get('/remainders')->assertOk()->assertSee($remainder->ticket_barcode)->assertSee('short pour, customer waited')->assertSee('Remainder ticket ' . $remainder->ticket_barcode . ' of 1.50 kg added.');
        $this->post('/remainders', ['supplement_id' => $short->id, 'amount_kg' => '1', 'comment' => str_repeat('x', 256)])->assertSessionHasErrors('comment');

        // that supply has its remainder now and is not offered again, and cannot get a second one
        $this->assertSame([], $this->get('/remainders/create')->viewData('supplies')->pluck('id')->all());
        $this->post('/remainders', ['supplement_id' => $short->id, 'amount_kg' => '0.1'])->assertSessionHasErrors('supplement_id');
        $this->post('/remainders', ['supplement_id' => $full->id, 'amount_kg' => '1'])->assertSessionHasErrors('amount_kg');   // nothing was left
        $this->assertSame(1, Remainder::count());
    }

    public function testARemainderByHandIsLimitedAndNeedsThePermission()
    {
        $nozzle = $this->nozzle('D');
        $supply = Supplement::forceCreate([
            'fuel_type' => 'D', 'nozzle_id' => $nozzle->id, 'device_id' => $nozzle->device_id, 'out_amount_lt' => 3, 'out_amount_kg' => 3,
            'preset_kg' => 5, 'supplied_at' => '2026-09-20 08:30:00', 'supply_date' => '2026-09-20',
        ]);

        $this->as('manager');
        $this->post('/remainders', ['supplement_id' => $supply->id, 'amount_kg' => '2.01'])->assertSessionHasErrors('amount_kg');                  // more than was not dispensed
        $this->post('/remainders', ['supplement_id' => $supply->id, 'amount_kg' => '0'])->assertSessionHasErrors('amount_kg');
        $this->post('/remainders', ['supplement_id' => $supply->id, 'amount_kg' => '1', 'remainder_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('remainder_date');
        $this->post('/remainders', ['supplement_id' => '11111111-1111-1111-1111-111111111111', 'amount_kg' => '1'])->assertSessionHasErrors('supplement_id');
        $this->post('/remainders', ['amount_kg' => '1'])->assertSessionHasErrors('supplement_id');
        $this->assertSame(0, Remainder::count());
        $this->assertSame(0, Ticket::count());                      // a refused remainder leaves no ticket

        // the date is today when none is sent; the whole of what was left is allowed
        $this->post('/remainders', ['supplement_id' => $supply->id, 'amount_kg' => '2'])->assertRedirect('/remainders');
        $this->assertSame(today()->toDateString(), substr((string) Remainder::first()->remainder_date, 0, 10));

        $this->as('cashier')->get('/remainders/create')->assertForbidden();
        $this->post('/remainders', ['supplement_id' => $supply->id, 'amount_kg' => '1'])->assertForbidden();

        // the button is on the list for people who may add
        $this->as('manager')->get('/remainders')->assertSee(route('remainders.create'));
    }

    public function testTheRemaindersListShowsTheRemaindersWithTheirDatesAndFilters()
    {
        $this->density(0.89);
        $nozzle = $this->nozzle('D');
        $this->supply($this->soldTicket(1, 0)->barcode, $nozzle, 4.49, ['supplied_at' => '2026-09-20 08:30:00']);
        $this->supply($this->soldTicket(2, 0)->barcode, $nozzle, 4.49, ['supplied_at' => '2026-09-21 08:30:00']);
        $used = Remainder::orderBy('created_at')->first();
        $this->supply($used->ticket_barcode, $nozzle, 1.12, ['supplied_at' => '2026-09-25 09:00:00']);

        $html = $this->as('manager')->get('/remainders')->assertOk()->getContent();
        $head = substr($html, strpos($html, '<thead>'), strpos($html, '<tr class="filters">') - strpos($html, '<thead>'));
        preg_match_all('/<th[^>]*>\s*([^<]*?)\s*<\/th>/', $head, $m);
        $this->assertSame(['Remainder date', 'Barcode', 'Fuel type', 'Weight (kg)', 'From supply', 'Complete date', 'Comment', 'Status'], array_map('html_entity_decode', $m[1]));
        $this->assertStringContainsString($used->ticket_barcode, $html);
        $this->assertStringContainsString(today()->toDateString(), $html);   // the complete date of the used one
        $this->assertSame(2, substr_count($html, 'badge-info'));            // a state of each remainder ticket
        $this->assertStringContainsString('<td>-</td>', $html);               // the other is not complete

        $count = function ($query) {
            preg_match_all('/<tr>\s*<td>\d{4}-\d\d-\d\d<\/td>/', $this->get('/remainders' . $query)->assertOk()->getContent(), $m);

            return count($m[0]);
        };
        $this->assertSame(2, $count(''));
        $this->assertSame(1, $count('?date=2026-09-21'));
        $this->assertSame(1, $count('?q=' . substr($used->ticket_barcode, -6)));
        $this->assertSame(0, $count('?fuel_type=A'));
        $this->get('/remainders?date=21/09/2026')->assertSessionHasErrors('date');

        $this->as('cashier')->get('/remainders')->assertForbidden();
    }

    public function testTheFormHasAModeAndTheListsShowIt()
    {
        $this->density(0.89);
        $nozzle = $this->nozzle('D');
        $this->supply($this->soldTicket(1, 0)->barcode, $nozzle, 5.62, ['mode' => 'manual']);
        $this->supply($this->soldTicket(2, 0)->barcode, $nozzle, 5.62);

        $form = $this->as('manager')->get('/supplements/create')->assertOk()->getContent();
        $this->assertRegExp('/<select id="f_mode" name="mode"[^>]*>\s*<option value="auto" selected>Auto<\/option>\s*<option value="manual" >Manual<\/option>/', $form);

        $list = $this->get('/supplements')->assertOk()->getContent();
        $this->assertRegExp('/<th>Nozzle<\/th>\s*<th>Mode<\/th>/', $list);
        $this->assertSame(1, substr_count($list, 'badge-warning">Manual<'));
        $this->assertSame(1, substr_count($list, 'badge-info">Auto<'));
    }

    public function testOneTicketUsedExactlyCreatesASupplementAndMarksTheTicketSupplied()
    {
        $this->density(0.89);
        $ticket = $this->soldTicket(1, 0); // 5 kg
        $nozzle = $this->nozzle('D');

        $this->supply($ticket->barcode, $nozzle, 5.62)->assertRedirect('/supplements'); // 5.62 L x 0.89 = 5.00 kg

        $supplement = Supplement::first();
        $this->assertSame('2026-09-20', $supplement->supply_date); // its own supplied date
        $this->assertSame('D', $supplement->fuel_type);
        $this->assertSame($nozzle->id, $supplement->nozzle_id);
        $this->assertSame($nozzle->device_id, $supplement->device_id);
        $this->assertEquals(5.62, $supplement->out_amount_lt);
        $this->assertEquals(5, $supplement->out_amount_kg);
        $this->assertEquals(5, $supplement->preset_kg);

        $ticket = Ticket::find($ticket->barcode);
        $this->assertSame(config('ticket_statuses.supply'), $ticket->status);
        $this->assertNotNull($ticket->supply_date);
        $this->assertSame($supplement->id, $ticket->supplement_id);
        $this->assertSame(0, Remainder::count()); // nothing left over
    }

    public function testSeveralTicketsBelongToOneSupplement()
    {
        $this->density(1);
        $a = $this->soldTicket(1, 0); // 5
        $b = $this->soldTicket(2, 0); // 5
        $c = $this->soldTicket(3, 1); // 10
        $nozzle = $this->nozzle('D');

        $this->supply([$a->barcode, $b->barcode, $c->barcode], $nozzle, 20)->assertRedirect('/supplements');

        $this->assertSame(1, Supplement::count());
        $supplement = Supplement::first();
        $this->assertEquals(20, $supplement->preset_kg);
        $this->assertEquals(20, $supplement->out_amount_kg);
        $this->assertSame(3, $supplement->tickets()->count());
        foreach ([$a, $b, $c] as $ticket) {
            $fresh = Ticket::find($ticket->barcode);
            $this->assertSame(config('ticket_statuses.supply'), $fresh->status);
            $this->assertSame($supplement->id, $fresh->supplement_id);
        }
    }

    public function testBarcodesMayBeSeparatedByNewlinesCommasOrSpaces()
    {
        $this->density(1);
        $a = $this->soldTicket(1, 0);
        $b = $this->soldTicket(2, 0);
        $c = $this->soldTicket(3, 0);
        $nozzle = $this->nozzle('D');

        $this->supply($a->barcode . ", " . $b->barcode . "\r\n" . $c->barcode . ' ', $nozzle, 15)->assertRedirect('/supplements');

        $this->assertSame(3, Supplement::first()->tickets()->count());
    }

    public function testWhatWasNotDispensedComesBackAsARemainderTicket()
    {
        $this->density(1);
        $a = $this->soldTicket(1, 0); // 5
        $b = $this->soldTicket(2, 0); // 5
        $c = $this->soldTicket(3, 0); // 5
        $nozzle = $this->nozzle('D');

        $response = $this->supply([$a->barcode, $b->barcode, $c->barcode], $nozzle, 12)->assertRedirect('/supplements');

        $supplement = Supplement::first();
        $this->assertEquals(15, $supplement->preset_kg);
        $this->assertEquals(12, $supplement->out_amount_kg);

        $remainder = Remainder::first();
        $this->assertSame($supplement->id, $remainder->supplement_id);
        $this->assertEquals(3, $remainder->amount_kg);
        $this->assertSame('D', $remainder->fuel_type);
        $this->assertSame('2026-09-20', $remainder->remainder_date);
        $this->assertSame('2026-09-20 08:30:00', (string) $remainder->created_at);   // the moment of the supply is the moment of the remainder
        $this->assertNull($remainder->complete_date);                                // not used up yet

        $ticket = Ticket::find($remainder->ticket_barcode);
        $this->assertSame('25', $ticket->code); // the reserved remainder code
        $this->assertSame(1, (int) $ticket->serial);
        $this->assertEquals(3, $ticket->amount_kg);
        $this->assertNull($ticket->spec);
        $this->assertNull($ticket->sale_id); // already paid for: no sale behind it
        $this->assertSame(config('ticket_statuses.sale'), $ticket->status);
        $this->assertFalse((bool) $ticket->synced); // the Smart210 will pull it

        $response->assertSessionHas('status', __('supplements.supplied_with_remainder', ['kg' => '12.00', 'barcode' => $ticket->barcode, 'left' => '3.00']));
        $this->assertSame($supplement->id, $supplement->remainder->supplement_id);
    }

    public function testASecondRemainderGetsTheNextSerial()
    {
        $this->density(1);
        $nozzle = $this->nozzle('D');

        $this->supply($this->soldTicket(1, 0)->barcode, $nozzle, 4);
        $this->supply($this->soldTicket(2, 0)->barcode, $nozzle, 3);

        $this->assertSame([1, 2], Ticket::where('code', '25')->orderBy('serial')->pluck('serial')->map(function ($s) {
            return (int) $s;
        })->all());
    }

    public function testCannotDispenseMoreThanTheTicketsCover()
    {
        $this->density(1);
        $ticket = $this->soldTicket(1, 0); // 5 kg
        $nozzle = $this->nozzle('D');

        $this->supply($ticket->barcode, $nozzle, 5.5)->assertSessionHasErrors('out_amount_lt');

        $this->assertSame(0, Supplement::count());
        $this->assertSame(config('ticket_statuses.sale'), Ticket::find($ticket->barcode)->status);
    }

    public function testOneBadTicketStopsTheWholeSupply()
    {
        $this->density(1);
        $good = $this->soldTicket(1, 0);
        $unsold = Ticket::forceCreate([
            'barcode' => '0000006000000002', 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => 2,
            'status' => config('ticket_statuses.publish'), // never sold
        ]);
        $nozzle = $this->nozzle('D');

        $response = $this->supply([$good->barcode, $unsold->barcode, '9999999999999999'], $nozzle, 5);

        $response->assertSessionHasErrors('ticket_barcodes');
        $this->assertStringContainsString($unsold->barcode, session('errors')->first('ticket_barcodes'));
        $this->assertStringContainsString('9999999999999999', session('errors')->first('ticket_barcodes'));
        $this->assertStringNotContainsString($good->barcode, session('errors')->first('ticket_barcodes'));
        $this->assertSame(0, Supplement::count());
        $this->assertSame(config('ticket_statuses.sale'), Ticket::find($good->barcode)->status); // untouched
    }

    public function testTheSameBarcodeTwiceIsRejected()
    {
        $this->density(1);
        $ticket = $this->soldTicket(1, 0);
        $nozzle = $this->nozzle('D');

        $this->supply([$ticket->barcode, $ticket->barcode], $nozzle, 5)->assertSessionHasErrors('ticket_barcodes.1');

        $this->assertSame(0, Supplement::count());
    }

    public function testBarcodesAreRequired()
    {
        $this->density(1);
        $nozzle = $this->nozzle('D');

        $this->supply('  ', $nozzle, 5)->assertSessionHasErrors('ticket_barcodes');
    }

    public function testCannotSupplyWithMismatchedFuelType()
    {
        $this->density(0.89);
        FuelType::forceCreate(['fuel_type' => 'A', 'price' => 1, 'density' => 0.78]);
        $ticket = $this->soldTicket(1, 0, 'D');
        $nozzle = $this->nozzle('A'); // Gasoline nozzle, Diesel ticket

        $this->supply($ticket->barcode, $nozzle, 5)->assertSessionHasErrors('nozzle_id');

        $this->assertSame(0, Supplement::count());
    }

    public function testCannotSupplyWithoutDensityConfigured()
    {
        FuelType::forceCreate(['fuel_type' => 'D', 'price' => 1, 'density' => null]);
        $ticket = $this->soldTicket(1, 0);
        $nozzle = $this->nozzle('D');

        $this->supply($ticket->barcode, $nozzle, 5)->assertSessionHasErrors('density');

        $this->assertSame(0, Supplement::count());
    }

    public function testAnotherFuelTypeTicketAmongSeveralStopsTheSupply()
    {
        $this->density(1);
        FuelType::forceCreate(['fuel_type' => 'A', 'price' => 1, 'density' => 0.78]);
        $diesel = $this->soldTicket(1, 0, 'D');
        $gasoline = $this->soldTicket(2, 0, 'A');
        $nozzle = $this->nozzle('D');

        $this->supply([$diesel->barcode, $gasoline->barcode], $nozzle, 5)->assertSessionHasErrors('nozzle_id');

        $this->assertSame(0, Supplement::count());
    }

    public function testListShowsTicketCountAndRemainder()
    {
        $this->density(1);
        $nozzle = $this->nozzle('D');
        $this->supply([$this->soldTicket(1, 0)->barcode, $this->soldTicket(2, 0)->barcode], $nozzle, 7);
        $remainder = Remainder::first();

        $this->as('manager')->get('/supplements')->assertOk()
            ->assertSee($remainder->ticket_barcode)
            ->assertSee('10.00') // tickets total
            ->assertSee('7.00');
    }

    public function testLiftingARestrictedRemainderTicketMakesItSoldAgainNotBlankStock()
    {
        $this->density(1);
        $nozzle = $this->nozzle('D');
        $this->supply($this->soldTicket(1, 0)->barcode, $nozzle, 3);
        $barcode = Remainder::first()->ticket_barcode;

        $this->as('manager')->post('/tickets/' . $barcode . '/restrict');
        $this->assertSame(config('ticket_statuses.restrict'), Ticket::find($barcode)->status);

        $this->post('/tickets/' . $barcode . '/lift-restriction');
        $this->assertSame(config('ticket_statuses.sale'), Ticket::find($barcode)->status); // not publish
    }

    public function testPermissions()
    {
        $this->as('manager')->get('/supplements')->assertOk();
        $this->as('accountant')->get('/supplements')->assertOk();
        $this->as('assistant')->get('/supplements')->assertOk();
        $this->as('cashier')->get('/supplements')->assertForbidden();

        $this->as('manager')->get('/supplements/create')->assertOk();
        $this->as('cashier')->get('/supplements/create')->assertForbidden();
        $this->post('/supplements', [])->assertForbidden();
    }

    public function testTheCommentOfARemainderIsWrittenChangedAndTakenAwayAfterwards()
    {
        $remainder = Remainder::forceCreate([
            'supplement_id' => null, 'ticket_barcode' => '0000025500000001', 'fuel_type' => 'D', 'amount_kg' => 3, 'remainder_date' => '2026-09-22', 'created_at' => '2026-09-22 10:00:00',
        ]);

        $this->as('manager')->get('/remainders')->assertOk()->assertSee(route('remainders.comment', $remainder->id));
        $this->as('manager')->put('/remainders/' . $remainder->id . '/comment', ['comment' => '  for Mr Li  '])->assertSessionHas('status');
        $this->assertSame('for Mr Li', $remainder->fresh()->comment);
        $this->as('manager')->get('/remainders')->assertOk()->assertSee('for Mr Li');

        $this->as('manager')->put('/remainders/' . $remainder->id . '/comment', ['comment' => 'changed'])->assertSessionHas('status');
        $this->assertSame('changed', $remainder->fresh()->comment);

        $this->as('manager')->put('/remainders/' . $remainder->id . '/comment', ['comment' => ''])->assertSessionHas('status');
        $this->assertNull($remainder->fresh()->comment);                                              // empty: no comment

        $this->as('manager')->put('/remainders/' . $remainder->id . '/comment', ['comment' => str_repeat('x', 256)])->assertSessionHasErrors('comment');
        $this->assertEquals(3, $remainder->fresh()->amount_kg);                                        // only the comment changes
        $this->as('manager')->put('/remainders/00000000-0000-0000-0000-000000000000/comment', ['comment' => 'x'])->assertNotFound();
    }

    public function testOnlyThoseWhoManageSuppliesChangeTheCommentOfARemainder()
    {
        $remainder = Remainder::forceCreate([
            'supplement_id' => null, 'ticket_barcode' => '0000025500000002', 'fuel_type' => 'D', 'amount_kg' => 3, 'comment' => 'kept', 'created_at' => '2026-09-22 10:00:00',
        ]);

        $this->as('cashier')->put('/remainders/' . $remainder->id . '/comment', ['comment' => 'changed'])->assertForbidden();
        $this->assertSame('kept', $remainder->fresh()->comment);
        $this->as('assistant')->put('/remainders/' . $remainder->id . '/comment', ['comment' => 'by the assistant'])->assertSessionHas('status');
        $this->assertSame('by the assistant', $remainder->fresh()->comment);
    }
}
