<?php

namespace App\Models\Fuel;

use App\Models\Supplements\Supplement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Fuel that left a tank (see the migration): made by hand, or following a supply.
class FuelOutbound extends Model
{
    use SoftDeletes;

    protected $casts = ['confirmed_at' => 'datetime'];

    public function isConfirmed()
    {
        return $this->confirmed_at !== null;
    }

    public function tank()
    {
        return $this->belongsTo(Tank::class)->withTrashed();
    }

    public function supplement()
    {
        return $this->belongsTo(Supplement::class)->withTrashed();
    }

    // A row that follows a supply is changed with the supply, never by hand.
    public function isSupply()
    {
        return $this->supplement_id !== null;
    }

    // The row of a supply is made, changed and removed with it (Supplement::boot). Its tank is the nozzle's when the row is made (and again when the supply
    // is moved to another nozzle); after that it stays, so connecting the nozzle to another tank later does not move what was supplied before.
    public static function syncSupplement(Supplement $supplement)
    {
        $row = static::withTrashed()->where('supplement_id', $supplement->id)->first();

        if ($supplement->trashed()) {
            if ($row && !$row->trashed()) {
                $row->delete();
            }

            return;
        }

        $tankId = $row ? $row->tank_id : null;
        if ($tankId === null || $supplement->wasChanged('nozzle_id')) {
            $nozzle = Nozzle::withTrashed()->find($supplement->nozzle_id);
            $tankId = $nozzle ? $nozzle->tank_id : $tankId;
        }
        if ($tankId === null) {
            return; // a supply of a nozzle that is not known leaves no tank to take it from
        }

        $row = $row ?: new static;
        $row->forceFill([
            'tank_id' => $tankId,
            'supplement_id' => $supplement->id,
            'outbound_date' => $supplement->supply_day,
            'amount_kg' => $supplement->out_amount_kg,
            'deleted_at' => null,
        ])->save();
    }

    // The supply is gone for good (a backup cleared it, or it was deleted for ever): so is its row.
    public static function dropSupplement(Supplement $supplement)
    {
        static::withTrashed()->where('supplement_id', $supplement->id)->forceDelete();
    }

    // Rows for the supplies that have none (a backup imported from an older export, rows added by a statement that raised no event).
    public static function syncMissing()
    {
        Supplement::whereNotIn('id', static::withTrashed()->whereNotNull('supplement_id')->select('supplement_id'))->get()->each(function ($supplement) {
            static::syncSupplement($supplement);
        });
    }
}
