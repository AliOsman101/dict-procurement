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
        Schema::create('employee_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->longText('private_key')->nullable(); // Changed to longText
            $table->longText('certificate')->nullable(); // Changed to longText
            $table->longText('intermediate_certificates')->nullable(); // Changed to longText
            $table->longText('signature_image_path'); // Changed to longText - THIS IS THE FIX!
            $table->timestamps();

            // Foreign key
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_certificates');
    }
};