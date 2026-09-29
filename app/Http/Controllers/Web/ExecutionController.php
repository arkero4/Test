<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Execution;
use Illuminate\View\View;

class ExecutionController extends Controller
{
    public function show(Execution $execution): View
    {
        return view('executions.show', [
            'execution' => $execution->load('task.requirement', 'worker', 'logs', 'agentRuns'),
        ]);
    }
}
