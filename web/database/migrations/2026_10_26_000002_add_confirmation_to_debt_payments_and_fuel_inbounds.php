<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The daily confirmation covers the money received and the fuel that came in too: a debt payment (by the day the money came, paid_date) and a fuel inbound (by its
// inbound date) are confirmed with the sales and supplements of their day. A confirmed fuel inbound can no longer be changed or deleted; a manager can take a
// confirmation back. A payment is never changed (a wrong one is reversed by a counter entry dated the day it is made), so confirming it only says it was checked.
class AddConfirmationToDebtPaymentsAndFuelInbounds extends Migration
{
    public function up()
    {
        Schema::table('debt_payments', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->index(['paid_date', 'confirmed_at']);
        });
        Schema::table('fuel_inbounds', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->index(['inbound_date', 'confirmed_at']);
        });
    }

    public function down()
    {
        Schema::table('debt_payments', function (Blueprint $table) {
            $table->dropIndex(['paid_date', 'confirmed_at']);
            $table->dropColumn(['confirmed_at', 'confirmed_by']);
        });
        Schema::table('fuel_inbounds', function (Blueprint $table) {
            $table->dropIndex(['inbound_date', 'confirmed_at']);
            $table->dropColumn(['confirmed_at', 'confirmed_by']);
        });
    }
}