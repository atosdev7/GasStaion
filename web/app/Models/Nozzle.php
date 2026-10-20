<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nozzle extends Model
{
    use SoftDeletes;

    /** Display label per nozzle id, e.g. "Pump 1 — #1 (Diesel)". */
    public static function labels()
    {
        $devices = Device::pluck('name', 'id');
        $fuels = config('fuel_types');

        return static::withTrashed()->orderBy('device_id')->orderBy('number')->get()->mapWithKeys(function ($nozzle) use ($devices, $fuels) {
            $label = ($devices[$nozzle->device_id] ?? $nozzle->device_id) . ' — #' . $nozzle->number
                . ' (' . ($fuels[$nozzle->fuel_type] ?? $nozzle->fuel_type) . ')';

            return [$nozzle->id => $label];
        });
    }
}
