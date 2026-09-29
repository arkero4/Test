<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DevelopmentTask extends Model
{
    use HasFactory;

    protected $table = 'tasks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['parallel_allowed' => 'boolean', 'requires_approval' => 'boolean'];
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function dependencies()
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'depends_on_task_id');
    }

    public function executions()
    {
        return $this->hasMany(Execution::class, 'task_id');
    }
}
