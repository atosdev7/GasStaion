<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A ticket made for a card (the cashier asks for fuel with the card on the Smart210: the card pays with its kg and a ticket is made, printed and scanned by
// the supplier like the ticket of a spot sale). It is already paid for and has no sale: tickets.card_id says which card paid. The ledger of the card
// follows the ticket (see App\Support\Cards\CardLedger).
class AddCardIdToTicketsTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE tickets ADD COLUMN card_id CHAR(36) NULL AFTER supply_date');
        DB::statement('ALTER TABLE tickets ADD KEY tickets_card_id_index (card_id)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE tickets DROP KEY tickets_card_id_index');
        DB::statement('ALTER TABLE tickets DROP COLUMN card_id');
    }
}
