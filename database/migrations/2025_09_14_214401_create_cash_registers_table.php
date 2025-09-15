<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->unsignedBigInteger('sucursal_id');
          
            $table->unsignedBigInteger('user_id'); // cashier or person in charge

            // Cash management
            $table->decimal('opening_amount', 10, 2);
            $table->decimal('closing_amount', 10, 2)->nullable();

            // Time tracking
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            // Foreign keys (con nombres correctos)
            $table->foreign('sucursal_id')
                  ->references('sucursal_id') // <- cambiar 'id' por 'sucursal_id'
                  ->on('branches')
                  ->onDelete('cascade');

       

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
