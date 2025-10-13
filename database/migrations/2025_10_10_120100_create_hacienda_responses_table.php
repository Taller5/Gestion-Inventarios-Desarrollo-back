<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hacienda_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_xml_id')->nullable()->constrained('invoice_xmls')->nullOnDelete();
            $table->string('clave', 60)->nullable()->index();
            $table->string('estado', 30)->nullable()->index(); // recibido|procesando|aceptado|rechazado|error
            $table->string('numero_consecutivo', 20)->nullable();
            $table->string('ind_ambiente', 10)->nullable();
            $table->timestamp('fecha_recepcion')->nullable();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->longText('respuesta_xml')->nullable(); // XML de respuesta (acuse o rechazo)
            $table->json('detalle')->nullable(); // JSON parseado de la respuesta
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hacienda_responses');
    }
};
