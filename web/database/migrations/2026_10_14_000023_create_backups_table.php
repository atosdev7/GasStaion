<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The daily database dumps (and what was cleared after them).
class CreateBackupsTable extends Migration
{
    public function up()
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('up_to_date');
            $table->string('file');
            $table->text('cleared');
            $table->text('tank_carry')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('backups');
    }
}
