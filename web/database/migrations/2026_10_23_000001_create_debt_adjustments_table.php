<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A counter entry on a debt: a return of the goods of the sale cuts what is owed. debts.amount stays as the sale made it, so the debts of
// the days that were already confirmed do not change afterwards; the cut is dated the day of the return. amount is negative.
class CreateDebtAdjustmentsTable extends Migration
{
    public function up()
    {
        Schema::create('debt_adjustments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('debt_id', 36)->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->decimal('amount', 14, 2);
            $table->date('adjusted_date')->index();
            $table->char('sale_id', 36)->nullable()->index();   // the return sale that made it
            $table->string('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('debt_adjustments');
    }
}
