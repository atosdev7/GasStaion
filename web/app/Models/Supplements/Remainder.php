<?php

namespace App\Models\Supplements;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Support\Cards\CardLedger;
use Illuminate\Database\Eloquent\Model;

// The unused part of the tickets of one supplement, handed back to the customer as a new ticket (ticket_barcode).
class Remainder extends Model
{
    use HasUuidPrimaryKey;

    // A supply that has a remainder gives nothing back to a card (an older terminal makes a remainder ticket of the unused part of a card's ticket).
    protected static function boot()
    {
        parent::boot();

        $follow = function (Remainder $remainder) {
            $supplement = $remainder->supplement_id ? Supplement::withTrashed()->find($remainder->supplement_id) : null;
            if ($supplement) {
                CardLedger::syncRefund($supplement);
            }
        };
        static::saved($follow);
        static::deleted($follow);
    }

    public function supplement()
    {
        return $this->belongsTo(Supplement::class);
    }
}
