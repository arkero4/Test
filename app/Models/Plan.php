<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $guarded = ['id'];

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }

    public function tasks()
    {
        return $this->hasMany(DevelopmentTask::class);
    }
}
