<?php

namespace App\Models\Supplements;

use App\Models\Admin\Device;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\Nozzle;
use App\Models\Tickets\Ticket;
use App\Support\Cards\CardLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// One supply event at a nozzle. The tickets it used point to it (tickets.supplement_id); preset_kg is what they covered,
// amount_kg what was dispensed; `mode` is auto or manual (see MODES); `density` is kg per litre.
class Supplement extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    // the mode a supply was made in: the system sets the preset and starts the fill, or the supplier types the amount at the dispenser
    const MODES = ['auto', 'manual'];

    protected $casts = [
        'synced' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    // A fill with a card (card_id) is an entry of the card's ledger; a deleted one is undone there by a reversal entry (CardLedger).
    protected static function boot()
    {
        parent::boot();

        static::saved(function (Supplement $supplement) {
            CardLedger::syncSupplement($supplement);
            CardLedger::syncRefund($supplement);       // the unused part of a card's ticket goes back to the card
            FuelOutbound::syncSupplement($supplement); // the fuel that left the tank
        });
        static::deleted(function (Supplement $supplement) {
            CardLedger::reverseSupplement($supplement);
            CardLedger::syncRefund($supplement);
            if ($supplement->isForceDeleting()) {
                FuelOutbound::dropSupplement($supplement);
            } else {
                FuelOutbound::syncSupplement($supplement); // a deleted supply no longer counts
            }
        });
    }

    // The day of the supply: its supply_date (the 9:00 business day), or the day of its time stamp while it has none yet.
    public function getSupplyDayAttribute()
    {
        return $this->supply_date ? substr((string) $this->supply_date, 0, 10) : substr((string) $this->supplied_at, 0, 10);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function remainder()
    {
        return $this->hasOne(Remainder::class);
    }

    public function nozzle()
    {
        return $this->belongsTo(Nozzle::class)->withTrashed(); // a nozzle deleted on the Smart210 still names its old supplements
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
