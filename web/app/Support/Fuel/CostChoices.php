<?php

namespace App\Support\Fuel;

use App\Models\Fuel\Tank;

// The costs a sale or a supply of a fuel type can be made at: the costs of the fuel that is in the tanks now (the layers, see TankCostLayers), newest first, each with the kg
// left at that cost; and the default cost of the fuel type when it is not among them. The cost field of the sale forms is a list of these, with the default cost chosen to
// begin with. For a supply the list is the one of the tank it takes its fuel from (forTank), since it takes the fuel of the layer it chooses.
class CostChoices
{
    /** @return array fuel type => list of ['cost' => float, 'kg' => float, 'default' => bool] */
    public static function all()
    {
        $byTank = TankCostLayers::all();
        $tanks = Tank::pluck('fuel_type', 'id');
        $choices = [];

        foreach (array_keys(config('fuel_types')) as $fuelType) {
            $layers = [];
            foreach ($tanks as $tankId => $tankFuel) {
                if ($tankFuel === $fuelType) {
                    $layers = array_merge($layers, $byTank[$tankId] ?? []);
                }
            }
            $choices[$fuelType] = self::list($fuelType, $layers);
        }

        return $choices;
    }

    /**
     * The cost of a supply a terminal uploads (it cannot choose): the default cost of the fuel type when the tank has fuel at that cost, otherwise none (the fuel then
     * comes from the oldest layer), so the upload never makes a layer go below zero by itself. The accountant can change it before confirming.
     */
    public static function costForNewSupply($tankId)
    {
        $tank = Tank::withTrashed()->find($tankId);
        $default = $tank ? DefaultCost::get($tank->fuel_type) : null;
        if ($default === null) {
            return null;
        }

        foreach (TankCostLayers::forTank($tank->id) as $layer) {
            if ($layer['cost'] !== null && $layer['kg'] > 0.004 && abs($layer['cost'] - $default) < 0.00005) {
                return $default;
            }
        }

        return null;
    }

    /** The choices of every tank (an outbound by hand takes its fuel from the layer of its cost in the tank): tank id => list. */
    public static function byTank()
    {
        $byTank = TankCostLayers::all();
        $result = [];
        foreach (Tank::pluck('fuel_type', 'id') as $tankId => $fuelType) {
            $result[$tankId] = self::list($fuelType, $byTank[$tankId] ?? []);
        }

        return $result;
    }

    /** The choices of the tank of every nozzle (a supply takes its fuel from the tank of its nozzle): nozzle id => list. */
    public static function byNozzle()
    {
        $byTank = TankCostLayers::all();
        $tanks = Tank::pluck('fuel_type', 'id');
        $result = [];
        foreach (\App\Models\Fuel\Nozzle::all() as $nozzle) {
            $result[$nozzle->id] = isset($tanks[$nozzle->tank_id]) ? self::list($tanks[$nozzle->tank_id], $byTank[$nozzle->tank_id] ?? []) : [];
        }

        return $result;
    }

    /** The choices of one tank (the fuel type is the tank's), plus a cost a record already has when it is not in the list. */
    public static function forTank($tankId, $extraCost = null)
    {
        $tank = Tank::withTrashed()->find($tankId);
        if (!$tank) {
            return [];
        }

        return self::withExtra(self::list($tank->fuel_type, TankCostLayers::all()[$tank->id] ?? []), $tank->fuel_type, $extraCost);
    }

    // The same for one fuel type, plus a cost a sale already has when it is not in the list (an old sale whose fuel is gone from the tanks).
    public static function forFuel($fuelType, $extraCost = null)
    {
        return self::withExtra(self::all()[$fuelType] ?? [], $fuelType, $extraCost);
    }

    // The layers with fuel left, one per cost, newest first; and the default cost when it is not among them.
    private static function list($fuelType, array $layers)
    {
        usort($layers, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $byCost = [];
        foreach ($layers as $layer) {
            if ($layer['cost'] === null || $layer['kg'] <= 0.004) {
                continue;
            }
            $key = TankCostLayers::key($layer['cost']);
            $byCost[$key] = ['cost' => round((float) $layer['cost'], 4), 'kg' => round(($byCost[$key]['kg'] ?? 0) + $layer['kg'], 2)];
        }

        $default = DefaultCost::get($fuelType);
        if ($default !== null && !isset($byCost[TankCostLayers::key($default)])) {
            $byCost[TankCostLayers::key($default)] = ['cost' => $default, 'kg' => 0.0];
        }

        return array_values(array_map(function ($row) use ($fuelType) {
            return $row + ['default' => DefaultCost::is($fuelType, $row['cost'])];
        }, $byCost));
    }

    private static function withExtra(array $list, $fuelType, $extraCost)
    {
        if ($extraCost !== null && $extraCost !== '') {
            $has = array_filter($list, function ($row) use ($extraCost) {
                return abs($row['cost'] - round((float) $extraCost, 4)) < 0.00005;
            });
            if (!$has) {
                $list[] = ['cost' => round((float) $extraCost, 4), 'kg' => 0.0, 'default' => DefaultCost::is($fuelType, $extraCost)];
            }
        }

        return $list;
    }
}
