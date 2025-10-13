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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('codigo_producto', 50);
            $table->string('descripcion', 160);
            $table->string('codigo_cabys', 13);
            $table->string('unidad_medida', 20);
            $table->decimal('impuesto_porcentaje', 5, 2); // porcentaje (ej 13.00)
            $table->decimal('cantidad', 15, 3);
            $table->decimal('precio_unitario', 15, 5);
            $table->decimal('descuento_pct', 6, 3)->default(0); // porcentaje de descuento aplicado
            $table->decimal('subtotal_linea', 18, 5); // antes de impuesto (ya después de descuento)
            $table->decimal('impuesto_monto', 18, 5); // monto de impuesto calculado
            $table->decimal('total_linea', 18, 5); // subtotal_linea + impuesto_monto
            $table->timestamps();

            $table->index(['invoice_id']);
            $table->index(['codigo_producto']);
            $table->index(['codigo_cabys']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
