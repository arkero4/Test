<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['capabilities' => 'array', 'environment' => 'array', 'last_heartbeat_at' => 'datetime'];
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'worker_project');
    }

    public function executions()
    {
        return $this->hasMany(Execution::class);
    }
}
