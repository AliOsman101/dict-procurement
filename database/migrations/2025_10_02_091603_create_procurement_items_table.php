<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->onDelete('cascade');
            $table->string('unit');
            $table->string('item_description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->integer('sort')->default(1);
            $table->timestamps();
        });

        DB::table('procurement_items')
            ->whereNull('sort')
            ->update(['sort' => DB::raw('id')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_items');
    }
};