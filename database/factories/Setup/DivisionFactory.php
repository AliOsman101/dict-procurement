<?php

namespace Database\Factories\Setup;

use App\Models\Setup\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->company(), // Generates a unique company name
        ];
    }
}
