<?php

namespace Tests\Unit;

use App\Models\User;
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
        $this->assertFalse($this->user('accountant')->hasPermission('fuel.manage'));
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

    public function testAssistantPublishesTicketsButNothingFinancial()
    {
        $u = $this->user('assistant');
        $this->assertTrue($u->hasPermission('ticket_stock.manage'));
        $this->assertFalse($u->hasPermission('payments.view'));
        $this->assertFalse($u->hasPermission('reports.view'));
    }

    public function testCashierIsLookupOnly()
    {
        $u = $this->user('cashier');
        $this->assertTrue($u->hasPermission('tickets.lookup'));
        $this->assertFalse($u->hasPermission('ticket_sales.view'));
        $this->assertFalse($u->hasPermission('users.manage'));
    }

    public function testGatesAreRegistered()
    {
        $this->assertTrue(Gate::forUser($this->user('accountant'))->allows('payments.manage'));
        $this->assertFalse(Gate::forUser($this->user('cashier'))->allows('payments.manage'));
    }
}
