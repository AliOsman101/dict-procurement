<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->date('philgeps_expiry_date')->nullable()->after('philgeps_reg_no');
            $table->string('mayors_permit')->nullable();
            $table->string('philgeps_certificate')->nullable();
            $table->string('omnibus_sworn_statement')->nullable();
            $table->string('pcab_license')->nullable();
            $table->string('professional_license_cv')->nullable();
            $table->string('terms_conditions_tech_specs')->nullable();
            $table->string('tax_return')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'philgeps_expiry_date',
                'mayors_permit',
                'philgeps_certificate',
                'omnibus_sworn_statement',
                'pcab_license',
                'professional_license_cv',
                'terms_conditions_tech_specs',
                'tax_return',
            ]);
        });
    }
};