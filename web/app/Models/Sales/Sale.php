<?php

namespace App\Models\Sales;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Support\Cards\CardLedger;
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

    // The tickets that came back with a return entry (it has no items of its own).
    public function returnedTickets()
    {
        return $this->hasMany(SaleReturnTicket::class, 'sale_id');
    }

    // How many tickets of a spec the sale has, from its items (load them with `with('items')` for a list). A return entry has the tickets it took back, as a
    // negative count, so the counts of a day agree with its kg and its money (the spec of a returned ticket is told by its weight: a ticket that a backup
    // cleared has no spec any more).
    public function ticketsOfSpec($spec)
    {
        if ($this->isReturn()) {
            $kg = (float) config('ticket_specs.' . (int) $spec);

            return 0 - $this->returnedTickets->filter(function ($ticket) use ($kg) {
                return abs((float) $ticket->amount_kg - $kg) < 0.005;
            })->count();
        }

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
