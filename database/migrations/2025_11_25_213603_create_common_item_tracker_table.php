<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('common_item_tracker', function (Blueprint $table) {
    $table->id();
    $table->string('item_description');     // name of the item
    $table->string('unit')->nullable();     // pc, box, pack, etc.
    $table->decimal('unit_cost', 10, 2)->nullable(); // last used cost
    $table->integer('count')->default(1);   // how many times this item was used
    $table->unsignedBigInteger('category_id')->nullable(); // category of PR when added
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('common_item_tracker');
    }
};
