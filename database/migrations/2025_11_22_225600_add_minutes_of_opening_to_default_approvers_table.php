<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('default_approvers', function (Blueprint $table) {
            // Change the enum column to include the new module
            $table->enum('module', [
                'purchase_request',
                'request_for_quotation',
                'abstract_of_quotation',
                'minutes_of_opening',                    // ← NEW: Added here
                'bac_resolution_recommending_award',
                'purchase_order'
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('default_approvers', function (Blueprint $table) {
            // First, delete any records that use minutes_of_opening (safe rollback)
            \DB::table('default_approvers')
                ->where('module', 'minutes_of_opening')
                ->delete();

            // Then revert the enum (remove minutes_of_opening)
            $table->enum('module', [
                'purchase_request',
                'request_for_quotation',
                'abstract_of_quotation',
                'bac_resolution_recommending_award',
                'purchase_order'
            ])->change();
        });
    }
};