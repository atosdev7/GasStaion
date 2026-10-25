<?php

namespace App\Models\Tickets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Blank tickets received. A negative quantity takes blank tickets out of the stock (spoiled in printing). Confirmed with the daily confirmation.
class TicketInbound extends Model
{
    use SoftDeletes;

    protected $casts = ['confirmed_at' => 'datetime'];

    public function isConfirmed()
    {
        return $this->confirmed_at !== null;
    }
}
