<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A supply has a cost per kg (supplements.cost): the cost of the fuel that left the tank, chosen from the costs of the fuel in the tank (a supply a terminal uploads gets the
// default cost of the fuel type; the accountant can change it before confirming). The outbound row that follows the supply carries the cost (fuel_outbounds.cost), and an
// outbound made by hand can have one too: the fuel is taken from the layer of that cost, so the stock of a tank is known per cost (see TankCostLayers). An outbound with
// no cost (all the rows made before this) takes the oldest fuel first, as before.
class AddCostToSupplementsAndFuelOutbounds extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE supplements ADD cost DECIMAL(14,4) NULL AFTER out_amount_kg');
        DB::statement('ALTER TABLE fuel_outbounds ADD cost DECIMAL(14,4) NULL AFTER amount_kg');
    }

    public function down()
    {
        DB::statement('ALTER TABLE fuel_outbounds DROP COLUMN cost');
        DB::statement('ALTER TABLE supplements DROP COLUMN cost');
    }
}
