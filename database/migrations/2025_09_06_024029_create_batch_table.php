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
        Schema::create('batch', function (Blueprint $table) {
            $table->id('lote_id');
            $table->string('codigo'); // Relaciona con productos.codigo
            $table->string('numero_lote');
            $table->unsignedInteger('cantidad');
            $table->string('proveedor');
            $table->date('fecha_entrada');
            $table->date('fecha_salida');
            $table->date('fecha_salida_lote')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('nombre');
            $table->timestamps();

            $table->foreign('codigo')->references('codigo')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    Schema::dropIfExists('batch');
    }
};
