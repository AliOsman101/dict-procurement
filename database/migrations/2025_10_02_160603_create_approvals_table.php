<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->onDelete('cascade');
            $table->enum('module', [
                'ppmp',
                'purchase_request',
                'request_for_quotation',
                'abstract_of_quotation',
                'bac_resolution_recommending_award',
                'purchase_order'
            ])->nullable();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->integer('sequence')->nullable();
            $table->string('designation')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('date_approved')->nullable();
            $table->timestamps();
            $table->unique(['procurement_id', 'employee_id', 'module'], 'approvals_procurement_id_employee_id_module_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};