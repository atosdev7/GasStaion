<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The daily confirmation covers the fuel outbounds made by hand (by their outbound date; the row of a supply follows the supply, which is confirmed itself) and the
// settlements of the ticket loans (by their date). A confirmed outbound can no longer be changed or deleted; a manager can take a confirmation back. A settlement
// is never changed (a wrong one is reversed by a counter entry dated the day it is made); the terminal's change of its comment or date is refused once it is confirmed.
class AddConfirmationToFuelOutboundsAndLoanSettlements extends Migration
{
    public function up()
    {
        Schema::table('fuel_outbounds', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->index(['outbound_date', 'confirmed_at']);
        });
        Schema::table('ticket_loan_settlements', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->index(['date', 'confirmed_at']);
        });
    }

    public function down()
    {
        Schema::table('fuel_outbounds', function (Blueprint $table) {
            $table->dropIndex(['outbound_date', 'confirmed_at']);
            $table->dropColumn(['confirmed_at', 'confirmed_by']);
        });
        Schema::table('ticket_loan_settlements', function (Blueprint $table) {
            $table->dropIndex(['date', 'confirmed_at']);
            $table->dropColumn(['confirmed_at', 'confirmed_by']);
        });
    }
}