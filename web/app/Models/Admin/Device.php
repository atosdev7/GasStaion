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

    // last_synced_at is the last time the device reported that nothing waits for an upload from before the running business day (POST sync/uploaded); downloading and
    // pushing a batch do not change it.
    protected $casts = [
        'initial_sync_done' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
