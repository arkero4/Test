<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Execution extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'modified_files' => 'array', 'tests' => 'array', 'result' => 'array'];
    }

    public function task()
    {
        return $this->belongsTo(DevelopmentTask::class, 'task_id');
    }

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }

    public function logs()
    {
        return $this->hasMany(ExecutionLog::class);
    }

    public function agentRuns()
    {
        return $this->hasMany(AgentRun::class);
    }
}
