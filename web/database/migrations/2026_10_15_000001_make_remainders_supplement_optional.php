<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A remainder may have no supplement: an opening remainder entered at go-live (Initialize) has none, and a backup clears old supplements while
// the remainders stay (they are balances). supplement_id is NULL for an opening remainder.
class MakeRemaindersSupplementOptional extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE remainders MODIFY supplement_id CHAR(36) NULL');
    }

    public function down()
    {
        DB::statement('DELETE FROM remainders WHERE supplement_id IS NULL');
        DB::statement('ALTER TABLE remainders MODIFY supplement_id CHAR(36) NOT NULL');
    }
}
