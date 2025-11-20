<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aoq_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->onDelete('cascade');
            $table->foreignId('rfq_response_id')->constrained()->onDelete('cascade');
            $table->string('requirement'); // e.g., 'mayors_permit' or 'quote_1'
            $table->enum('status', ['pass', 'fail'])->default('pass');
            $table->text('remarks')->nullable();
            $table->boolean('lowest_bid')->default(false); // For per-item lowest bid
            $table->timestamps();
            
            // Add unique constraint to prevent duplicate evaluations
            $table->unique(['rfq_response_id', 'requirement'], 'unique_response_requirement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aoq_evaluations');
    }
};