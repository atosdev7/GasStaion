<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Records the web app refused during a push, kept for the manager to look at.
class CreateSyncErrorsTable extends Migration
{
    public function up()
    {
        Schema::create('sync_errors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('entity', 40);
            $table->string('record_id', 40)->nullable();
            $table->text('payload')->nullable();
            $table->string('reason');
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->index(['resolved_at', 'id'], 'sync_errors_open');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sync_errors');
    }
}
