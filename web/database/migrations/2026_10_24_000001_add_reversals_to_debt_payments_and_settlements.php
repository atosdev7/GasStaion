<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A wrong debt payment or ticket loan settlement is undone by a counter entry, as a return is: a new row with a negative amount that points at the
// row it reverses, dated the day it is made. The wrong row stays, so the days that were closed keep their figures. The balances add up the
// rows, the reversal included.
class AddReversalsToDebtPaymentsAndSettlements extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('debt_payments', 'reverses_payment_id')) {
            Schema::table('debt_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('reverses_payment_id')->nullable()->index();
            });
        }
        if (!Schema::hasColumn('ticket_loan_settlements', 'reverses_settlement_id')) {
            Schema::table('ticket_loan_settlements', function (Blueprint $table) {
                $table->char('reverses_settlement_id', 36)->nullable()->index();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('debt_payments', 'reverses_payment_id')) {
            Schema::table('debt_payments', function (Blueprint $table) {
                $table->dropColumn('reverses_payment_id');
            });
        }
        if (Schema::hasColumn('ticket_loan_settlements', 'reverses_settlement_id')) {
            Schema::table('ticket_loan_settlements', function (Blueprint $table) {
                $table->dropColumn('reverses_settlement_id');
            });
        }
    }
}
