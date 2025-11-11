<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FundClusterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('fund_clusters')->insert([
            ['name' => 'Capital Outlay (CO)'],
            ['name' => 'Maintenance and Other Operating Expenses (MOOE)'],
            ['name' => 'Personnel Services (PS)'],
            ['name' => 'Research and Development (R&D)'],
            ['name' => 'External Assistance Fund (EAF)'],
            ['name' => 'Program and Project Funds'],
            ['name' => 'Contingency Fund'],
            ['name' => 'Disaster Risk Reduction and Management Fund (DRRM)'],
            ['name' => 'Infrastructure Development Fund'],
            ['name' => 'Public Health Fund'],
            ['name' => 'Special Purpose Funds'],
            ['name' => 'Training and Capacity Building Fund'],
            ['name' => 'Sustainability Fund'],
            ['name' => 'Public-Private Partnership (PPP) Fund'],
            ['name' => 'Educational Fund'],
            ['name' => 'Social Welfare Fund'],
        ]);
    }
}