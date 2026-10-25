<?php

namespace App\Models\Fuel;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelInbound extends Model
{
    use SoftDeletes;

    protected $casts = [
        'is_default' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function isConfirmed()
    {
        return $this->confirmed_at !== null;
    }

    /**
     * Cost per kg that pre-fills the cost field of a sales form:
     * the cost of the inbound flagged is_default for this fuel type (null if there is none yet).
     */
    public static function defaultCost($fuelType)
    {
        $row = static::where('fuel_type', $fuelType)->where('is_default', true)->first();

        return $row ? $row->cost : null;
    }

    /**
     * The cost that was the default of this fuel type at a given time (a sale's time stamp): the inbound flagged as the default then.
     * Falls back to the current default when the history does not reach back that far.
     */
    public static function costAt($fuelType, $time)
    {
        $at = Carbon::parse($time)->format('Y-m-d H:i:s');
        $row = static::where('fuel_type', $fuelType)
            ->where('default_from', '<=', $at)
            ->where(function ($query) use ($at) {
                $query->whereNull('default_until')->orWhere('default_until', '>', $at);
            })
            ->orderByDesc('default_from')->first();

        return $row ? $row->cost : static::defaultCost($fuelType);
    }

    // Makes this inbound the default of its fuel type from now on and ends the period of the one that was, so the history is kept.
    public function becomeDefault()
    {
        static::where('fuel_type', $this->fuel_type)->where('is_default', true)->where('id', '<>', $this->id ?: 0)
            ->update(['is_default' => false, 'default_until' => now()]);
        $this->forceFill(['is_default' => true, 'default_from' => now(), 'default_until' => null]);
    }
}
