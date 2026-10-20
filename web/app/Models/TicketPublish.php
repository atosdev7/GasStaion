<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// One row per publish action: a range of tickets (fuel type, spec, code, serial range)
// registered from blank stock at once. Table: ticket_publishes.
class TicketPublish extends Model
{
    use SoftDeletes;
}
