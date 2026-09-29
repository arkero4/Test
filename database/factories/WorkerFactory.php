<?php

namespace Database\Factories;

use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkerFactory extends Factory
{
    protected $model = Worker::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'name' => fake()->name(), 'status' => 'ONLINE', 'last_heartbeat_at' => now(), 'token_hash' => hash('sha256', 'test-worker-token-'.fake()->unique()->uuid())];
    }
}
