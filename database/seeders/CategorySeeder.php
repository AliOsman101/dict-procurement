<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('categories')->insert([
            ['name' => 'Catering Services'],
            ['name' => 'Event Management and Services'],
            ['name' => 'Transportation and Logistics'],
            ['name' => 'General Supplies and Office Equipment'],
            ['name' => 'Consulting and Professional Services'],
            ['name' => 'Healthcare and Medical Services'],
            ['name' => 'Utilities and Energy'],
            ['name' => 'Security and Safety Services'],
            ['name' => 'Marketing and Public Relations'],
            ['name' => 'Educational and Training Services'],
            ['name' => 'Tourism and Hospitality Services'],
            ['name' => 'Green and Sustainable Initiatives'],
            ['name' => 'Community Outreach and Social Responsibility'],
            ['name' => 'Consulting Services'],
            ['name' => 'Printing Services'],
            ['name' => 'Infrastructure Projects'],
        ]);
    }
}