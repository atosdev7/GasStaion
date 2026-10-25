<?php

namespace App\Models\Debts;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketLoanSettlement extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $casts = [
        'synced' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function isConfirmed()
    {
        return $this->confirmed_at !== null;
    }

    public function loan()
    {
        return $this->belongsTo(TicketLoan::class, 'loan_id');
    }

    // A wrong settlement is undone by a reversal: a row for the same loan and ticket with the negative kg, dated the day it was made, that names
    // the settlement it reverses (reverses_settlement_id). The wrong settlement is kept.
    public function isReversal()
    {
        return $this->reverses_settlement_id !== null;
    }

    public function isReversed()
    {
        return static::where('reverses_settlement_id', $this->id)->exists();
    }
}
