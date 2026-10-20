<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A published range of ticket serial numbers of one fuel type and spec. `date` is the day it was published (the daily confirmation date).
class CreateTicketPublishesTable extends Migration
{
    public function up()
    {
        Schema::create('ticket_publishes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('fuel_type', 4);
            $table->unsignedTinyInteger('spec');
            $table->char('code', 2);
            $table->unsignedInteger('start_serial');
            $table->unsignedInteger('end_serial');
            $table->unsignedInteger('count');
            $table->date('date')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fuel_type', 'spec', 'start_serial', 'end_serial'], 'ticket_publishes_overlap_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_publishes');
    }
}
