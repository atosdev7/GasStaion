<?php

namespace App\Models\Tickets;

use Illuminate\Database\Eloquent\Model;

// One row per publish action: a range of tickets (fuel type, spec, code, serial range)
// registered from blank stock at once. Table: ticket_publishes. A publish cannot be reversed: it is not changed or deleted afterwards (the numbers are
// used). Confirmed with the daily confirmation.
class TicketPublish extends Model
{
    protected $casts = ['confirmed_at' => 'datetime'];

    public function isConfirmed()
    {
        return $this->confirmed_at !== null;
    }
}
