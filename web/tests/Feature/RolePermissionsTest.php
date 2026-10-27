<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The manager can do everything the accountant and the assistant can (and the admin everything).
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function user($role)
    {
        return User::forceCreate(['name' => $role, 'username' => $role . '1', 'password' => bcrypt('x'), 'role' => $role, 'active' => true]);
    }

    public function testTheManagerCanDoEverythingTheAccountantAndTheAssistantCan()
    {
        $manager = $this->user('manager');
        foreach (['accountant', 'assistant'] as $role) {
            foreach (config('permissions.roles.' . $role) as $permission) {
                $this->assertTrue($manager->can($permission), "the manager lacks $permission of the $role");
            }
        }
    }

    public function testTheManagerRecordsDebtPaymentsAndSettlesTicketLoans()
    {
        $this->actingAs($this->user('manager'));
        $this->get('/payments')->assertOk();
        $this->get('/payments/create')->assertOk();
        $this->get('/loans')->assertOk();
    }
}
