<?php

namespace App\Models\Tickets;

use Illuminate\Database\Eloquent\Model;

// Old paper tickets of one fuel type and spec that customers still held on the "as of" day (Initialize > Ticket Sales). Table: opening_tickets.
class OpeningTicket extends Model
{
    protected $fillable = ['fuel_type', 'spec', 'quantity', 'as_of', 'comment', 'user_id'];
}
