<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A Smart210 terminal. It is known by its IP address (ip_address); the Machine Config PIN is kept as a salted SHA-256.
class CreateDevicesTable extends Migration
{
    public function up()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('pin_salt', 32)->nullable();
            $table->string('pin_hash', 64)->nullable();
            $table->unsignedSmallInteger('purge_days')->default(3);
            $table->boolean('initial_sync_done')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('devices');
    }
}
