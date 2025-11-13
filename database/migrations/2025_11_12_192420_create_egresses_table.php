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
    Schema::create('egresses', function (Blueprint $table) {
        $table->id();
        $table->string('codigo_producto');
        $table->integer('cantidad');
        $table->string('motivo'); // 'devolución', 'vencimiento', 'traslado', 'otros'
        $table->text('descripcion')->nullable();
        $table->unsignedBigInteger('bodega_origen_id')->nullable();
        $table->unsignedBigInteger('bodega_destino_id')->nullable(); // si aplica
        $table->date('fecha')->default(now());
        $table->timestamps();

        $table->foreign('codigo_producto')->references('codigo_producto')->on('products');
        $table->foreign('bodega_origen_id')->references('bodega_id')->on('warehouses');
        $table->foreign('bodega_destino_id')->references('bodega_id')->on('warehouses');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('egresses');
    }
};
