<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A ticket given back against a loan.
class CreateTicketLoanSettlementsTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE ticket_loan_settlements (
            id CHAR(36) NOT NULL PRIMARY KEY,
            loan_id CHAR(36) NOT NULL,
            ticket_barcode CHAR(16) NOT NULL,
            amount_kg DECIMAL(12,2) NOT NULL,
            settled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date` DATE NULL,
            comment VARCHAR(255) NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            synced TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
            KEY ticket_loan_settlements_loan_id_index (loan_id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('ticket_loan_settlements');
    }
}
