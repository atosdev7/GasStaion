<?php

use App\Models\Admin\User;
use App\Support\Admin\DeviceHash;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $now = now();

        // Default admin. Change the password after the first login.
        $salt = bin2hex(random_bytes(8));
        User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Administrator',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'pos_salt' => $salt,
            'pos_hash' => DeviceHash::make($salt, 'admin123'),
        ]);

        // The built-in "Individual" customer (own use, other use, and the buyers who pay cash and need no name).
        if (!DB::table('customers')->where('is_default', true)->exists()) {
            DB::table('customers')->insert([
                'name' => 'Individual',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Default Machine Config PIN applied to newly registered devices (salted SHA-256).
        // Also the default purge_days.
        if (!DB::table('settings')->where('key', 'default_device_pin_hash')->exists()) {
            $pinSalt = bin2hex(random_bytes(8));
            $rows = [
                'default_device_pin_salt' => $pinSalt,
                'default_device_pin_hash' => DeviceHash::make($pinSalt, '0000'),
                'default_purge_days' => '3',
            ];
            foreach ($rows as $key => $value) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
