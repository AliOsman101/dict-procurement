<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
    {
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->renameColumn('date_approved', 'action_at');
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->renameColumn('action_at', 'date_approved');
        });
    }
};
