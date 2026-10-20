<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Printed tickets received (paper stock) by fuel type and spec.
class CreateTicketInboundsTable extends Migration
{
    public function up()
    {
        Schema::create('ticket_inbounds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('fuel_type', 4);
            $table->unsignedTinyInteger('spec');
            $table->unsignedInteger('quantity');
            $table->date('inbound_date');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fuel_type', 'spec']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_inbounds');
    }
}
