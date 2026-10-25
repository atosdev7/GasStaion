<?php

namespace Tests\Unit;

use App\Models\Admin\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    private function user($role, $active = true)
    {
        return new User(['role' => $role, 'active' => $active]);
    }

    public function testAdminHasEverything()
    {
        $this->assertTrue($this->user('admin')->hasPermission('backup.manage'));
        $this->assertTrue($this->user('admin')->hasPermission('anything.at.all'));
    }

    public function testInactiveUserHasNothing()
    {
        $this->assertFalse($this->user('admin', false)->hasPermission('users.manage'));
    }

    public function testManageImpliesView()
    {
        $this->assertTrue($this->user('manager')->hasPermission('fuel.view'));
        $this->assertTrue($this->user('assistant')->hasPermission('ticket_stock.view'));
        $this->assertTrue($this->user('accountant')->hasPermission('fuel.manage')); // sets the price of a fuel type, like the manager
        foreach (['assistant', 'cashier'] as $role) {
            $this->assertFalse($this->user($role)->hasPermission('fuel.view'), $role);
        }
    }

    public function testOnlyTheManagerAndTheAdminDateASaleFurtherBackThanTheLimit()
    {
        foreach (['admin', 'manager'] as $role) {
            $this->assertTrue($this->user($role)->hasPermission('sales.backdate'), $role);
        }
        foreach (['accountant', 'assistant', 'cashier'] as $role) {
            $this->assertFalse($this->user($role)->hasPermission('sales.backdate'), $role);
        }
    }

    public function testRestrictTickets()
    {
        foreach (['admin', 'manager', 'accountant'] as $role) {
            $this->assertTrue($this->user($role)->hasPermission('tickets.restrict'), $role);
        }
        foreach (['assistant', 'cashier'] as $role) {
            $this->assertFalse($this->user($role)->hasPermission('tickets.restrict'), $role);
        }
    }

    public function testAssistantPublishesTicketsRecordsPaymentsAndSettlesLoansButSeesNoReports()
    {
        $u = $this->user('assistant');
        $this->assertTrue($u->hasPermission('ticket_stock.manage'));
        $this->assertTrue($u->hasPermission('payments.manage'));   // records and reverses debt payments, like the accountant
        $this->assertTrue($u->hasPermission('payments.view'));
        $this->assertFalse($u->hasPermission('reports.view'));
        $this->assertTrue($u->hasPermission('loans.manage'));    // settles the ticket loans and reverses a settlement, like the accountant
        $this->assertTrue($u->hasPermission('loans.view'));
        $this->assertFalse($u->hasPermission('supplements.view'));

        $cashier = $this->user('cashier');
        $this->assertTrue($cashier->hasPermission('loans.view'));     // looks at the ticket loans
        $this->assertFalse($cashier->hasPermission('loans.manage'));
        $this->assertFalse($cashier->hasPermission('payments.view'));
    }

    public function testCashierIsLookupOnly()
    {
        $u = $this->user('cashier');
        $this->assertFalse($u->hasPermission('tickets.lookup')); // no Ticket Management for the cashier, not even the lookup
        // on the web the cashier only looks at the sales he issued himself
        foreach (['ticket_sales.view', 'spot_sales.view', 'card_sales.view'] as $view) {
            $this->assertTrue($u->hasPermission($view), $view);
        }
        foreach (['ticket_sales.manage', 'spot_sales.manage', 'sales.all', 'sales.delete', 'sales.edit', 'sales.return', 'card_sales.edit', 'card_sales.delete'] as $other) {
            $this->assertFalse($u->hasPermission($other), $other);
        }
        $this->assertFalse($u->hasPermission('users.manage'));
    }

    public function testGatesAreRegistered()
    {
        $this->assertTrue(Gate::forUser($this->user('accountant'))->allows('payments.manage'));
        $this->assertFalse(Gate::forUser($this->user('cashier'))->allows('payments.manage'));
    }

    public function testOnlyTheManagerAndTheAdminSetAFreePaymentCard()
    {
        foreach (['admin', 'manager'] as $role) {
            $this->assertTrue($this->user($role)->hasPermission('cards.free'), $role);
        }
        foreach (['accountant', 'assistant', 'cashier'] as $role) {
            $this->assertFalse($this->user($role)->hasPermission('cards.free'), $role);
        }
        $this->assertTrue($this->user('accountant')->hasPermission('cards.manage'));   // the accountant manages the cards, but cannot give a card free fuel
    }
}
