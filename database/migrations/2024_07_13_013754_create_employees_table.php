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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('firstname', 50);
            $table->string('middlename', 50)->nullable();
            $table->string('lastname', 50);

            $table->integer('employee_no')->unique()->nullable();
            // $table->string('dtr_no')->unique()->nullable();
            $table->enum('civil_status', ['single', 'married', 'widowed', 'divorced'])->default('single');
            $table->enum('employment_status', ['jo', 'plantilla', 'probationary'])->default('plantilla');

            $table->enum('gender', ['male', 'female'])->default('male');

            $table->string('designation')->nullable();

            $table->integer('division_id')->unsigned()->nullable();
            $table->foreign('division_id')->references('id')->on('divisions');

            $table->integer('position_id')->unsigned()->nullable();
            $table->foreign('position_id')->references('id')->on('positions');

            $table->integer('project_id')->unsigned()->nullable();
            $table->foreign('project_id')->references('id')->on('projects');

            $table->date('birthday')->nullable();

            $table->string('mobile')->nullable();
            $table->string('gsis_no')->nullable();
            $table->string('tin')->nullable();
            $table->string('supervisor')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable(); // Change to unsignedBigInteger
            $table->foreign('supervisor_id')->references('id')->on('employees');                       
            $table->boolean('is_active')->default(true);
            $table->date('entrance_to_duty')->nullable();
            $table->enum('region', ['CAR', 'Region 1', 'Region 2'])->nullable();
            $table->enum('office', ['Region Office', 'Abra', 'Apayao', 'Benguet', 'Ifugao', 'Kalinga', 'Mt. Province'])->nullable();
            $table->string('photo', 55)->default('/uploads/profilepicture/profile.png')->nullable();
            //user
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade'); // Cascade deletion
            

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};