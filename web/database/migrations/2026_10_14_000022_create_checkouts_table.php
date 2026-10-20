<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A checkout of a terminal: the records of `date` were stamped with their date (mode auto at 9:30 or manual).
class CreateCheckoutsTable extends Migration
{
    public function up()
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_id')->nullable();
            $table->date('date');
            $table->string('mode', 10);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('checkouts');
    }
}
