<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The daily confirmation covers the blank ticket stock too: a ticket inbound (by its inbound date) and a ticket publish (by its date) are confirmed with
// the sales and supplements of their day. A confirmed inbound can no longer be changed or deleted; a manager can take the confirmation back.
// The quantity of a ticket inbound may be negative (blank tickets spoiled in printing are taken out of the stock): the column is signed now.
class AddConfirmationToTicketInboundsAndPublishes extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE ticket_inbounds MODIFY quantity INT NOT NULL');
        Schema::table('ticket_inbounds', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->index(['inbound_date', 'confirmed_at']);
        });
        Schema::table('ticket_publishes', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->index(['date', 'confirmed_at']);
        });
    }

    public function down()
    {
        DB::statement('ALTER TABLE ticket_inbounds MODIFY quantity INT UNSIGNED NOT NULL'); // (refused while a record has a negative quantity)
        Schema::table('ticket_inbounds', function (Blueprint $table) {
            $table->dropIndex(['inbound_date', 'confirmed_at']);
            $table->dropColumn(['confirmed_at', 'confirmed_by']);
        });
        Schema::table('ticket_publishes', function (Blueprint $table) {
            $table->dropIndex(['date', 'confirmed_at']);
            $table->dropColumn(['confirmed_at', 'confirmed_by']);
        });
    }
}
