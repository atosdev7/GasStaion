<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The fuel types are the key itself ('D' diesel, 'A' gasoline ...): the code is what every other table stores in its fuel_type column.
class CreateFuelTypesTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE fuel_types (
            fuel_type VARCHAR(4) NOT NULL PRIMARY KEY,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            density DECIMAL(6,4) NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('fuel_types');
    }
}
