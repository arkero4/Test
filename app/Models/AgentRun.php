<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'usage' => 'array'];
    }

    public function execution()
    {
        return $this->belongsTo(Execution::class);
    }
}
