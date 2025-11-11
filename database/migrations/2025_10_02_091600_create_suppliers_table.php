<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('business_name')->nullable();
            $table->text('business_address')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('email_address')->nullable();
            $table->string('tin')->nullable();
            $table->boolean('vat')->default(false);
            $table->boolean('nvat')->default(false);
            $table->string('philgeps_reg_no')->nullable();
            $table->string('lbp_account_name')->nullable();
            $table->string('lbp_account_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};