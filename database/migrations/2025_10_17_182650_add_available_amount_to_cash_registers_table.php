<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('cash_registers', function (Blueprint $table) {
        $table->decimal('available_amount', 12, 2)->nullable()->after('opening_amount');
    });
}

public function down()
{
    Schema::table('cash_registers', function (Blueprint $table) {
        $table->dropColumn('available_amount');
    });
}

};
