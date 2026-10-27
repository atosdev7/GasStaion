<?php

namespace App\Support\Fuel;

use App\Models\Admin\Setting;

// The default cost per kg of a fuel type: the value the cost field of a sale starts with (Sales > create), and the cost of a sale uploaded by a terminal. It is its own
// setting (default_cost_<fuel type>), apart from the fuel inbounds and the tank balances: the "Make default cost" button of such a record copies the cost of the record
// here. A record whose cost is the default cost is shown as the default one (no flag is kept on it). A user can choose another cost on the sale itself.
class DefaultCost
{
    private static function key($fuelType)
    {
        return 'default_cost_' . $fuelType;
    }

    // The default cost of the fuel type, or null while none was set.
    public static function get($fuelType)
    {
        $value = optional(Setting::find(self::key($fuelType)))->value;

        return $value === null || $value === '' ? null : round((float) $value, 4);
    }

    public static function set($fuelType, $cost)
    {
        Setting::updateOrCreate(['key' => self::key($fuelType)], ['value' => number_format(round((float) $cost, 4), 4, '.', '')]);
    }

    // The first record of a fuel type (an inbound, a tank balance) gives it a default cost when it has none, so the sales have one from the start.
    public static function setIfNone($fuelType, $cost)
    {
        if (self::get($fuelType) === null) {
            self::set($fuelType, $cost);
        }
    }

    // Is this cost the default cost of the fuel type? (to 4 decimals, as costs are kept)
    public static function is($fuelType, $cost)
    {
        $default = self::get($fuelType);

        return $default !== null && $cost !== null && abs($default - round((float) $cost, 4)) < 0.00005;
    }
}
