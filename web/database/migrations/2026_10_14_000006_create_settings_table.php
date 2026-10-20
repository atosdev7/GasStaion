<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE settings (
            `key` VARCHAR(191) NOT NULL PRIMARY KEY,
            `value` TEXT NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('settings');
    }
}
