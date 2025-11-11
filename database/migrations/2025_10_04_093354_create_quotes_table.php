<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_response_id')->constrained()->onDelete('cascade');
            $table->foreignId('procurement_item_id')->constrained('procurement_items')->onDelete('cascade');
            $table->boolean('statement_of_compliance')->default(true);
            $table->text('specifications')->nullable();
            $table->decimal('unit_value', 12, 2)->nullable();
            $table->decimal('total_value', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};