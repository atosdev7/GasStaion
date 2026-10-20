<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The rest of a fill that gave less than its tickets covered: a new ticket (ticket_barcode) for the customer. complete_date is the day that ticket was supplied.
class CreateRemaindersTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE remainders (
            id CHAR(36) NOT NULL PRIMARY KEY,
            supplement_id CHAR(36) NOT NULL,
            ticket_barcode CHAR(16) NOT NULL,
            fuel_type VARCHAR(4) NOT NULL,
            amount_kg DECIMAL(12,2) NOT NULL,
            remainder_date DATE NULL,
            complete_date DATE NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            KEY remainders_supplement_id_index (supplement_id),
            KEY remainders_ticket_barcode_index (ticket_barcode)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('remainders');
    }
}
