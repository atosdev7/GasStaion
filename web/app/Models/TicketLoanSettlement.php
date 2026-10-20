<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketLoanSettlement extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $casts = [
        'synced' => 'boolean',
    ];

    public function loan()
    {
        return $this->belongsTo(TicketLoan::class, 'loan_id');
    }
}
