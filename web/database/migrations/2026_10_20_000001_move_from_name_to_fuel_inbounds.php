<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "From" is where the station gets its fuel, so it belongs to the fuel inbound (it was added to the ticket inbound by mistake).
// The ticket inbound had no values in it when this was written.
class MoveFromNameToFuelInbounds extends Migration
{
    public function up()
    {
        Schema::table('fuel_inbounds', function (Blueprint $table) {
            $table->string('from_name', 100)->nullable()->after('inbound_date');
        });
        if (Schema::hasColumn('ticket_inbounds', 'from_name')) {
            Schema::table('ticket_inbounds', function (Blueprint $table) {
                $table->dropColumn('from_name');
            });
        }
    }

    public function down()
    {
        Schema::table('ticket_inbounds', function (Blueprint $table) {
            $table->string('from_name', 100)->nullable()->after('inbound_date');
        });
        Schema::table('fuel_inbounds', function (Blueprint $table) {
            $table->dropColumn('from_name');
        });
    }
}
