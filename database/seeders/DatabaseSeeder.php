<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Project::firstOrCreate(
            ['slug' => 'dev-orchestrator'],
            ['name' => 'Dev Orchestrator', 'type' => 'orchestrator', 'stack' => 'Laravel 13', 'status' => 'ACTIVE', 'description' => 'Centro de operaciones de desarrollo'],
        );
    }
}
