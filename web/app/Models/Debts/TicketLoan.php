<?php

namespace App\Models\Debts;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ResetsSynced;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// A customer takes fuel now without presenting a ticket; settled later by surrendering tickets
// (see TicketLoanSettlement, one row per ticket surrendered).
class TicketLoan extends Model
{
    use HasUuidPrimaryKey, ResetsSynced, SoftDeletes;

    protected $casts = [
        'synced' => 'boolean',
    ];

    // The sale that made the loan (an opening loan has none); a deleted sale is still shown.
    public function sale()
    {
        return $this->belongsTo(\App\Models\Sales\Sale::class)->withTrashed();
    }
}
