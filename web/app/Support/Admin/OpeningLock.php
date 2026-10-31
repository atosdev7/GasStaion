<?php

namespace App\Support\Admin;

use App\Models\Admin\Setting;

// The opening balances (the tank balances entered by hand, the opening debts, ticket loans and remainders) are entered at go-live. After that they are changed only by the
// system (a backup rolls the tank balances forward), not by hand: the admin locks them once the live setup is done and the backup server is copied from it. A locked
// balance cannot be added, changed or deleted on its screens until the admin unlocks it (both are written to the audit log). The old paper tickets are not locked:
// they are counted from go-live and a wrong count is corrected by editing the entry.
class OpeningLock
{
    const SETTING = 'opening_locked_at';

    // When the opening balances were locked (Y-m-d H:i:s), or null while they are open.
    public static function lockedAt()
    {
        $value = optional(Setting::find(self::SETTING))->value;

        return $value ?: null;
    }

    public static function isLocked()
    {
        return self::lockedAt() !== null;
    }

    public static function lock()
    {
        Setting::updateOrCreate(['key' => self::SETTING], ['value' => now()->format('Y-m-d H:i:s')]);
    }

    public static function unlock()
    {
        Setting::where('key', self::SETTING)->delete();
    }
}
