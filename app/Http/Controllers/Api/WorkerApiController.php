<?php

namespace App\Http\Controllers\Api;

use App\Agents\CodexLocalAgent;
use App\Http\Controllers\Controller;
use App\Models\DevelopmentTask;
use App\Models\Execution;
use App\Models\Worker;
use App\Services\Lifecycle;
use App\Services\SensitiveText;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkerApiController extends Controller
{
    private function worker(Request $request): Worker
    {
        return $request->attributes->get('worker');
    }

    public function register(Request $request)
    {
        $data = $request->validate(['uuid' => ['required', 'uuid'], 'agent_version' => ['nullable', 'string', 'max:100'], 'environment' => ['nullable', 'array:platform,architecture,os_version'], 'environment.platform' => ['nullable', 'string', 'max:50'], 'environment.architecture' => ['nullable', 'string', 'max:50'], 'environment.os_version' => ['nullable', 'string', 'max:100']]);
        $worker = $this->worker($request);
        abort_unless($worker->uuid === $data['uuid'], 403);
        $worker->update(['agent_version' => $data['agent_version'] ?? null, 'environment' => $data['environment'] ?? null, 'last_heartbeat_at' => now(), 'status' => 'ONLINE']);

        return response()->json(['worker_uuid' => $worker->uuid, 'status' => $worker->status]);
    }

    public function heartbeat(Request $request)
    {
        $worker = $this->worker($request);
        $worker->update(['last_heartbeat_at' => now(), 'status' => $worker->executions()->where('status', 'RUNNING')->exists() ? 'BUSY' : 'ONLINE']);
        $cancelRequested = $worker->executions()->where('status', 'RUNNING')
            ->whereHas('task.requirement', fn ($query) => $query->where('status', 'CANCELLED'))->exists();

        return response()->json(['status' => $worker->status, 'server_time' => now()->toIso8601String(), 'cancel_requested' => $cancelRequested]);
    }

    public function next(Request $request, Lifecycle $lifecycle)
    {
        $worker = $this->worker($request);
        if (! $worker->last_heartbeat_at || $worker->last_heartbeat_at->lt(now()->subSeconds(config('orchestrator.heartbeat_timeout_seconds')))) {
            return response()->json(['message' => 'Heartbeat required.'], 409);
        }
        if ($worker->executions()->where('status', 'RUNNING')->exists()) {
            return response()->json(['job' => null]);
        }
        $projectIds = $worker->projects()->pluck('projects.id');
        $candidates = DevelopmentTask::where('status', 'QUEUED')->whereIn('project_id', $projectIds)
            ->orderByRaw("CASE priority WHEN 'URGENT' THEN 0 WHEN 'HIGH' THEN 1 WHEN 'NORMAL' THEN 2 ELSE 3 END")
            ->orderBy('created_at')->cursor();
        $task = $candidates->first(fn ($candidate) => $lifecycle->eligible($candidate, $worker));

        return response()->json(['job' => $task ? ['id' => $task->id, 'type' => $task->type, 'title' => $task->title, 'project_slug' => $task->project->slug, 'requires_approval' => $task->requires_approval] : null]);
    }

    public function accept(Request $request, DevelopmentTask $task, Lifecycle $lifecycle)
    {
        $worker = $this->worker($request);
        $execution = $lifecycle->accept($task, $worker);

        return response()->json(['execution_id' => $execution->id, 'task_id' => $task->id, 'project_slug' => $task->project->slug,
            'type' => $task->type, 'prompt' => $execution->prompt, 'test_commands' => $task->type === 'technical_analysis' ? [] : ($task->project->test_commands ?? []),
            'sandbox' => app(CodexLocalAgent::class)->sandboxFor($task)]);
    }

    private function owned(Request $request, Execution $execution): Worker
    {
        $worker = $this->worker($request);
        abort_unless($execution->worker_id === $worker->id && $execution->status === 'RUNNING', 403);

        return $worker;
    }

    public function progress(Request $request, Execution $execution, Lifecycle $lifecycle)
    {
        $worker = $this->owned($request, $execution);
        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $execution->logs()->create(['level' => 'info', 'message' => SensitiveText::clean($data['message'], 10000)]);
        $lifecycle->event($execution->task->requirement, 'execution.progress', 'worker', details: ['message' => SensitiveText::clean($data['message'], 1000)], task: $execution->task, execution: $execution, worker: $worker);

        return response()->json(['ok' => true]);
    }

    public function logs(Request $request, Execution $execution)
    {
        $this->owned($request, $execution);
        $data = $request->validate(['entries' => ['required', 'array', 'max:50'], 'entries.*.level' => ['required', Rule::in(['debug', 'info', 'warning', 'error'])], 'entries.*.message' => ['required', 'string', 'max:10000']]);
        foreach ($data['entries'] as $entry) {
            $execution->logs()->create(['level' => $entry['level'], 'message' => SensitiveText::clean($entry['message'], 10000)]);
        }

        return response()->json(['ok' => true]);
    }

    private function finishData(Request $request): array
    {
        return $request->validate([
            'summary' => ['required', 'string', 'max:4000'], 'stdout' => ['nullable', 'string', 'max:65536'], 'stderr' => ['nullable', 'string', 'max:65536'],
            'modified_files' => ['nullable', 'array', 'max:500'], 'modified_files.*' => ['string', 'max:500'],
            'branch' => ['nullable', 'string', 'max:255'], 'commit' => ['nullable', 'string', 'max:100'], 'tests' => ['nullable', 'array'],
            'result' => ['nullable', 'array'], 'error' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    public function complete(Request $request, Execution $execution, Lifecycle $lifecycle)
    {
        $worker = $this->owned($request, $execution);
        $lifecycle->finish($execution, $worker, true, $this->finishData($request));

        return response()->json(['status' => $execution->fresh()->status]);
    }

    public function fail(Request $request, Execution $execution, Lifecycle $lifecycle)
    {
        $worker = $this->owned($request, $execution);
        $lifecycle->finish($execution, $worker, false, $this->finishData($request));

        return response()->json(['status' => 'FAILED']);
    }

    public function requestApproval(Request $request, Execution $execution, Lifecycle $lifecycle)
    {
        $worker = $this->owned($request, $execution);
        $data = $request->validate(['action' => ['required', 'string', 'max:255'], 'reason' => ['required', 'string', 'max:4000']]);
        $approval = $lifecycle->requestApproval($execution->task->requirement, $data['action'], $data['reason'], $execution->task, $worker);

        return response()->json(['approval_id' => $approval->id, 'status' => 'PENDING'], 202);
    }
}
