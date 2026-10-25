<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fuel that left a tank, as the counterpart of fuel_inbounds. A row is either made by hand (transfer, sample, returned to the supplier: supplement_id is
// empty) or follows a supply (supplement_id is set: it is made, changed and removed with the supplement, and the tank is the one the nozzle drew from at the
// time, so a nozzle that is later connected to another tank does not move old supplies). The stock of a tank is the opening balance + the inbounds - the
// outbounds.
class CreateFuelOutboundsTable extends Migration
{
    public function up()
    {
        Schema::create('fuel_outbounds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tank_id');
            $table->char('supplement_id', 36)->nullable()->unique();
            $table->date('outbound_date');
            $table->decimal('amount_kg', 14, 2);
            $table->string('to_name', 100)->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tank_id', 'outbound_date']);
        });

        // the supplies that exist already get their rows
        DB::statement(
            "INSERT INTO fuel_outbounds (tank_id, supplement_id, outbound_date, amount_kg, created_at, updated_at)
             SELECT nozzles.tank_id, supplements.id, COALESCE(supplements.supply_date, DATE(supplements.supplied_at)), supplements.out_amount_kg, NOW(), NOW()
             FROM supplements JOIN nozzles ON nozzles.id = supplements.nozzle_id
             WHERE supplements.deleted_at IS NULL"
        );
    }

    public function down()
    {
        Schema::dropIfExists('fuel_outbounds');
    }
}
