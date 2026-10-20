<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function testGuestIsSentToLogin()
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function testLoginWithUsernameAndLogout()
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin123'])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->get('/')->assertOk()->assertSee('admin');
        $this->post('/logout');
        $this->assertGuest();
    }

    public function testWrongPasswordFails()
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'nope']);
        $this->assertGuest();
    }
}
