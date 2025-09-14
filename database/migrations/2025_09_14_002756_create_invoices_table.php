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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            
            // Customer info
            $table->string('customer_name');
            $table->string('customer_identity_number');
      

            // Branch / Business info
            $table->string('branch_name');
           
            $table->string('business_name');
            $table->string('business_legal_name');
            $table->string('business_phone')->nullable();
            $table->string('business_email')->nullable();
             $table->string('branches_phone')->nullable();
            $table->string('province')->nullable();
            $table->string('canton')->nullable();
            $table->string('business_id_type')->nullable(); // e.g., "Cédula Jurídica"
            $table->string('business_id_number')->nullable();

            // User / Cashier info
            $table->string('cashier_name');

            // Invoice details
            $table->dateTime('date');
            $table->json('products'); // store products as JSON [{code, name, quantity, unit_price, discount_pct, subtotal}]
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_discount', 12, 2);
            $table->decimal('taxes', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->decimal('change', 12, 2)->nullable();

            // Payment info
            $table->string('payment_method'); // Cash, Card, SINPE
            $table->string('receipt')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
