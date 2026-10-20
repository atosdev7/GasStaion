<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'purge_days' => 3,
    ];

    // last_synced_at is the last time the device UPLOADED (pushed); downloading does not change it.
    protected $casts = [
        'initial_sync_done' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
