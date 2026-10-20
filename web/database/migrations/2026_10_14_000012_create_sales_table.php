<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A sale made on the terminal or entered on the web. sale_type: spot, ticket or card (a top-up of a card: card_id). `usage` is sale, self, other or
// ticket_loan. Money is paid_amount + unpaid_amount = total_amount; a sale that reverses another names it in reverses_sale_id.
class CreateSalesTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE sales (
            id CHAR(36) NOT NULL PRIMARY KEY,
            customer_id BIGINT UNSIGNED NULL,
            card_id CHAR(36) NULL,
            sale_type VARCHAR(10) NOT NULL DEFAULT \'ticket\',
            fuel_type VARCHAR(4) NOT NULL,
            cost DECIMAL(14,2) NULL,
            price DECIMAL(12,2) NOT NULL,
            amount_kg DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(10) NOT NULL DEFAULT \'cash\',
            total_amount DECIMAL(14,2) NOT NULL,
            paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            unpaid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            receipt TINYINT(1) NOT NULL DEFAULT 0,
            `usage` VARCHAR(12) NOT NULL,
            comment VARCHAR(255) NULL,
            reverses_sale_id CHAR(36) NULL,
            sale_date DATE NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            confirmed_at TIMESTAMP NULL,
            confirmed_by BIGINT UNSIGNED NULL,
            synced TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
            KEY sales_customer_id_index (customer_id),
            KEY sales_card_id_index (card_id),
            KEY sales_reverses_sale_id_index (reverses_sale_id),
            KEY sales_sale_date_confirmed_at_index (sale_date, confirmed_at)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
}
