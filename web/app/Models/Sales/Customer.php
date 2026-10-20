<?php

namespace App\Models\Sales;

use App\Models\Concerns\ResetsSynced;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use ResetsSynced, SoftDeletes;

    protected $casts = [
        'is_self' => 'boolean',
    ];
}
