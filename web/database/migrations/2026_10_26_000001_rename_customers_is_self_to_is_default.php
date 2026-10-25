<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The built-in customer is no longer "Self" (own use only): it is "Individual", the customer of own use, other use and of the buyers who pay cash and
// need no name. The flag says which customer it is.
class RenameCustomersIsSelfToIsDefault extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE customers CHANGE is_self is_default TINYINT(1) NOT NULL DEFAULT 0');
        DB::table('customers')->where('is_default', true)->where('name', 'Self')
            ->update(['name' => 'Individual', 'synced' => false, 'updated_at' => now()]);
    }

    public function down()
    {
        DB::statement('ALTER TABLE customers CHANGE is_default is_self TINYINT(1) NOT NULL DEFAULT 0');
    }
}
