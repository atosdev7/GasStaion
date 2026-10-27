<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    private function login($role)
    {
        $user = User::create([
            'name' => 'T ' . $role,
            'username' => 't_' . $role,
            'password' => bcrypt('x'),
            'role' => $role,
            'active' => true,
        ]);

        return $this->actingAs($user);
    }

    public function testAdminSeesEveryGroupAndItem()
    {
        $res = $this->login('admin')->get('/')->assertOk();
        foreach (config('menu') as $group) {
            $res->assertSee(__('menu.' . $group['label']));
            if (!empty($group['single'])) {
                continue; // shown as one link named after the group
            }
            foreach ($group['items'] as $item) {
                $res->assertSee(__('menu.' . $item['label']));
            }
        }
    }

    public function testCashierSeesOnlyAllowedGroups()
    {
        $this->login('cashier')->get('/')->assertOk()
            ->assertSee(__('menu.group_dashboard'))
            ->assertDontSee(__('menu.group_tickets'))
            ->assertDontSee(__('menu.ticket_lookup'))
            ->assertSee(__('menu.group_sales'))
            ->assertSee(__('menu.customers'))
            ->assertSee(__('menu.ticket_sales'))
            ->assertSee(__('menu.spot_sales'))
            ->assertSee(__('menu.card_sales'))
            ->assertDontSee(__('menu.payments'))
            ->assertDontSee(__('menu.group_admin'))
            ->assertDontSee(__('menu.group_reports'))
            ->assertSee(__('menu.ticket_loans'))            // the cashier looks at the ticket loans
            ->assertSee(__('menu.group_supplements'))      // the cashier looks at the supplies and the remainders
            ->assertDontSee(__('menu.group_init'))
            ->assertDontSee(__('menu.group_fuel'))
            ->assertDontSee(__('menu.users'))
            ->assertDontSee(__('menu.backup'));
    }

    public function testTheMainMenusAreInTheOrderOfTheWork()
    {
        $html = $this->login('accountant')->get('/')->assertOk()->getContent();

        // Ticket Management, Cards Management, Sales Management, Supplements, Debts Management, Daily Confirmation (user, 2026-10-25); Debts Management holds Payments and
        // Ticket Loans (there is no Loan menu of its own); Payments and Daily Confirmation are not in Sales Management
        // each name is looked for after the one before it (a name like Supplements is also an item of another menu)
        $at = 0;
        foreach (['group_tickets', 'group_cards', 'group_sales', 'ticket_sales', 'spot_sales', 'customers', 'group_supplements', 'group_debts', 'payments', 'ticket_loans', 'group_confirmations'] as $key) {
            $pos = strpos($html, '>' . __('menu.' . $key) . '<', $at);
            $this->assertNotFalse($pos, $key . ' comes after the one before it');
            $at = $pos + 1;
        }
        $this->assertSame('Debts Management', __('menu.group_debts'));
    }

    public function testAccountantMenu()
    {
        $this->login('accountant')->get('/')->assertOk()
            ->assertSee(__('menu.group_sales'))
            ->assertSee(__('menu.payments'))
            ->assertSee(__('menu.ticket_loans'))
            ->assertSee(__('menu.group_supplements'))
            ->assertSee(__('menu.group_reports'))
            ->assertSee(__('menu.group_init'))
            ->assertSee(__('menu.fuel'))
            ->assertDontSee(__('menu.users'))
            ->assertDontSee(__('menu.backup'))
            ->assertDontSee(__('menu.audit'));
    }

    public function testManagerSeesAdminGroupWithoutAdminOnlyItems()
    {
        $this->login('manager')->get('/')->assertOk()
            ->assertSee(__('menu.group_admin'))
            ->assertSee(__('menu.devices'))
            ->assertSee(__('menu.audit'))
            ->assertSee('href="' . url('/users') . '"', false)             // Users is a main menu item of its own for the manager
            ->assertDontSee(__('menu.backup'));
    }

    // an item of another group on a page of the same section (Go-live is a balances page too) does not light the items of Initialize
    public function testOnlyTheItemOfThePageIsCurrentWhenAnotherGroupSharesItsRoutePrefix()
    {
        $html = $this->login('manager')->get('/balances/go-live')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'menu-open'));                                             // Config for Admin only
        $this->assertSame(1, preg_match_all('/<a href="[^"]*"\s+class="nav-link active">\s*<i class="far fa-circle nav-icon"><\/i>/', $html));   // and one item in it
        $this->assertRegExp('#href="' . preg_quote(url('/balances/go-live'), '#') . '"\s+class="nav-link active"#', $html);
    }

    // the sync status is a tab of the POS item: the item stays current on its page, and the tabs are on both pages
    public function testTheSyncStatusIsATabOfPos()
    {
        $html = $this->login('manager')->get('/sync')->assertOk()->getContent();
        $this->assertRegExp('#href="' . preg_quote(url('/devices'), '#') . '"\s+class="nav-link active"#', $html);
        $this->assertStringContainsString('id="pos-tabs"', $html);
        $this->assertStringNotContainsString('<p>Sync Status</p>', $html);                                  // no menu item of its own any more
        $this->get('/devices')->assertOk()->assertSee('id="pos-tabs"', false)->assertSee(__('menu.sync'));

        // a user who may see the sync status but not the devices has the POS item lead to it
        $html = $this->login('accountant')->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="' . url('/sync') . '"', $html);
        $this->assertStringNotContainsString('href="' . url('/devices') . '"', $html);
        $this->get('/sync')->assertOk()->assertSee('POS');

        // the assistant has neither the devices nor the sync status: no POS item
        $html = $this->login('assistant')->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="' . url('/sync') . '"', $html);
        $this->assertStringNotContainsString('href="' . url('/devices') . '"', $html);
    }

    public function testActiveGroupIsOpenOnItsPage()
    {
        // On /fuel the "Config for Admin" group is open; on the dashboard nothing is.
        $this->login('admin')->get('/fuel')->assertOk()->assertSee('menu-open');
        $this->get('/')->assertOk()->assertDontSee('menu-open');
    }

    public function testThereIsNoLanguageSwitchWithOneLanguage()
    {
        $this->login('admin')->get('/')->assertOk()->assertDontSee('fa-language');
        $this->assertSame(['en'], array_keys(config('locales')));
    }

    public function testUnknownLocaleIsIgnored()
    {
        $this->get('/locale/xx');
        $this->assertNull(session('locale'));
    }

    public function testAllLocalesHaveSameKeys()
    {
        foreach (['app', 'menu', 'roles'] as $file) {
            $base = array_keys(require resource_path("lang/en/$file.php"));
            foreach (array_keys(config('locales')) as $locale) {
                $keys = array_keys(require resource_path("lang/$locale/$file.php"));
                $this->assertEquals($base, $keys, "$locale/$file");
            }
        }
    }

    public function testEveryMenuLabelAndRoleIsTranslated()
    {
        $labels = require resource_path('lang/en/menu.php');
        foreach (config('menu') as $group) {
            $this->assertArrayHasKey($group['label'], $labels);
            foreach ($group['items'] as $item) {
                $this->assertArrayHasKey($item['label'], $labels);
            }
        }
        foreach (config('roles') as $role) {
            $this->assertArrayHasKey($role, require resource_path('lang/en/roles.php'));
        }
    }
}
