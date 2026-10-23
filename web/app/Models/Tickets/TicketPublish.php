<?php

namespace App\Models\Tickets;

use Illuminate\Database\Eloquent\Model;

// One row per publish action: a range of tickets (fuel type, spec, code, serial range)
// registered from blank stock at once. Table: ticket_publishes. A deleted batch is really deleted (no soft delete): its numbers are free again.
class TicketPublish extends Model
{
}
