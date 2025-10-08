<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cabys', function (Blueprint $table) {
            $table->string('code', 13)->primary();
            $table->text('description');
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->char('category_main', 1)->nullable(); // Primer dígito del código
            $table->text('category_main_name')->nullable(); // Nombre de la categoría principal
            $table->text('category_2')->nullable();
            $table->text('category_3')->nullable();
            $table->text('category_4')->nullable();
            $table->text('category_5')->nullable();
            $table->text('category_6')->nullable();
            $table->text('category_7')->nullable();
            $table->text('category_8')->nullable();
            $table->text('note_include')->nullable();
            $table->text('note_exclude')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabys');
    }
};
