<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The press tickets that customers hold at go-live and have not had supplied yet (sold on paper, so the system has no rows for them): how many there are
// for a fuel type and a spec, counted from the "as of" day. Only used to work out what is owed (see OpeningTickets).
class CreateOpeningTicketsTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE opening_tickets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            fuel_type CHAR(1) NOT NULL,
            spec TINYINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            as_of DATE NOT NULL,
            comment VARCHAR(255) NULL,
            user_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        DB::statement('DROP TABLE opening_tickets');
    }
}
