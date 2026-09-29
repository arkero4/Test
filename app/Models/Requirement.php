<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['technical_analysis' => 'array', 'requires_approval' => 'boolean', 'received_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public function tasks()
    {
        return $this->hasMany(DevelopmentTask::class);
    }

    public function events()
    {
        return $this->hasMany(RequirementEvent::class);
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }
}
