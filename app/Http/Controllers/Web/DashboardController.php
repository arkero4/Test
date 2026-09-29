<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Execution;
use App\Models\Requirement;
use App\Models\RequirementEvent;
use App\Models\Worker;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $counts = Requirement::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('dashboard.index', [
            'counts' => $counts,
            'workers' => Worker::with(['executions' => fn ($query) => $query->where('status', 'RUNNING')->with('task.project')])->orderBy('name')->get(),
            'executions' => Execution::with('task.project', 'worker')->where('status', 'RUNNING')->latest()->get(),
            'events' => RequirementEvent::with('requirement')->latest()->limit(30)->get(),
            'recent' => Requirement::with('project')->latest()->limit(15)->get(),
        ]);
    }
}
