<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ResetsSynced;
use App\Support\CardLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// An RFID fuel card of one fuel type. The balance is the sum of its ledger (card_transactions, see CardLedger): the kg topped up less the kg supplied with the card.
// status: active; locked (5 wrong PINs, unlocked on the web); blocked (a lost card, unblocked on the web).
class Card extends Model
{
    use HasUuidPrimaryKey, ResetsSynced, SoftDeletes;

    const STATUSES = ['active', 'locked', 'blocked'];

    const MAX_ATTEMPTS = 5;

    protected $casts = [
        'synced' => 'boolean',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function supplements()
    {
        return $this->hasMany(Supplement::class);
    }

    // [card id => kg left] for every card that has an entry in its ledger; one query, not one per card.
    public static function balances()
    {
        return CardLedger::balances();
    }

    public function balance()
    {
        return CardLedger::balance($this->id);
    }

    // The balance worked out again from the records that still exist (the kg topped up less the kg supplied): what the ledger must agree with
    // as long as no top-up or supply was removed for good. The card history page shows both so that a difference is seen.
    public function balanceFromRecords()
    {
        $in = (float) Sale::where('sale_type', 'card')->where('card_id', $this->id)->sum('amount_kg');
        $out = (float) Supplement::where('card_id', $this->id)->sum('out_amount_kg');

        return round($in - $out, 2);
    }

    public function transactions()
    {
        return $this->hasMany(CardTransaction::class);
    }

    // What the card is called in lists: the holder's name and the card id.
    public function getLabelAttribute()
    {
        return $this->holder_name . ' (' . $this->uid . ')';
    }
}
