<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            
            // Parent will have NULL, children will use these enums
            $table->enum('module', [
                'ppmp',
                'purchase_request',
                'request_for_quotation',
                'abstract_of_quotation',
                'bac_resolution_recommending_award',
                'purchase_order'
            ])->nullable();

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('procurement_type', ['small_value_procurement', 'public_bidding'])
                ->default('small_value_procurement');
            $table->string('procurement_id')->unique();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('fund_cluster_id');
            $table->enum('office_section', [
                'DICT CAR - Admin and Finance Division',
                'DICT CAR - Technical Operations Division'
            ])->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status')->default('Pending');
            $table->string('title')->nullable();
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->enum('delivery_mode', ['days', 'date'])->nullable();
            $table->string('delivery_value')->nullable();
            $table->dateTime('deadline_date')->nullable(); // Changed from date to dateTime
            $table->timestamps();

            // Relationships
            $table->foreign('parent_id')->references('id')->on('procurements')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('requested_by')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('fund_cluster_id')->references('id')->on('fund_clusters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};