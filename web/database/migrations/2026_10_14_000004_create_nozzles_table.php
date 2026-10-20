<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A nozzle belongs to one terminal (device_id) and is known there by its number; its board address is the address on the RS485 bus.
class CreateNozzlesTable extends Migration
{
    public function up()
    {
        Schema::create('nozzles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_id');
            $table->unsignedSmallInteger('number');
            $table->unsignedBigInteger('tank_id');
            $table->string('fuel_type', 4);
            $table->unsignedTinyInteger('board_address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['device_id', 'number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('nozzles');
    }
}
