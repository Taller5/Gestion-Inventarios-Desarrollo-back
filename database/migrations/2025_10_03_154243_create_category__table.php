<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Crear tabla category con nombre como PK
        Schema::create('category', function (Blueprint $table) {
            $table->string('nombre')->primary(); // clave primaria
            $table->text('descripcion')->nullable(); // descripción opcional
            $table->timestamps();
        });


    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_nombre']);
            $table->dropColumn('category_nombre');
        });

        Schema::dropIfExists('category');
    }
};
