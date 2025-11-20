<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->string('place_of_delivery')->nullable()->after('delivery_value');
            $table->date('date_of_delivery')->nullable()->after('place_of_delivery');
            $table->text('payment_term')->nullable()->after('date_of_delivery');
            $table->string('ors_burs_no')->nullable()->after('payment_term');
            $table->date('ors_burs_date')->nullable()->after('ors_burs_no');
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropColumn([
                'place_of_delivery',
                'date_of_delivery',
                'payment_term',
                'ors_burs_no',
                'ors_burs_date',
            ]);
        });
    }
};