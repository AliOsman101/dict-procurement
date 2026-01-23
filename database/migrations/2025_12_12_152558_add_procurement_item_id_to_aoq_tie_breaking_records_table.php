<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aoq_tie_breaking_records', function (Blueprint $table) {
            if (!Schema::hasColumn('aoq_tie_breaking_records', 'procurement_item_id')) {
                $table->unsignedBigInteger('procurement_item_id')->nullable()->after('procurement_id');
                $table->foreign('procurement_item_id')
                    ->references('id')
                    ->on('procurement_items')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('aoq_tie_breaking_records', function (Blueprint $table) {
            if (Schema::hasColumn('aoq_tie_breaking_records', 'procurement_item_id')) {
                $table->dropForeign(['procurement_item_id']);
                $table->dropColumn('procurement_item_id');
            }
        });
    }
};