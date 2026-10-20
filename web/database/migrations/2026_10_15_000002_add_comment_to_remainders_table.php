<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A remainder can have a comment (typed when it is made by hand or entered as an opening remainder).
class AddCommentToRemaindersTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE remainders ADD comment VARCHAR(255) NULL AFTER amount_kg');
    }

    public function down()
    {
        DB::statement('ALTER TABLE remainders DROP COLUMN comment');
    }
}
