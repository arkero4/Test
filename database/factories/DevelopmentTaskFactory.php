<?php

namespace Database\Factories;

use App\Models\DevelopmentTask;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\Factory;

class DevelopmentTaskFactory extends Factory
{
    protected $model = DevelopmentTask::class;

    public function definition(): array
    {
        return ['requirement_id' => Requirement::factory(), 'project_id' => Project::factory(), 'type' => 'implementation', 'title' => fake()->sentence(), 'status' => 'DRAFT', 'required_agent' => 'codex_local'];
    }
}
