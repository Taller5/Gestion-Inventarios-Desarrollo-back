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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id('negocio_id');
            $table->string('nombre_legal'); //persona física o persona jurídica 
            $table->string('nombre_comercial');
            $table->string('tipo_identificacion');
            $table->string('numero_identificacion');
            $table->char('codigo_actividad_emisor', 6);
            $table->decimal('margen_ganancia', 5, 2)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('telefono');
            $table->string('email');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
