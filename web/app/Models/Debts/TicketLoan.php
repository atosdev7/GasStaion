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
}
