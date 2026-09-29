<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestionClient extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }
}
