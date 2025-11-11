<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aoq_tie_breaking_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->onDelete('cascade');
            $table->string('aoq_number');
            $table->enum('method', ['coin_toss', 'random_draw']);
            $table->decimal('tied_amount', 15, 2);
            $table->integer('tied_suppliers_count');
            $table->json('tied_suppliers_data'); // Stores all tied supplier details
            $table->foreignId('winner_rfq_response_id')->constrained('rfq_responses')->onDelete('cascade');
            $table->string('winner_supplier_name');
            $table->string('seed_used'); // For reproducibility audit
            $table->timestamp('performed_at');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['procurement_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aoq_tie_breaking_records');
    }
};