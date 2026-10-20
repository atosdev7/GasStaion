<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Where the blank tickets came from (free text), next to the date they were received.
class AddFromNameToTicketInboundsTable extends Migration
{
    public function up()
    {
        Schema::table('ticket_inbounds', function (Blueprint $table) {
            $table->string('from_name', 100)->nullable()->after('inbound_date');
        });
    }

    public function down()
    {
        Schema::table('ticket_inbounds', function (Blueprint $table) {
            $table->dropColumn('from_name');
        });
    }
}
