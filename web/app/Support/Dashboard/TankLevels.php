<?php

namespace App\Support\Dashboard;

use App\Models\Admin\Backup;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\InitialTankBalance;
use App\Models\Fuel\Tank;

// Current stock per tank: opening balance + what backups carried forward + fuel inbound - fuel outbound (the supplies, whose rows follow them with the tank
// the nozzle drew from at the time, and the outbounds made by hand). Deleted rows do not count. All in kg.
class TankLevels
{
    /** @return \Illuminate\Support\Collection of objects: tank, opening, inbound, supplied, current, capacity, percent */
    public static function all()
    {
        $opening = InitialTankBalance::pluck('amount_kg', 'tank_id');
        $carried = Backup::tankCarryTotals();
        $inbound = FuelInbound::query()->selectRaw('tank_id, SUM(amount_kg) AS kg')->groupBy('tank_id')->pluck('kg', 'tank_id');
        // what left each tank: the supplies (their rows follow them) and the outbounds made by hand; deleted rows do not count
        $supplied = FuelOutbound::query()->whereNotNull('supplement_id')->selectRaw('tank_id, SUM(amount_kg) AS kg')->groupBy('tank_id')->pluck('kg', 'tank_id');
        $other = FuelOutbound::query()->whereNull('supplement_id')->selectRaw('tank_id, SUM(amount_kg) AS kg')->groupBy('tank_id')->pluck('kg', 'tank_id');

        return Tank::orderBy('name')->get()->map(function ($tank) use ($opening, $carried, $inbound, $supplied, $other) {
            $row = (object) [
                'tank' => $tank,
                'opening' => (float) ($opening[$tank->id] ?? 0) + (float) ($carried[$tank->id] ?? 0),
                'inbound' => (float) ($inbound[$tank->id] ?? 0),
                'supplied' => (float) ($supplied[$tank->id] ?? 0) + (float) ($other[$tank->id] ?? 0), // everything that left: the supplies and the outbounds by hand
                'by_hand' => (float) ($other[$tank->id] ?? 0),                                         // ... of which made by hand
                'capacity' => $tank->capacity_kg !== null ? (float) $tank->capacity_kg : null,
            ];
            $row->current = $row->opening + $row->inbound - $row->supplied;
            $row->percent = $row->capacity ? round($row->current / $row->capacity * 100, 1) : null;

            return $row;
        });
    }
}
