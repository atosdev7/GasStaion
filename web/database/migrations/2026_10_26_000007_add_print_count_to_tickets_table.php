<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// How many times a ticket was printed (every time the Smart210 sent it to the printer successfully: the first print and each Print again) and when it was last
// printed. The terminal reports both with the ticket; the web app keeps the larger count.
class AddPrintCountToTicketsTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE tickets ADD COLUMN print_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER card_id');
        DB::statement('ALTER TABLE tickets ADD COLUMN last_printed_at DATETIME NULL AFTER print_count');
    }

    public function down()
    {
        DB::statement('ALTER TABLE tickets DROP COLUMN last_printed_at');
        DB::statement('ALTER TABLE tickets DROP COLUMN print_count');
    }
}
