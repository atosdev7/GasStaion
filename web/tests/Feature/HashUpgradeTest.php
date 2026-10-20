<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Smart210 hashes moved from salted SHA-1 (40 hex) to salted SHA-256 (64 hex); old rows are upgraded when the plain value is at hand.
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class HashUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // A cashier whose Smart210 hash is the old SHA-1 (or the current SHA-256 with $legacy = false), already synced.
    private function legacyUser($legacy = true)
    {
        $salt = 'abcd1234abcd1234';
        $user = User::forceCreate([
            'name' => 'Old', 'username' => 'old', 'password' => bcrypt('secret1'), 'role' => 'cashier', 'active' => true,
            'pos_salt' => $salt, 'pos_hash' => $legacy ? sha1($salt . 'secret1') : hash('sha256', $salt . 'secret1'),
        ]);
        // Marked synced straight in the table: saving the model would reset the flag.
        User::withTrashed()->whereKey($user->id)->toBase()->update(['synced' => true]);

        return $user->fresh();
    }

    public function testSeededDefaultsUseSha256()
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertSame(hash('sha256', $admin->pos_salt . 'admin123'), $admin->pos_hash);

        $s = DB::table('settings')->pluck('value', 'key');
        $this->assertSame(hash('sha256', $s['default_device_pin_salt'] . '0000'), $s['default_device_pin_hash']);
    }

    public function testWebSignInUpgradesALegacyHashAndMarksItForSync()
    {
        $user = $this->legacyUser();

        $this->post('/login', ['username' => 'old', 'password' => 'secret1'])->assertRedirect('/');

        $user->refresh();
        $this->assertSame(hash('sha256', $user->pos_salt . 'secret1'), $user->pos_hash);
        $this->assertNotSame('abcd1234abcd1234', $user->pos_salt);
        $this->assertSame(0, (int) $user->synced);
    }

    public function testWebSignInLeavesACurrentHashAlone()
    {
        $user = $this->legacyUser(false);

        $this->post('/login', ['username' => 'old', 'password' => 'secret1'])->assertRedirect('/');

        $user->refresh();
        $this->assertSame('abcd1234abcd1234', $user->pos_salt);
        $this->assertSame(hash('sha256', 'abcd1234abcd1234' . 'secret1'), $user->pos_hash);
        $this->assertSame(1, (int) $user->synced);
    }

    public function testWrongPasswordUpgradesNothing()
    {
        $user = $this->legacyUser();

        $this->post('/login', ['username' => 'old', 'password' => 'wrong']);

        $this->assertSame(sha1('abcd1234abcd1234' . 'secret1'), $user->fresh()->pos_hash);
    }
}
