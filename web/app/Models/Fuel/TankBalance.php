<?php

namespace App\Models\Fuel;

use Illuminate\Database\Eloquent\Model;

// Fuel in a tank at one cost, as a fuel inbound is, but already there: the tank and its fuel type, the cost per kg, the balance (kg), the day it counts from, a note,
// and who entered it. A tank has as many rows as it has costs. Table: tank_balances.
// - source manual: entered by hand (Initialize > Tanks), the balance the tank starts with at go-live.
// - source backup (backup_id): made by a backup that cleared fuel inbounds and outbounds: what is left of them, one row per cost (see BackupRun).
// The stock of a tank is the sum of its rows plus the fuel inbounds, less the fuel outbounds, that are still in the tables (see TankLevels).
class TankBalance extends Model
{
    const MANUAL = 'manual';
    const BACKUP = 'backup';

    protected $fillable = ['tank_id', 'fuel_type', 'cost', 'balance', 'balance_date', 'note', 'source', 'backup_id', 'user_id'];

    // The fuel type is the tank's, as on a fuel inbound: it is kept on the row, whoever makes the row.
    protected static function boot()
    {
        parent::boot();

        static::saving(function (TankBalance $row) {
            $tank = Tank::withTrashed()->find($row->tank_id);
            if ($tank) {
                $row->fuel_type = $tank->fuel_type;
            }
        });
    }

    public function isManual()
    {
        return $this->source !== self::BACKUP;
    }

    public function tank()
    {
        return $this->belongsTo(Tank::class)->withTrashed();
    }
}
