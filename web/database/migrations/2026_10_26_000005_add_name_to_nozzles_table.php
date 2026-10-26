<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The name of a nozzle (set on the Smart210, Machine Config, and reported with the nozzle): it is what the lists show. A nozzle that has none yet
// is shown as "Nozzle <number>".
class AddNameToNozzlesTable extends Migration
{
    public function up()
    {
        Schema::table('nozzles', function (Blueprint $table) {
            $table->string('name', 50)->nullable()->after('number');
        });
    }

    public function down()
    {
        Schema::table('nozzles', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
}
