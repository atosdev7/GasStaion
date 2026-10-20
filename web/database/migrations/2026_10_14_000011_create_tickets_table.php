<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// One ticket. The barcode (16 digits: sign, code, serial ...) is the key. status: 0 stock, 1 sold, 2 supplied, 3 restricted (see config/ticket_statuses).
class CreateTicketsTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE tickets (
            barcode CHAR(16) NOT NULL PRIMARY KEY,
            fuel_type VARCHAR(4) NOT NULL,
            spec TINYINT UNSIGNED NULL,
            code CHAR(2) NOT NULL,
            serial INT UNSIGNED NOT NULL,
            amount_kg DECIMAL(12,2) NULL,
            publish_id BIGINT UNSIGNED NULL,
            publish_date TIMESTAMP NULL,
            sale_id CHAR(36) NULL,
            sale_date TIMESTAMP NULL,
            supplement_id CHAR(36) NULL,
            supply_date TIMESTAMP NULL,
            status TINYINT UNSIGNED NOT NULL DEFAULT 0,
            synced TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            KEY tickets_status_fuel_type_index (status, fuel_type),
            KEY tickets_synced_barcode_index (synced, barcode),
            KEY tickets_sale_id_index (sale_id),
            KEY tickets_supplement_id_index (supplement_id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('tickets');
    }
}
