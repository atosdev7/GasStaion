<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What printing the blank tickets at the press cost, per ticket (not the cost of the fuel). Optional; the total of a record is quantity x cost.
class AddCostToTicketInboundsTable extends Migration
{
    public function up()
    {
        Schema::table('ticket_inbounds', function (Blueprint $table) {
            $table->decimal('cost', 14, 4)->nullable();
        });
    }

    public function down()
    {
        Schema::table('ticket_inbounds', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
}
