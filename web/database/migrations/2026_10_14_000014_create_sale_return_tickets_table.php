<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The tickets given back when a ticket sale is returned.
class CreateSaleReturnTicketsTable extends Migration
{
    public function up()
    {
        Schema::create('sale_return_tickets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('sale_id', 36)->index();
            $table->char('ticket_barcode', 16)->index();
            $table->decimal('amount_kg', 12, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sale_return_tickets');
    }
}
