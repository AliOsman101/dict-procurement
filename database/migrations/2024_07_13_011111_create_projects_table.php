<?php

use App\Models\Setup\Project;
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
        Schema::create('projects', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();

            $table->timestamps();
        });

        $json = file_get_contents(database_path().'/projects.json');
        $rows = json_decode($json, true);

        foreach ($rows as $row) {
           Project::firstOrCreate($row);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
