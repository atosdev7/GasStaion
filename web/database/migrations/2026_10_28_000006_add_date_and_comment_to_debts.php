<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// An opening debt (no sale) has the day it counts from (debt_date: the "as of" day of the customer's old ledger, used by the statements and the aging) and a comment. A debt
// made by a sale has neither: its day is the sale's.
class AddDateAndCommentToDebts extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE debts ADD debt_date DATE NULL AFTER payment_method, ADD comment VARCHAR(255) NULL AFTER debt_date');
    }

    public function down()
    {
        DB::statement('ALTER TABLE debts DROP COLUMN comment, DROP COLUMN debt_date');
    }
}
