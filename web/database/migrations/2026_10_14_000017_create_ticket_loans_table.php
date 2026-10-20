<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tickets lent to a customer, to be given back later. settled_amount_kg is the sum of its settlements.
class CreateTicketLoansTable extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE ticket_loans (
            id CHAR(36) NOT NULL PRIMARY KEY,
            loaned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date` DATE NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            fuel_type VARCHAR(4) NOT NULL,
            amount_kg DECIMAL(12,2) NOT NULL,
            settled_amount_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
            sale_id CHAR(36) NULL,
            comment VARCHAR(255) NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            synced TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
            KEY ticket_loans_customer_id_index (customer_id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down()
    {
        Schema::dropIfExists('ticket_loans');
    }
}
