<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

// One item of a ticket sale as entered: `quantity` tickets of a spec from `start_serial` to `end_serial` (`amount_kg` in all).
class SaleItem extends Model
{
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
