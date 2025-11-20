<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->string('submitted_by')->nullable();
            $table->date('submitted_date')->nullable();
            $table->string('designation')->nullable();
            $table->json('documents')->nullable();
            $table->string('business_name')->nullable();
            $table->text('business_address')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('email_address')->nullable();
            $table->string('tin')->nullable();
            $table->boolean('vat')->nullable();
            $table->boolean('nvat')->nullable();
            $table->string('philgeps_reg_no')->nullable();
            $table->string('lbp_account_name')->nullable();
            $table->string('lbp_account_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_responses');
    }
};