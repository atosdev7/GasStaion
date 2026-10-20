<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The order the entries of a card's ledger were made in: two entries of the same second (a top-up on the web and the deletion of another one) are
// put in the order they happened by `seq`, which only grows. The running balance and the History lists follow occurred_at, then seq.
class AddSeqToCardTransactionsTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE card_transactions ADD seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD UNIQUE KEY card_transactions_seq_unique (seq)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE card_transactions DROP COLUMN seq');
    }
}
