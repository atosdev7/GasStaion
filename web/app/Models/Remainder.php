<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

// The unused part of the tickets of one supplement, handed back to the customer as a new ticket (ticket_barcode).
class Remainder extends Model
{
    use HasUuidPrimaryKey;

    public function supplement()
    {
        return $this->belongsTo(Supplement::class);
    }
}
