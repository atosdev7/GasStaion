<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The opening stock of a tank has the fields of a fuel inbound: the day it counts from (balance_date), what the fuel cost per kg (cost), a note and who entered
// it. The cost is the cost of the sales until the first fuel inbound is entered, and the stock is valued with it (see FuelInbound::openingCost).
class ExtendInitialTankBalancesTable extends Migration
{
    public function up()
    {
        Schema::table('initial_tank_balances', function (Blueprint $table) {
            $table->decimal('cost', 14, 4)->nullable()->after('amount_kg');
            $table->date('balance_date')->nullable()->after('cost');
            $table->string('note', 255)->nullable()->after('balance_date');
            $table->unsignedBigInteger('user_id')->nullable()->after('note');
        });
    }

    public function down()
    {
        Schema::table('initial_tank_balances', function (Blueprint $table) {
            $table->dropColumn(['cost', 'balance_date', 'note', 'user_id']);
        });
    }
}
