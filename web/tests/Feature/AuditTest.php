<?php

namespace Tests\Feature;

use App\Support\Admin\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    private $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audit_test_' . uniqid();
        mkdir($this->dir);
        config(['logging.channels.audit.path' => $this->dir . DIRECTORY_SEPARATOR . 'audit.log']);
    }

    protected function tearDown(): void
    {
        // Release the open log file first (Windows will not delete an open file).
        foreach (Log::channel('audit')->getLogger()->getHandlers() as $handler) {
            $handler->close();
        }
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') as $f) {
            unlink($f);
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function lines()
    {
        $files = glob($this->dir . DIRECTORY_SEPARATOR . 'audit-*.log');
        $this->assertCount(1, $files);
        $this->assertSame('audit-' . date('Y-m-d') . '.log', basename($files[0]));

        return file($files[0], FILE_IGNORE_NEW_LINES);
    }

    public function testLineFormat()
    {
        Audit::log('fuel_inbound', 'tank 1, 5000 L', 'bob');

        $this->assertRegExp('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d - bob - FUEL_INBOUND - tank 1, 5000 L$/', $this->lines()[0]);
    }

    public function testWhoDefaultsToSystem()
    {
        Audit::log('purge');

        $this->assertRegExp('/ - system - PURGE - $/', $this->lines()[0]);
    }

    public function testDetailStaysOnOneLine()
    {
        Audit::log('note', "line1\nline2 - x", 'bob');

        $lines = $this->lines();
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('line1 line2', $lines[0]);
    }

    public function testRetentionIsAboutThreeMonths()
    {
        $this->assertSame(90, config('logging.channels.audit.days'));
    }

    public function testLoginLogoutAndFailureAreAudited()
    {
        $this->seed();
        $this->post('/login', ['username' => 'admin', 'password' => 'wrong']);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);
        $this->post('/logout');

        $text = implode("\n", $this->lines());
        $this->assertStringContainsString(' - admin - LOGIN_FAILED - ', $text);
        $this->assertStringContainsString(' - admin - LOGIN - ', $text);
        $this->assertStringContainsString(' - admin - LOGOUT - ', $text);
    }
}
