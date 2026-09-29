<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DevelopmentTask;
use App\Models\Requirement;
use App\Models\Worker;
use App\Services\Lifecycle;
use App\Services\SensitiveText;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function create(Requirement $requirement)
    {
        return view('tasks.form', ['requirement' => $requirement->load('plans', 'tasks'), 'workers' => Worker::orderBy('name')->get()]);
    }

    public function store(Request $request, Requirement $requirement, Lifecycle $lifecycle)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['technical_analysis', 'implementation', 'testing', 'review'])], 'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'instructions' => ['nullable', 'string'], 'plan_id' => ['nullable', 'exists:plans,id'],
            'parent_task_id' => ['nullable', 'exists:tasks,id'], 'required_worker_id' => ['nullable', 'exists:workers,id'],
            'priority' => ['required', Rule::in(['LOW', 'NORMAL', 'HIGH', 'URGENT'])], 'parallel_allowed' => ['boolean'], 'requires_approval' => ['boolean'],
            'dependency_ids' => ['array'], 'dependency_ids.*' => ['integer', 'exists:tasks,id'],
        ]);
        if (! $requirement->project_id) {
            throw ValidationException::withMessages(['project_id' => 'Asigna proyecto primero.']);
        }
        if ($data['type'] === 'technical_analysis' ? $requirement->status !== 'TECHNICAL_ANALYSIS' : ! in_array($requirement->status, ['PLANNING', 'QUEUED', 'IMPLEMENTING', 'TESTING', 'REVIEWING'], true)) {
            abort(409);
        }
        foreach (['plan_id' => 'plans', 'parent_task_id' => 'tasks'] as $key => $relation) {
            if (! empty($data[$key]) && ! $requirement->$relation()->whereKey($data[$key])->exists()) {
                abort(422);
            }
        }
        foreach ($data['dependency_ids'] ?? [] as $id) {
            if (! $requirement->tasks()->whereKey($id)->exists()) {
                abort(422);
            }
        }
        $dependencies = $data['dependency_ids'] ?? [];
        unset($data['dependency_ids']);
        $task = $requirement->tasks()->create(SensitiveText::cleanArray($data) + ['project_id' => $requirement->project_id, 'required_agent' => 'codex_local', 'status' => 'DRAFT']);
        $task->dependencies()->sync($dependencies);
        $lifecycle->event($requirement, 'task.created', 'user', to: 'DRAFT', task: $task, userId: $request->user()->id);

        return redirect()->route('requirements.show', $requirement)->with('ok', 'Tarea creada.');
    }

    public function queue(Request $request, DevelopmentTask $task, Lifecycle $lifecycle)
    {
        $lifecycle->queue($task, $request->user()->id);

        return back()->with('ok', 'Tarea en cola.');
    }

    public function retry(Request $request, DevelopmentTask $task, Lifecycle $lifecycle)
    {
        $lifecycle->queue($task, $request->user()->id);

        return back()->with('ok', 'Tarea reintentada.');
    }
}
