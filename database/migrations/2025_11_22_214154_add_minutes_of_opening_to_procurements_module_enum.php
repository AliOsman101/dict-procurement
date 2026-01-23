<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Turn off strict mode temporarily
        DB::statement('SET SESSION sql_mode = ""');

        DB::statement("ALTER TABLE procurements 
            MODIFY COLUMN module ENUM(
                'ppmp',
                'purchase_request',
                'request_for_quotation',
                'abstract_of_quotation',
                'minutes_of_opening',
                'bac_resolution_recommending_award',
                'purchase_order'
            ) NULL"
        );
    }

    public function down(): void
    {
        DB::statement('SET SESSION sql_mode = ""');

        DB::statement("ALTER TABLE procurements 
            MODIFY COLUMN module ENUM(
                'ppmp',
                'purchase_request',
                'request_for_quotation',
                'abstract_of_quotation',
                'bac_resolution_recommending_award',
                'purchase_order'
            ) NULL"
        );

        DB::table('procurements')
            ->where('module', 'minutes_of_opening')
            ->delete();
    }
};