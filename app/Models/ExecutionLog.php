<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExecutionLog extends Model
{
    protected $guarded = ['id'];

    public function execution()
    {
        return $this->belongsTo(Execution::class);
    }
}
