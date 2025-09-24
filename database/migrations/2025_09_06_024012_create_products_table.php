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
         Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_producto')->unique();
            $table->string('nombre_producto');
            $table->string('categoria');
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('precio_compra', 10, 2);
            $table->decimal('precio_venta', 10, 2);
            $table->foreignId('bodega_id')->constrained('warehouses', 'bodega_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    Schema::dropIfExists('products');
    }
};
