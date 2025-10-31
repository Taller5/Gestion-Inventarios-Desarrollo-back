<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['porcentaje', 'fijo', 'combo'])->default('porcentaje');
            $table->decimal('valor', 10, 2)->nullable(); // null para combos
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->boolean('activo')->default(true);

            // IDs de negocio y sucursal
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();

            // Foreign keys según tus modelos
            $table->foreign('business_id')->references('negocio_id')->on('businesses')->onDelete('set null');
            $table->foreign('branch_id')->references('sucursal_id')->on('branches')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
