<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequirementEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }
}
