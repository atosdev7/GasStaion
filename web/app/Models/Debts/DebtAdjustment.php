<?php

namespace App\Models\Debts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// A counter entry on a debt (a return of the goods cuts what is owed): amount is negative, adjusted_date is the day it was made. The debt
// itself keeps the amount the sale made, so the debts of earlier days stay as they were confirmed.
class DebtAdjustment extends Model
{
    use SoftDeletes;

    public function debt()
    {
        return $this->belongsTo(Debt::class);
    }
}
