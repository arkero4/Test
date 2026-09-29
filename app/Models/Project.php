<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['test_commands' => 'array'];
    }

    public function contexts()
    {
        return $this->hasMany(ProjectContext::class);
    }

    public function workers()
    {
        return $this->belongsToMany(Worker::class, 'worker_project');
    }

    public function preferredWorker()
    {
        return $this->belongsTo(Worker::class, 'preferred_worker_id');
    }
}
