<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }
}
