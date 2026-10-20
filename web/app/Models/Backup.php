<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $casts = [
        'cleared' => 'array',
        'tank_carry' => 'array',
    ];

    /** Total kg carried per tank id across all backups. */
    public static function tankCarryTotals()
    {
        $totals = [];
        foreach (static::all()->pluck('tank_carry') as $carry) {
            foreach ((array) $carry as $tankId => $kg) {
                $totals[$tankId] = ($totals[$tankId] ?? 0) + (float) $kg;
            }
        }

        return $totals;
    }
}
