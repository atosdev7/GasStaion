<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Support\CardLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $casts = [
        'synced' => 'boolean',
        'receipt' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    // A top-up of a card (sale_type card) is an entry of the card's ledger; a deleted one is undone there by a reversal entry (CardLedger).
    protected static function boot()
    {
        parent::boot();

        static::saved(function (Sale $sale) {
            CardLedger::syncSale($sale);
        });
        static::deleted(function (Sale $sale) {
            CardLedger::reverseSale($sale);
        });
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    // How many tickets of a spec the sale has, from its items (load them with `with('items')` for a list).
    public function ticketsOfSpec($spec)
    {
        return (int) $this->items->where('spec', (int) $spec)->sum('quantity');
    }

    // A return entry is a sale row with negative amounts that points at the sale it corrects.
    public function isReturn()
    {
        return $this->reverses_sale_id !== null;
    }

    public function original()
    {
        return $this->belongsTo(Sale::class, 'reverses_sale_id');
    }

    public function returns()
    {
        return $this->hasMany(Sale::class, 'reverses_sale_id');
    }
}
