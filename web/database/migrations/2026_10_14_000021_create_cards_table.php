<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// RFID fuel cards. The card only carries its id (`uid`); what it holds is worked out from the records: the kg topped up (sales of type `card`) less
// the kg supplied with it (supplements with its card_id). One fuel type per card. The customer is only a name, not a row of `customers`. The PIN is
// kept as a salted SHA-256 (the Smart210 checks it); a card is locked after 5 wrong PINs and blocked when it is lost.
class CreateCardsTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE cards (
            id CHAR(36) NOT NULL PRIMARY KEY,
            uid VARCHAR(32) NOT NULL,
            holder_name VARCHAR(100) NOT NULL,
            fuel_type VARCHAR(4) NOT NULL,
            pin_salt VARCHAR(32) NULL,
            pin_hash VARCHAR(64) NULL,
            failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(10) NOT NULL DEFAULT \'active\',
            device_id BIGINT UNSIGNED NULL,
            synced TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
            UNIQUE KEY cards_uid_unique (uid)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('cards');
    }
}
