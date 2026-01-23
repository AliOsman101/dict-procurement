<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE approvals MODIFY COLUMN module VARCHAR(255) NULL");

        // Then recreate it with the new enum values including 'minutes_of_opening'
        Schema::table('approvals', function (Blueprint $table) {
            $table->enum('module', [
                'ppmp',
                'purchase_request',
                'request_for_quotation',
                'abstract_of_quotation',
                'minutes_of_opening',
                'bac_resolution_recommending_award',
                'purchase_order'
            ])->nullable()->change();
        });

        // Restore unique constraint if needed
        try {
            Schema::table('approvals', function (Blueprint $table) {
                $table->unique(['procurement_id', 'employee_id', 'module'], 'approvals_procurement_id_employee_id_module_unique');
            });
        } catch (\Exception $e) {
            // Ignore if already exists
        }
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            DB::statement("ALTER TABLE approvals MODIFY COLUMN module VARCHAR(255) NULL");

            $table->enum('module', [
                'ppmp',
                'purchase_request',
                'request_for_quotation',
                'abstract_of_quotation',
                'bac_resolution_recommending_award',
                'purchase_order'
            ])->nullable()->change();
        });
    }
};