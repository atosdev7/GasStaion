<?php

namespace App\Support\Fuel;

use App\Models\Fuel\FuelInbound;
use App\Support\Dashboard\TankLevels;

// The stock of the tanks by fuel type, and what it cost.
//
// Balance: what TankLevels says of each tank (its balances + inbound - supplied), added up per fuel type.
// Cost: the fuel in the tanks per cost (TankCostLayers): what came in at a cost (the tank balances and the fuel inbounds) less what went out at that cost (a supply, or an
// outbound by hand, takes its fuel from the layer of its cost; one with no cost takes the oldest fuel first), added up over the tanks of the fuel type. That gives the layers
// of the stock (a cost per kg with the kg left at that cost, newest first), the value of the stock and its average cost. Fuel with no known cost is shown as such and is not
// valued; a layer below zero (more left at a cost than came in at it) is shown with its kg below zero.
class TankStockSummary
{
    /**
     * @return array [
     *   'fuels' => [fuel_type => ['fuel_type', 'tanks', 'balance', 'capacity', 'layers' => [['inbound_date', 'cost', 'kg', 'of', 'value', 'is_default', 'from_balance']],
     *                             'valued_kg', 'unknown_kg', 'value', 'average', 'default_cost']],
     *   'tanks' => the rows of TankLevels,
     *   'value' => the value of all the fuel types
     * ]
     */
    public static function build()
    {
        $levels = TankLevels::all();
        $byTank = TankCostLayers::all();
        $fuels = [];

        foreach ($levels->groupBy(function ($row) {
            return $row->tank->fuel_type;
        }) as $fuelType => $rows) {
            $balance = round($rows->sum('current'), 2);
            $capacity = $rows->contains(function ($row) {
                return $row->capacity === null;
            }) ? null : round($rows->sum('capacity'), 2);

            // the layers of the tanks of this fuel type, one per cost
            $merged = [];
            $unknown = 0.0;
            foreach ($rows as $row) {
                foreach ($byTank[$row->tank->id] ?? [] as $layer) {
                    if ($layer['cost'] === null) {
                        $unknown += $layer['kg'];
                        continue;
                    }
                    $key = TankCostLayers::key($layer['cost']);
                    if (!isset($merged[$key])) {
                        $merged[$key] = ['cost' => (float) $layer['cost'], 'kg' => 0.0, 'of' => 0.0, 'date' => '', 'balance' => false];
                    }
                    $merged[$key]['kg'] = round($merged[$key]['kg'] + $layer['kg'], 2);
                    $merged[$key]['of'] = round($merged[$key]['of'] + $layer['of'], 2);
                    $merged[$key]['date'] = max($merged[$key]['date'], $layer['date']);
                    $merged[$key]['balance'] = $merged[$key]['balance'] || $layer['balance'];
                }
            }
            uasort($merged, function ($a, $b) {
                return strcmp($b['date'], $a['date']) ?: ($b['cost'] <=> $a['cost']);      // the newest first
            });

            $layers = [];
            foreach ($merged as $layer) {
                if (abs($layer['kg']) <= 0.004) {
                    continue;
                }
                $layers[] = [
                    'inbound_date' => $layer['date'],
                    'cost' => $layer['cost'],
                    'kg' => $layer['kg'],
                    'of' => $layer['of'],
                    'value' => round($layer['kg'] * $layer['cost'], 2),
                    'is_default' => DefaultCost::is($fuelType, $layer['cost']),
                    'from_balance' => $layer['balance'],
                ];
            }

            $valuedKg = round(array_sum(array_column($layers, 'kg')), 2);
            $value = round(array_sum(array_column($layers, 'value')), 2);
            $fuels[$fuelType] = [
                'fuel_type' => $fuelType,
                'tanks' => $rows->count(),
                'balance' => $balance,
                'capacity' => $capacity,
                'layers' => $layers,
                'valued_kg' => $valuedKg,
                'unknown_kg' => round($balance - $valuedKg, 2),           // no cost is known for it
                'value' => $value,
                'average' => $valuedKg > 0 ? round($value / $valuedKg, 4) : null,
                'default_cost' => DefaultCost::get($fuelType),
            ];
        }
        ksort($fuels);

        return [
            'fuels' => $fuels,
            'tanks' => $levels,
            'value' => round(array_sum(array_column($fuels, 'value')), 2),
        ];
    }
}
