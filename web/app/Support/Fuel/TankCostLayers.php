<?php

namespace App\Support\Fuel;

use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\Tank;
use App\Models\Fuel\TankBalance;

// The fuel in a tank per cost: the layers. What went into a cost (the tank balances and the fuel inbounds at that cost) less what came out of it (the fuel outbounds that
// have that cost: a supply, or an outbound by hand, takes its fuel from the layer of its cost). An outbound with no cost takes the oldest fuel first (every row made before
// the outbounds had costs). A layer can go below zero when more left at a cost than came in at it: it shows, and the stock is still right in total.
//
// layers() is the calculation itself (so a backup can use it on the rows it clears); all() is the layers of every tank as they are now.
class TankCostLayers
{
    /**
     * @param array $entries what is in: list of ['cost' => ?float, 'kg' => float, 'date' => 'Y-m-d' or '', 'note' => ?string, 'balance' => bool]
     * @param array $outByCost what left at a cost: cost key (number_format 4) => kg
     * @param float $unassigned what left with no cost: it takes the oldest fuel first
     * @return array list of ['cost' => ?float, 'kg' => float, 'of' => float (what came in), 'date' => 'Y-m-d' or '', 'notes' => [...], 'balance' => bool], kg not 0
     */
    public static function layers(array $entries, array $outByCost, $unassigned = 0.0)
    {
        $layers = [];
        foreach ($entries as $entry) {
            if ($entry['cost'] === null && $entry['kg'] < 0) {
                $unassigned += -$entry['kg'];       // fuel that left with no known cost, earlier than any layer there was: it takes the oldest fuel there is now
                continue;
            }
            $key = self::key($entry['cost']);
            if (!isset($layers[$key])) {
                $layers[$key] = ['cost' => $entry['cost'], 'kg' => 0.0, 'of' => 0.0, 'date' => '', 'first' => null, 'notes' => [], 'balance' => false];
            }
            $layers[$key]['kg'] = round($layers[$key]['kg'] + $entry['kg'], 2);
            $layers[$key]['of'] = round($layers[$key]['of'] + max(0, $entry['kg']), 2);
            $layers[$key]['date'] = max($layers[$key]['date'], (string) $entry['date']);                  // the newest day of the cost (shown)
            $layers[$key]['first'] = $layers[$key]['first'] === null ? (string) $entry['date'] : min($layers[$key]['first'], (string) $entry['date']);   // the oldest (the order it is used in)
            $layers[$key]['balance'] = $layers[$key]['balance'] || !empty($entry['balance']);
            if (!empty($entry['note']) && trim($entry['note']) !== '') {
                $layers[$key]['notes'][trim($entry['note'])] = true;
            }
        }

        // what left at a cost comes out of the layer of that cost
        foreach ($outByCost as $key => $kg) {
            if (!isset($layers[$key])) {
                $layers[$key] = ['cost' => (float) $key, 'kg' => 0.0, 'of' => 0.0, 'date' => '', 'first' => '', 'notes' => [], 'balance' => false];
            }
            $layers[$key]['kg'] = round($layers[$key]['kg'] - $kg, 2);
        }

        // what left with no cost takes the oldest fuel first (a layer with no day is the oldest)
        $left = round((float) $unassigned, 2);
        if ($left > 0.004) {
            $order = array_keys($layers);
            usort($order, function ($a, $b) use ($layers) {
                return strcmp((string) $layers[$a]['first'], (string) $layers[$b]['first']);
            });
            foreach ($order as $key) {
                if ($left <= 0.004) {
                    break;
                }
                if ($layers[$key]['kg'] <= 0.004) {
                    continue;
                }
                $take = min($left, $layers[$key]['kg']);
                $layers[$key]['kg'] = round($layers[$key]['kg'] - $take, 2);
                $left = round($left - $take, 2);
            }
            if ($left > 0.004) {
                $none = self::key(null);
                if (!isset($layers[$none])) {
                    $layers[$none] = ['cost' => null, 'kg' => 0.0, 'of' => 0.0, 'date' => '', 'first' => '', 'notes' => [], 'balance' => false];
                }
                $layers[$none]['kg'] = round($layers[$none]['kg'] - $left, 2);     // more left than there was: a layer below zero
            }
        }

        return array_values(array_filter(array_map(function ($layer) {
            $layer['notes'] = array_keys($layer['notes']);

            return $layer;
        }, $layers), function ($layer) {
            return abs($layer['kg']) > 0.004;
        }));
    }

    public static function key($cost)
    {
        return $cost === null ? 'none' : number_format((float) $cost, 4, '.', '');
    }

    /** The layers of one tank now (see layers()). */
    public static function forTank($tankId)
    {
        $entries = [];
        foreach (TankBalance::where('tank_id', $tankId)->get() as $row) {
            $entries[] = ['cost' => $row->cost === null ? null : round((float) $row->cost, 4), 'kg' => (float) $row->balance,
                'date' => $row->balance_date ? substr((string) $row->balance_date, 0, 10) : '', 'note' => $row->note, 'balance' => true];
        }
        foreach (FuelInbound::where('tank_id', $tankId)->get() as $row) {
            $entries[] = ['cost' => round((float) $row->cost, 4), 'kg' => (float) $row->amount_kg, 'date' => substr((string) $row->inbound_date, 0, 10), 'note' => null, 'balance' => false];
        }
        $assigned = [];
        $unassigned = 0.0;
        foreach (FuelOutbound::where('tank_id', $tankId)->selectRaw('cost, SUM(amount_kg) AS kg')->groupBy('cost')->get() as $row) {
            if ($row->cost === null) {
                $unassigned += (float) $row->kg;
            } else {
                $assigned[self::key($row->cost)] = (float) $row->kg;
            }
        }

        return self::layers($entries, $assigned, $unassigned);
    }

    /** The layers of every tank now: tank id => list of layers (see layers()). */
    public static function all()
    {
        $balances = TankBalance::all()->groupBy('tank_id');
        $inbounds = FuelInbound::all()->groupBy('tank_id');
        $outbounds = FuelOutbound::query()->selectRaw('tank_id, cost, SUM(amount_kg) AS kg')->groupBy('tank_id', 'cost')->get()->groupBy('tank_id');

        $result = [];
        foreach (Tank::pluck('id') as $tankId) {
            $entries = [];
            foreach ($balances->get($tankId, collect()) as $row) {
                $entries[] = ['cost' => $row->cost === null ? null : round((float) $row->cost, 4), 'kg' => (float) $row->balance,
                    'date' => $row->balance_date ? substr((string) $row->balance_date, 0, 10) : '', 'note' => $row->note, 'balance' => true];
            }
            foreach ($inbounds->get($tankId, collect()) as $row) {
                $entries[] = ['cost' => round((float) $row->cost, 4), 'kg' => (float) $row->amount_kg, 'date' => substr((string) $row->inbound_date, 0, 10), 'note' => null, 'balance' => false];
            }
            $assigned = [];
            $unassigned = 0.0;
            foreach ($outbounds->get($tankId, collect()) as $row) {
                if ($row->cost === null) {
                    $unassigned += (float) $row->kg;
                } else {
                    $assigned[self::key($row->cost)] = ($assigned[self::key($row->cost)] ?? 0) + (float) $row->kg;
                }
            }

            $result[$tankId] = self::layers($entries, $assigned, $unassigned);
        }

        return $result;
    }
}
