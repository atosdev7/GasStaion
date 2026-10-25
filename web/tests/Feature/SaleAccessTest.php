<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Cards\Card;
use App\Models\Sales\Customer;
use App\Models\Sales\Sale;
use App\Support\Cards\CardPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Who sees and works on which sale:
//  - admin, manager, accountant: ticket, spot and card sales, every one (cards: top up, edit, delete);
//  - the assistant: every ticket sale (all permissions on them), no spot sales, no card sales;
//  - the cashier works on the Smart210; on the web he only looks at the spot, ticket and card sales he issued himself.
class SaleAccessTest extends TestCase
{
    use RefreshDatabase;

    private $users = [];
    private $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::forceCreate(['name' => 'ACME']);
        foreach (['admin', 'manager', 'accountant', 'assistant', 'cashier'] as $role) {
            $this->users[$role] = User::forceCreate(['name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true]);
        }
    }

    private function as($role)
    {
        return $this->actingAs($this->users[$role]);
    }

    private function sale($type, $role, $extra = [])
    {
        $card = null;
        if ($type === 'card') {
            $card = Card::first() ?: Card::forceCreate(['uid' => 'AA01', 'holder_name' => 'Li Wei', 'fuel_type' => 'D'] + CardPin::make('1234'));
        }

        return Sale::forceCreate($extra + [
            'customer_id' => $type === 'card' ? null : $this->customer->id, 'card_id' => $card ? $card->id : null,
            'sale_type' => $type, 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 10, 'payment_method' => 'cash', 'total_amount' => 20,
            'paid_amount' => 20, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => today()->toDateString(), 'user_id' => $this->users[$role]->id,
        ]);
    }

    private function ids($response)
    {
        return $response->assertOk()->viewData('sales')->pluck('id')->sort()->values()->all();
    }

    private function sorted(array $sales)
    {
        return collect($sales)->pluck('id')->sort()->values()->all();
    }

    public function testAdminManagerAndAccountantSeeEverySale()
    {
        $mine = $this->sale('ticket', 'assistant');
        $other = $this->sale('ticket', 'manager');
        $spot = $this->sale('spot', 'cashier');
        $card = $this->sale('card', 'cashier');

        foreach (['admin', 'manager', 'accountant'] as $role) {
            $this->as($role);
            $this->assertSame($this->sorted([$mine, $other]), $this->ids($this->get('/ticket-sales')), $role);
            $this->assertSame($this->sorted([$spot]), $this->ids($this->get('/spot-sales')), $role);
            $this->assertSame($this->sorted([$card]), $this->ids($this->get('/card-sales')), $role);
            $this->get('/ticket-sales/' . $mine->id)->assertOk();
            $this->get('/ticket-sales/' . $spot->id)->assertOk();
            $this->get('/ticket-sales/' . $card->id)->assertOk();
        }
    }

    public function testTheAssistantHasEveryTicketSaleButNoSpotOrCardSales()
    {
        $mine = $this->sale('ticket', 'assistant');
        $other = $this->sale('ticket', 'manager');
        $spot = $this->sale('spot', 'assistant');   // a spot sale made in his name some other way: he has no spot sales
        $card = $this->sale('card', 'assistant');

        $this->as('assistant');
        $this->assertSame($this->sorted([$mine, $other]), $this->ids($this->get('/ticket-sales')));
        $this->get('/ticket-sales/' . $mine->id)->assertOk();
        $this->get('/ticket-sales/' . $other->id)->assertOk();
        $this->get('/ticket-sales/' . $spot->id)->assertForbidden();
        $this->get('/ticket-sales/' . $card->id)->assertForbidden();
        $this->get('/spot-sales')->assertForbidden();
        $this->get('/card-sales')->assertForbidden();

        // the deleted ones: all the deleted ticket sales
        $gone = $this->sale('ticket', 'assistant');
        $gone->delete();
        $goneOther = $this->sale('ticket', 'manager');
        $goneOther->delete();
        $this->assertSame($this->sorted([$gone, $goneOther]), $this->ids($this->get('/ticket-sales?deleted=1')));

        // what he changes: the edit and the return of every ticket sale, not of a spot sale or a top-up
        foreach ([$mine, $other] as $sale) {
            $this->get('/ticket-sales/' . $sale->id . '/edit')->assertOk();
            $this->get('/ticket-sales/' . $sale->id . '/return')->assertOk();
        }
        foreach ([$spot, $card] as $sale) {
            $this->get('/ticket-sales/' . $sale->id . '/edit')->assertForbidden();
            $this->get('/ticket-sales/' . $sale->id . '/return')->assertStatus($sale->sale_type === 'card' ? 404 : 403);
            $this->post('/ticket-sales/' . $sale->id . '/return', ['all' => 1])->assertStatus($sale->sale_type === 'card' ? 404 : 403);
            $this->delete('/ticket-sales/' . $sale->id)->assertForbidden();
            $this->assertNotNull(Sale::find($sale->id));
        }
        // (the delete is not refused for the person: this sale has no tickets in the test, so the rule of the sale itself says no)
        $this->delete('/ticket-sales/' . $other->id)->assertSessionHasErrors('sale');
        $this->assertStringContainsString('already used', session('errors')->first('sale'));
    }

    public function testTheCashierOnlyLooksAtHisOwnSalesOfEveryKind()
    {
        $own = ['ticket' => $this->sale('ticket', 'cashier'), 'spot' => $this->sale('spot', 'cashier'), 'card' => $this->sale('card', 'cashier')];
        $others = ['ticket' => $this->sale('ticket', 'manager'), 'spot' => $this->sale('spot', 'accountant'), 'card' => $this->sale('card', 'manager')];

        $this->as('cashier');
        $this->assertSame($this->sorted([$own['ticket']]), $this->ids($this->get('/ticket-sales')));
        $this->assertSame($this->sorted([$own['spot']]), $this->ids($this->get('/spot-sales')));
        $this->assertSame($this->sorted([$own['card']]), $this->ids($this->get('/card-sales')));
        foreach ($own as $sale) {
            $this->get('/ticket-sales/' . $sale->id)->assertOk();
        }
        foreach ($others as $sale) {
            $this->get('/ticket-sales/' . $sale->id)->assertForbidden();
        }

        // looking only: no action on any sale, no form, no list of the deleted of the others
        foreach (array_merge(array_values($own), array_values($others)) as $sale) {
            $this->get('/ticket-sales/' . $sale->id . '/edit')->assertForbidden();
            $this->put('/ticket-sales/' . $sale->id, ['sale_date' => today()->toDateString(), 'comment' => 'x'])->assertForbidden();
            $this->delete('/ticket-sales/' . $sale->id)->assertForbidden();
            $this->get('/ticket-sales/' . $sale->id . '/return')->assertForbidden();
            $this->delete('/card-sales/' . $sale->id)->assertForbidden();
            $this->assertNotNull(Sale::find($sale->id));
        }
        $this->get('/ticket-sales/create')->assertForbidden();
        $this->get('/spot-sales/create')->assertForbidden();
        $html = $this->get('/ticket-sales')->getContent();
        $this->assertStringNotContainsString('/ticket-sales/' . $own['ticket']->id . '/edit', $html);
        $this->assertStringNotContainsString('/ticket-sales/create', $html);
        $this->assertStringNotContainsString('/ticket-sales/' . $others['ticket']->id, $html);
    }

    public function testTheSalesIssuedOnTheTerminalAreTheCashiersOwnOnTheWeb()
    {
        // what a terminal pushes carries the user of the cashier who made it
        $sale = Sale::forceCreate([
            'customer_id' => $this->customer->id, 'sale_type' => 'ticket', 'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 5, 'payment_method' => 'cash',
            'total_amount' => 10, 'paid_amount' => 10, 'unpaid_amount' => 0, 'usage' => 'sale', 'sale_date' => today()->toDateString(), 'user_id' => $this->users['cashier']->id,
        ]);
        $this->sale('ticket', 'manager');

        $this->as('cashier');
        $this->assertSame($this->sorted([$sale]), $this->ids($this->get('/ticket-sales')));
    }

    public function testTheMenuOfEachRole()
    {
        $menu = function ($role) {
            return $this->as($role)->get('/')->assertOk()->getContent();
        };
        foreach (['admin', 'manager', 'accountant', 'cashier'] as $role) {
            $html = $menu($role);
            foreach (['>Ticket Sales<', '>Spot Sales<', '>Card Sales<'] as $item) {
                $this->assertStringContainsString($item, $html, "$role $item");
            }
        }
        $html = $menu('assistant');
        $this->assertStringContainsString('>Ticket Sales<', $html);
        $this->assertStringNotContainsString('>Spot Sales<', $html);
        $this->assertStringNotContainsString('>Card Sales<', $html);
    }

    public function testTheTicketLookupShowsTheSaleOfATicketOnlyToThoseWhoMaySeeThatKindOfSale()
    {
        $ticket = function ($serial, $sale) {
            return \App\Models\Tickets\Ticket::forceCreate([
                'barcode' => sprintf('000000600%07d', $serial), 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => $serial,
                'status' => config('ticket_statuses.sale'), 'sale_id' => $sale->id, 'sale_date' => now(),
            ]);
        };
        $mine = $ticket(1, $this->sale('ticket', 'assistant'));
        $theirs = $ticket(2, $this->sale('ticket', 'manager'));
        $spot = $ticket(3, $this->sale('spot', 'manager'));
        $heading = __('menu.ticket_sales');
        $detail = function ($t) {
            return $this->get('/tickets/lookup?modal=1&barcode=' . $t->barcode)->assertOk()->getContent();
        };

        // the assistant sees the sale of every ticket sale, nothing of a spot sale (the ticket itself is still found)
        $this->as('assistant');
        foreach ([$mine, $theirs] as $t) {
            $this->assertStringContainsString('<h3 class="card-title">' . $heading . '</h3>', $detail($t));
        }
        $html = $detail($spot);
        $this->assertStringContainsString($spot->barcode, $html);
        $this->assertStringNotContainsString('<h3 class="card-title">' . $heading . '</h3>', $html);
        $this->assertStringNotContainsString('ACME', $html);

        // the cashier has no lookup
        $this->as('cashier')->get('/tickets/lookup?modal=1&barcode=' . $mine->barcode)->assertForbidden();

        // admin, manager and accountant see every sale
        foreach (['admin', 'manager', 'accountant'] as $role) {
            $this->as($role);
            foreach ([$mine, $theirs, $spot] as $t) {
                $this->assertStringContainsString('<h3 class="card-title">' . $heading . '</h3>', $detail($t), $role);
            }
        }
    }
}
