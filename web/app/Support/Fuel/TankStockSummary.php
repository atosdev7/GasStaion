<?php

namespace App\Support\Fuel;

use App\Models\Fuel\FuelInbound;
use App\Support\Dashboard\TankLevels;

// The stock of the tanks by fuel type, and what it cost.
//
// Balance: what TankLevels says of each tank (opening + carried forward + inbound - supplied), added up per fuel type.
// Cost: the fuel that is left is the fuel that came in last (FIFO): the newest inbounds are taken, newest first, until they add up to the balance, each
// at its own cost per kg. That gives the layers of the stock (a cost per kg with the kg left at that cost), the value of the stock and its average cost.
// What the inbounds do not cover (the opening balance of the go-live, or stock carried forward by a backup that cleared its inbounds) has no known cost:
// it is shown as such and is not valued. Deleted inbounds do not count.
class TankStockSummary
{
    /**
     * @return array [
     *   'fuels' => [fuel_type => ['fuel_type', 'tanks', 'balance', 'capacity', 'layers' => [['inbound_date', 'cost', 'kg', 'of', 'value', 'is_default']],
     *                             'valued_kg', 'unknown_kg', 'value', 'average', 'default_cost']],
     *   'tanks' => the rows of TankLevels,
     *   'value' => the value of all the fuel types
     * ]
     */
    public static function build()
    {
        $levels = TankLevels::all();
        $fuels = [];

        foreach ($levels->groupBy(function ($row) {
            return $row->tank->fuel_type;
        }) as $fuelType => $rows) {
            $balance = round($rows->sum('current'), 2);
            $capacity = $rows->contains(function ($row) {
                return $row->capacity === null;
            }) ? null : round($rows->sum('capacity'), 2);

            // the newest inbounds first: what is in the tanks is what came last
            $layers = [];
            $left = max(0, $balance);
            foreach (FuelInbound::where('fuel_type', $fuelType)->orderByDesc('inbound_date')->orderByDesc('id')->get() as $inbound) {
                if ($left <= 0.004) {
                    break;
                }
                $kg = round(min($left, (float) $inbound->amount_kg), 2);
                $layers[] = [
                    'inbound_date' => substr((string) $inbound->inbound_date, 0, 10),
                    'cost' => (float) $inbound->cost,
                    'kg' => $kg,
                    'of' => round((float) $inbound->amount_kg, 2),
                    'value' => round($kg * (float) $inbound->cost, 2),
                    'is_default' => (bool) $inbound->is_default,
                ];
                $left = round($left - $kg, 2);
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
                'unknown_kg' => round(max(0, $balance) - $valuedKg, 2),   // no inbound left to say what it cost
                'value' => $value,
                'average' => $valuedKg > 0 ? round($value / $valuedKg, 4) : null,
                'default_cost' => FuelInbound::defaultCost($fuelType),
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
