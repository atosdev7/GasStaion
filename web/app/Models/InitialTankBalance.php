<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InitialTankBalance extends Model
{
    protected $fillable = ['tank_id', 'amount_kg'];
}
