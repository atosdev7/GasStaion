<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// initial_tank_balances is now tank_balances, and its fields are the ones the user named: tank_id, fuel_type (the fuel type of the tank, kept on the row like a fuel
// inbound does), cost (per kg), balance (the kg, was amount_kg), balance_date and note. The unique index of the tank follows the table name.
class RenameInitialTankBalancesToTankBalancesTable extends Migration
{
    public function up()
    {
        DB::statement('RENAME TABLE initial_tank_balances TO tank_balances');
        DB::statement('ALTER TABLE tank_balances CHANGE amount_kg balance DECIMAL(14,2) NOT NULL');
        DB::statement("ALTER TABLE tank_balances ADD fuel_type VARCHAR(4) NOT NULL DEFAULT '' AFTER tank_id");
        DB::statement('UPDATE tank_balances b JOIN tanks t ON t.id = b.tank_id SET b.fuel_type = t.fuel_type');
        DB::statement('ALTER TABLE tank_balances DROP INDEX initial_tank_balances_tank_id_unique, ADD UNIQUE tank_balances_tank_id_unique (tank_id)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE tank_balances DROP INDEX tank_balances_tank_id_unique, ADD UNIQUE initial_tank_balances_tank_id_unique (tank_id)');
        DB::statement('ALTER TABLE tank_balances DROP COLUMN fuel_type');
        DB::statement('ALTER TABLE tank_balances CHANGE balance amount_kg DECIMAL(14,2) NOT NULL');
        DB::statement('RENAME TABLE tank_balances TO initial_tank_balances');
    }
}
