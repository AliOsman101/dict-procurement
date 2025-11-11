<?php

namespace Database\Factories\Setup;

use App\Models\Setup\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->company(), // Generates a unique project name
        ];
    }
}
