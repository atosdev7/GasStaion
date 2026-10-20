<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateDebtsTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE debts (
            id CHAR(36) NOT NULL PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            sale_id CHAR(36) NULL,
            amount DECIMAL(14,2) NOT NULL,
            payment_method VARCHAR(10) NOT NULL DEFAULT \'cash\',
            user_id BIGINT UNSIGNED NOT NULL,
            synced TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
            KEY debts_customer_id_index (customer_id),
            KEY debts_sale_id_index (sale_id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('debts');
    }
}
