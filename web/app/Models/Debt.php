<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Created when a sale closes with unpaid_amount > 0 (usage=sale only), or entered by hand as a
// customer's opening balance (sale_id null — see BalanceController). How much of it is settled
// isn't stored here — DebtPayment rows aren't always tied to one specific debt (see DebtPayment),
// so it's computed per-debt from the ones that are, wherever it needs to be shown (see DebtController).
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

    // The sale's date; the debt's own time once a backup has cleared the sale; null for an opening balance.
    public function getDisplayDateAttribute()
    {
        if ($this->sale_id === null) {
            return null;
        }

        return $this->sale ? $this->sale->sale_date : $this->created_at;
    }
}
