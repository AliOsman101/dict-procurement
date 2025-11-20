<?php

namespace Database\Factories\Setup;

use App\Models\Setup\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->jobTitle(), // Generates a unique job title
        ];
    }
}
