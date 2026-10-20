<?php

namespace App\Support\Admin;

use App\Models\Admin\User;

/**
 * Hashes the Smart210 checks without the server: the cashier login (users.pos_hash) and the Machine Config PIN (devices.pin_hash).
 * Salted SHA-256, 64 hex characters. Older rows hold a salted SHA-1 of 40 characters; the Smart210 tells them apart by length.
 */
class DeviceHash
{
    public static function make($salt, $secret)
    {
        return hash('sha256', $salt . $secret);
    }

    public static function isLegacy($hash)
    {
        return $hash !== null && strlen($hash) === 40;
    }

    // Called while the plain password is at hand and already verified (web sign-in, device registration).
    public static function upgradeUser(User $user, $password)
    {
        if (!self::isLegacy($user->pos_hash)) {
            return;
        }

        $user->pos_salt = bin2hex(random_bytes(8));
        $user->pos_hash = self::make($user->pos_salt, $password);
        $user->synced = false; // the Smart210 pulls the new hash on its next sync
        $user->save();
    }
}
