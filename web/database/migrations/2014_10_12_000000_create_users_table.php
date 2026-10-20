<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Users of the web app. Those who work at the terminal also have their terminal password as a salted SHA-256 (pos_salt, pos_hash), so they can log in offline.
class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role', 30); // one of the five roles
            $table->string('pos_salt', 32)->nullable();
            $table->string('pos_hash', 64)->nullable();
            $table->boolean('active')->default(true); // the only model that still has an active/deactivate toggle
            $table->boolean('synced')->default(false);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
}
