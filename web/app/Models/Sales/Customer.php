<?php

namespace App\Models\Sales;

use App\Models\Concerns\ResetsSynced;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use ResetsSynced, SoftDeletes;

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // The built-in customer "Individual": the customer of own use and other use, and of the buyers who pay cash and need no name (the cashier writes
    // what is known about them in the comment of the sale).
    public static function individual()
    {
        return static::where('is_default', true)->first() ?: static::forceCreate(['name' => 'Individual', 'is_default' => true, 'synced' => false]);
    }
}
