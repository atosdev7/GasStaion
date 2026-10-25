<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A free payment card (set by the manager or the admin on a card that is registered already): it may be topped up for nothing, with a price of 0. Every other
// card is topped up with a price above 0.
class AddFreePaymentToCardsTable extends Migration
{
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->boolean('free_payment')->default(false)->after('status');
        });
    }

    public function down()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('free_payment');
        });
    }
}
