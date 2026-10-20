<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// One supply event at a nozzle (a fill). The tickets it used point to it (tickets.supplement_id); preset_kg is what they covered, out_amount_kg what was
// dispensed, density is kg per litre. mode: auto (the system sets the preset and starts the fill) or manual (the supplier types the amount at the
// dispenser). A fill paid with a card names it in card_id and has no tickets.
class CreateSupplementsTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE supplements (
            id CHAR(36) NOT NULL PRIMARY KEY,
            fuel_type VARCHAR(4) NOT NULL,
            nozzle_id BIGINT UNSIGNED NOT NULL,
            device_id BIGINT UNSIGNED NULL,
            card_id CHAR(36) NULL,
            supply_date DATE NULL,
            supplied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            mode VARCHAR(10) NOT NULL DEFAULT \'auto\',
            preset_kg DECIMAL(12,2) NOT NULL,
            density DECIMAL(8,4) NULL,
            out_amount_lt DECIMAL(12,2) NOT NULL,
            out_amount_kg DECIMAL(12,2) NOT NULL,
            comment VARCHAR(255) NULL,
            confirmed_at TIMESTAMP NULL,
            confirmed_by BIGINT UNSIGNED NULL,
            synced TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
            KEY supplements_nozzle_id_index (nozzle_id),
            KEY supplements_card_id_index (card_id),
            KEY supplements_supply_date_confirmed_at_index (supply_date, confirmed_at)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('supplements');
    }
}
