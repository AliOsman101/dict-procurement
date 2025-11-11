<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('default_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedInteger('sequence')->nullable(); // Made nullable
            $table->enum('module', [
                'ppmp',
                'purchase_request',
                'request_for_quotation',
                'abstract_of_quotation',
                'bac_resolution_recommending_award',
                'purchase_order'
            ]);
            $table->string('designation')->nullable();
            $table->enum('office_section', [
                'DICT CAR - Admin and Finance Division',
                'DICT CAR - Technical Operations Division'
            ])->nullable(); // Added office_section
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('default_approvers');
    }
};