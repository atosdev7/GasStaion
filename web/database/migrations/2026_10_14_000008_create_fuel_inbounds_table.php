<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fuel delivered to a tank with its cost per kg. The row marked is_default is the cost used for the period default_from .. default_until.
class CreateFuelInboundsTable extends Migration
{
    public function up()
    {
        Schema::create('fuel_inbounds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tank_id');
            $table->string('fuel_type', 4);
            $table->decimal('amount_kg', 14, 2);
            $table->decimal('cost', 14, 4);
            $table->boolean('is_default')->default(false);
            $table->dateTime('default_from')->nullable();
            $table->dateTime('default_until')->nullable();
            $table->date('inbound_date');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fuel_type', 'is_default']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('fuel_inbounds');
    }
}
