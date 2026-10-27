<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// tank_balances works like fuel_inbounds: a tank can have several rows, each the fuel of one cost (cost, balance, balance_date, note). A row is entered by hand (source
// manual) or made by a backup (source backup, backup_id): when a backup clears the fuel inbounds and outbounds, what is left of them is written here, one row per cost.
// What the earlier backups carried forward (backups.tank_carry, kg per tank, no cost) becomes rows with no cost, so the levels of the tanks stay as they were.
class MakeTankBalancesAListOfCostLayers extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE tank_balances DROP INDEX tank_balances_tank_id_unique, ADD INDEX tank_balances_tank_id_index (tank_id)');
        DB::statement("ALTER TABLE tank_balances ADD source VARCHAR(10) NOT NULL DEFAULT 'manual' AFTER note, ADD backup_id BIGINT UNSIGNED NULL AFTER source");

        $fuelTypes = DB::table('tanks')->pluck('fuel_type', 'id');
        foreach (DB::table('backups')->orderBy('id')->get() as $backup) {
            foreach ((array) json_decode((string) $backup->tank_carry, true) as $tankId => $kg) {
                if (!isset($fuelTypes[$tankId]) || abs((float) $kg) < 0.005) {
                    continue;
                }
                DB::table('tank_balances')->insert([
                    'tank_id' => $tankId, 'fuel_type' => $fuelTypes[$tankId], 'balance' => round((float) $kg, 2), 'cost' => null,
                    'balance_date' => substr((string) $backup->up_to_date, 0, 10), 'note' => 'carried forward by backup #' . $backup->id,
                    'source' => 'backup', 'backup_id' => $backup->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        DB::table('tank_balances')->where('source', 'backup')->delete();
        DB::statement('ALTER TABLE tank_balances DROP COLUMN backup_id, DROP COLUMN source');
        DB::statement('ALTER TABLE tank_balances DROP INDEX tank_balances_tank_id_index, ADD UNIQUE tank_balances_tank_id_unique (tank_id)');
    }
}
