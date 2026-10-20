<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The opening balance of a tank (one row per tank).
class CreateInitialTankBalancesTable extends Migration
{
    public function up()
    {
        Schema::create('initial_tank_balances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tank_id')->unique();
            $table->decimal('amount_kg', 14, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('initial_tank_balances');
    }
}
