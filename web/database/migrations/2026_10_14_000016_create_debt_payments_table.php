<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A payment of a customer's debt. paid_date is the day the money came, `date` the daily confirmation date.
class CreateDebtPaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->index();
            $table->char('debt_id', 36)->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 10)->default('cash');
            $table->date('paid_date');
            $table->date('date')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('debt_payments');
    }
}
