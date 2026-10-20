<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The lines of a ticket sale as the cashier entered them: a range of serials of one spec.
class CreateSaleItemsTable extends Migration
{
    public function up()
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('sale_id', 36)->index();
            $table->unsignedTinyInteger('spec');
            $table->unsignedInteger('start_serial');
            $table->unsignedInteger('end_serial');
            $table->unsignedInteger('quantity');
            $table->decimal('amount_kg', 12, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sale_items');
    }
}
