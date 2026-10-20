<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The holder of a card: a phone number next to the name, and a comment on the card.
class AddHolderPhoneAndCommentToCardsTable extends Migration
{
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('holder_phone', 30)->nullable()->after('holder_name');
            $table->string('comment', 255)->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['holder_phone', 'comment']);
        });
    }
}
