<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private $files = [];

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    // an audit file for an old day, so that it does not mix with the real log of the day
    private function day($date, array $lines)
    {
        $file = storage_path('logs/audit-' . $date . '.log');
        file_put_contents($file, implode("\n", $lines) . "\n");
        $this->files[] = $file;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function testTheAuditLogListsTheEntriesOfThePeriodNewestFirst()
    {
        $this->day('2019-03-01', [
            '2019-03-01 09:00:00 - admin - LOGIN - 127.0.0.1',
            '2019-03-01 09:05:00 - cash - TICKET_SALE - sale #1 D 5kg - with a dash',
            'not an audit line',
        ]);
        $this->day('2019-03-02', ['2019-03-02 10:00:00 - system - BACKUP_DUMP - gasstation.sql.gz']);

        $response = $this->as('admin')->get('/audit?from=2019-03-01&to=2019-03-02')->assertOk();
        $response->assertSeeInOrder(['BACKUP_DUMP', 'TICKET_SALE', 'LOGIN']);
        $response->assertSee('sale #1 D 5kg - with a dash');
        $response->assertDontSee('not an audit line');
        $this->assertSame(3, $response->viewData('entries')->total());
    }

    public function testTheListCanBeNarrowedToAUserOrAnAction()
    {
        $this->day('2019-03-01', [
            '2019-03-01 09:00:00 - admin - LOGIN - 127.0.0.1',
            '2019-03-01 09:05:00 - cash - TICKET_SALE - sale #1',
            '2019-03-01 09:06:00 - cash - LOGIN - 127.0.0.1',
        ]);

        $byUser = $this->as('manager')->get('/audit?from=2019-03-01&to=2019-03-01&user=cash')->assertOk();
        $this->assertSame(2, $byUser->viewData('entries')->total());
        $byAction = $this->as('manager')->get('/audit?from=2019-03-01&to=2019-03-01&action=login')->assertOk();
        $this->assertSame(2, $byAction->viewData('entries')->total());
        $both = $this->as('manager')->get('/audit?from=2019-03-01&to=2019-03-01&user=cash&action=login')->assertOk();
        $this->assertSame(1, $both->viewData('entries')->total());
    }

    public function testADayWithoutAFileOrAWrongDateIsHandled()
    {
        $this->as('admin')->get('/audit?from=2019-01-01&to=2019-01-02')->assertOk()->assertSee(__('report.no_data'));
        $this->as('admin')->get('/audit?from=nonsense')->assertSessionHasErrors('from');
        $this->as('admin')->get('/audit')->assertOk();   // the last 7 days
    }

    public function testOnlyRolesWithTheAuditPermissionSeeIt()
    {
        $this->as('accountant')->get('/audit')->assertForbidden();
        $this->as('cashier')->get('/audit')->assertForbidden();
        $this->as('manager')->get('/audit')->assertOk();
    }

    public function testTheMenuLinksToTheWorkingPage()
    {
        $this->as('admin')->get('/')->assertOk()->assertSee(route('audit.index'));
    }
}
