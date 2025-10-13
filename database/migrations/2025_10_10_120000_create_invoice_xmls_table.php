<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_xmls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('clave', 60)->nullable()->index();
            $table->string('document_type', 2)->default('04')->index();
            $table->string('schema_version', 10)->default('4.4');
            $table->longText('xml'); // signed XML content
            $table->boolean('schema_valid')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->text('validation_errors')->nullable();
            $table->string('status', 20)->default('generated')->index(); // generated|submitted|accepted|rejected|error|processing
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_xmls');
    }
};
