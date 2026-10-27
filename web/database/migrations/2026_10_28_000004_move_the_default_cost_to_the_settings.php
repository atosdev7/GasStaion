<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The default cost of a fuel type is its own setting (default_cost_<fuel type>), no longer a flag on a fuel inbound: it is the value the cost of a sale starts with, set
// with the "Make default cost" button of an inbound or a tank balance. The cost of the inbound that was the default becomes the setting, and the flag and its
// period (is_default, default_from, default_until) are gone from fuel_inbounds. There is no history of defaults: a sale keeps the cost it was made with.
class MoveTheDefaultCostToTheSettings extends Migration
{
    public function up()
    {
        foreach (DB::table('fuel_inbounds')->whereNull('deleted_at')->where('is_default', 1)->orderBy('default_from')->get() as $row) {
            // (the newest default of a fuel type is the last one read)
            DB::table('settings')->updateOrInsert(['key' => 'default_cost_' . $row->fuel_type], [
                'value' => number_format((float) $row->cost, 4, '.', ''), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::statement('ALTER TABLE fuel_inbounds DROP INDEX fuel_inbounds_fuel_type_is_default_index');
        DB::statement('ALTER TABLE fuel_inbounds DROP COLUMN is_default, DROP COLUMN default_from, DROP COLUMN default_until');
    }

    public function down()
    {
        DB::statement('ALTER TABLE fuel_inbounds ADD is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER cost, ADD default_from DATETIME NULL AFTER is_default, ADD default_until DATETIME NULL AFTER default_from');
        DB::statement('ALTER TABLE fuel_inbounds ADD INDEX fuel_inbounds_fuel_type_is_default_index (fuel_type, is_default)');

        // the newest inbound of each fuel type that has the default cost is the default again
        foreach (DB::table('settings')->where('key', 'like', 'default_cost_%')->get() as $setting) {
            $fuel = substr($setting->key, strlen('default_cost_'));
            $row = DB::table('fuel_inbounds')->where('fuel_type', $fuel)->whereNull('deleted_at')->whereRaw('ROUND(cost, 4) = ?', [round((float) $setting->value, 4)])
                ->orderByDesc('inbound_date')->orderByDesc('id')->first();
            if ($row) {
                DB::table('fuel_inbounds')->where('id', $row->id)->update(['is_default' => 1, 'default_from' => now()]);
            }
        }
        DB::table('settings')->where('key', 'like', 'default_cost_%')->delete();
    }
}
