<?php

namespace App\Models\Fuel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Fuel delivered to a tank with its cost per kg. The default cost of a fuel type is not kept here (see App\Support\Fuel\DefaultCost).
class FuelInbound extends Model
{
    use SoftDeletes;

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function isConfirmed()
    {
        return $this->confirmed_at !== null;
    }
}
