<?php

namespace App\Models\Debts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// One row per payment a customer makes. debt_id is optional (a payment may not be clearly tied to
// one debt); a debt's settled amount is always computed from the rows that do point at it.
class DebtPayment extends Model
{
    use SoftDeletes;
}
