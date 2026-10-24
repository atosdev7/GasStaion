<?php

namespace App\Models\Debts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// One row per payment a customer makes. debt_id is optional (a payment may not be clearly tied to
// one debt); a debt's settled amount is always computed from the rows that do point at it.
class DebtPayment extends Model
{
    use SoftDeletes;

    // The debt it pays (null for a general payment of the customer); a deleted debt is still shown.
    public function debt()
    {
        return $this->belongsTo(Debt::class)->withTrashed();
    }

    // A wrong payment is undone by a reversal: a row of the same customer, debt and method with the negative amount, dated the day it was made,
    // that names the payment it reverses (reverses_payment_id). The wrong payment is kept.
    public function isReversal()
    {
        return $this->reverses_payment_id !== null;
    }

    public function isReversed()
    {
        return static::where('reverses_payment_id', $this->id)->exists();
    }
}
