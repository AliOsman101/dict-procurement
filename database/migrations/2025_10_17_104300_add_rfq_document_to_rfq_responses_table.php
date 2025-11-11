<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_responses', function (Blueprint $table) {
            $table->string('rfq_document')->nullable()->after('documents');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_responses', function (Blueprint $table) {
            $table->dropColumn('rfq_document');
        });
    }
};