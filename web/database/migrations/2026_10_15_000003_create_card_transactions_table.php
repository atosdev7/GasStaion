<?php

use App\Support\Cards\CardLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The ledger of the cards: an entry for every top-up and every fill, with the running balance after it (see App\Support\Cards\CardLedger). Entries made
// from the records that exist already (top-ups, fills, and the reversal of the deleted ones) come with the table. The Smart210 pulls the table.
class CreateCardTransactionsTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE card_transactions (
            id CHAR(36) NOT NULL PRIMARY KEY,
            card_id CHAR(36) NOT NULL,
            type VARCHAR(10) NOT NULL,
            kg DECIMAL(12,2) NOT NULL,
            balance_kg DECIMAL(12,2) NULL,
            price DECIMAL(12,2) NULL,
            total_amount DECIMAL(14,2) NULL,
            source_id CHAR(36) NOT NULL,
            occurred_at TIMESTAMP NULL,
            user_id BIGINT UNSIGNED NULL,
            comment VARCHAR(255) NULL,
            synced TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            UNIQUE KEY card_transactions_source_unique (type, source_id),
            KEY card_transactions_card_index (card_id, occurred_at)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        CardLedger::rebuild();
    }

    public function down()
    {
        Schema::dropIfExists('card_transactions');
    }
}
