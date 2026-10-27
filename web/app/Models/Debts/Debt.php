<?php

namespace App\Models\Debts;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Sales\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Created when a sale closes with unpaid_amount > 0 (usage=sale only), or entered by hand as a
// customer's opening balance (sale_id null — see BalanceController). How much of it is settled
// isn't stored here — DebtPayment rows aren't always tied to one specific debt (see DebtPayment),
// so it's computed per-debt from the ones that are, wherever it needs to be shown (see DebtController). A return of the goods does not change
// `amount`: it adds a DebtAdjustment (a negative amount dated the day of the return), and net_amount is the two together.
class Debt extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $casts = [
        'synced' => 'boolean',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    // The cuts a return made (negative amounts, see DebtAdjustment).
    public function adjustments()
    {
        return $this->hasMany(DebtAdjustment::class);
    }

    // What is owed before any payment: the amount the sale made, less the returns. (amount alone is never changed, so that the debts of
    // closed days stay as they were; load the adjustments with the debts: Debt::with('adjustments').)
    public function getNetAmountAttribute()
    {
        return round($this->amount + $this->adjustments->sum('amount'), 2);
    }

    // The sale's date; once a backup has cleared the sale, the date the backup copied to debt_date (the debt's own time if there is none); null for an opening balance.
    public function getDisplayDateAttribute()
    {
        if ($this->sale_id === null) {
            return null;
        }

        return $this->sale ? $this->sale->sale_date : ($this->debt_date ?: $this->created_at);
    }
}
