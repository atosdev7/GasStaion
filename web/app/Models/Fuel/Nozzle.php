<?php

namespace App\Models\Fuel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nozzle extends Model
{
    use SoftDeletes;

    /** The name of a nozzle as the lists show it: the name that was given on the Smart210, "Nozzle <number>" while it has none. */
    public function getLabelAttribute()
    {
        return trim((string) $this->name) !== '' ? $this->name : 'Nozzle ' . $this->number;
    }

    /** Display label per nozzle id: the name of the nozzle only (not the device it belongs to). */
    public static function labels()
    {
        return static::withTrashed()->orderBy('device_id')->orderBy('number')->get()->mapWithKeys(function ($nozzle) {
            return [$nozzle->id => $nozzle->label];
        });
    }
}
