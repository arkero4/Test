<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequirementFactory extends Factory
{
    protected $model = Requirement::class;

    public function definition(): array
    {
        return ['source' => 'manual', 'subject' => fake()->sentence(), 'original_content' => fake()->paragraph(), 'project_id' => Project::factory(), 'status' => 'RECEIVED', 'priority' => 'NORMAL', 'risk' => 'UNKNOWN'];
    }
}
