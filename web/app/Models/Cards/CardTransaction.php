<?php

namespace App\Models\Cards;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ResetsSynced;
use Illuminate\Database\Eloquent\Model;

// One entry of a card's ledger: kg in (a top-up) or out (a fill) with the running balance after it. Entries are never deleted: a top-up that is
// deleted on the web gets a reversal entry, so the history survives the sales and supplements it came from (see App\Support\Cards\CardLedger).
// type: topup (+kg, the sale), fill (-kg, the supplement or the ticket), reversal (the opposite of the entry it undoes, source_id is the same record),
// refund (+kg, the unused part of a card's ticket a supply gave back; source_id is the supply).
class CardTransaction extends Model
{
    use HasUuidPrimaryKey, ResetsSynced;

    const TYPES = ['topup', 'fill', 'reversal', 'refund'];

    protected $casts = [
        'synced' => 'boolean',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class)->withTrashed();
    }
}
