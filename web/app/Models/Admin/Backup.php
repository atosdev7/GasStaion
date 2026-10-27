<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $casts = [
        'cleared' => 'array',
        'tank_carry' => 'array',
    ];
}
