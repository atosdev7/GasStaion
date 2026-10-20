<?php

namespace App\Support\Dashboard;

use App\Models\Admin\Backup;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\InitialTankBalance;
use App\Models\Fuel\Tank;
use App\Models\Supplements\Supplement;

// Current stock per tank: opening balance + what backups carried forward + fuel inbound - supplied
// (supplements, via the nozzle's tank). All in kg.
class TankLevels
{
    /** @return \Illuminate\Support\Collection of objects: tank, opening, inbound, supplied, current, capacity, percent */
    public static function all()
    {
        $opening = InitialTankBalance::pluck('amount_kg', 'tank_id');
        $carried = Backup::tankCarryTotals();
        $inbound = FuelInbound::query()->selectRaw('tank_id, SUM(amount_kg) AS kg')->groupBy('tank_id')->pluck('kg', 'tank_id');
        $supplied = Supplement::query()
            ->join('nozzles', 'nozzles.id', '=', 'supplements.nozzle_id')
            ->selectRaw('nozzles.tank_id, SUM(supplements.out_amount_kg) AS kg')
            ->groupBy('nozzles.tank_id')
            ->pluck('kg', 'tank_id');

        return Tank::orderBy('name')->get()->map(function ($tank) use ($opening, $carried, $inbound, $supplied) {
            $row = (object) [
                'tank' => $tank,
                'opening' => (float) ($opening[$tank->id] ?? 0) + (float) ($carried[$tank->id] ?? 0),
                'inbound' => (float) ($inbound[$tank->id] ?? 0),
                'supplied' => (float) ($supplied[$tank->id] ?? 0),
                'capacity' => $tank->capacity_kg !== null ? (float) $tank->capacity_kg : null,
            ];
            $row->current = $row->opening + $row->inbound - $row->supplied;
            $row->percent = $row->capacity ? round($row->current / $row->capacity * 100, 1) : null;

            return $row;
        });
    }
}
