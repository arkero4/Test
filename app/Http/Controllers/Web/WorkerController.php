<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Worker;
use App\Services\SensitiveText;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkerController extends Controller
{
    public function index()
    {
        return view('workers.index', ['workers' => Worker::with('projects')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('workers.form', ['worker' => new Worker, 'projects' => Project::orderBy('name')->get()]);
    }

    public function edit(Worker $worker)
    {
        return view('workers.form', ['worker' => $worker->load('projects'), 'projects' => Project::orderBy('name')->get()]);
    }

    private function fields(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['OFFLINE', 'ONLINE', 'DISABLED'])],
            'capabilities_text' => ['nullable', 'string'], 'project_ids' => ['array'], 'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);
        $projects = $data['project_ids'] ?? [];
        $data['capabilities'] = array_values(array_filter(array_map('trim', explode(',', $data['capabilities_text'] ?? ''))));
        unset($data['project_ids'], $data['capabilities_text']);

        return [SensitiveText::cleanArray($data), $projects];
    }

    public function store(Request $request)
    {
        [$data, $projects] = $this->fields($request);
        $worker = Worker::create($data + ['uuid' => (string) Str::uuid()]);
        $worker->projects()->sync($projects);

        return redirect()->route('workers.edit', $worker)->with('ok', 'Worker creado. Emite su token antes de configurarlo.');
    }

    public function update(Request $request, Worker $worker)
    {
        [$data, $projects] = $this->fields($request);
        $worker->update($data);
        $worker->projects()->sync($projects);

        return back()->with('ok', 'Worker actualizado.');
    }

    public function token(Worker $worker)
    {
        $token = bin2hex(random_bytes(32));
        $worker->update(['token_hash' => hash('sha256', $token)]);

        return response()->view('workers.token', ['worker' => $worker, 'token' => $token])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function destroy(Worker $worker)
    {
        $worker->update(['status' => 'DISABLED', 'token_hash' => null]);

        return redirect()->route('workers.index')->with('ok', 'Worker deshabilitado y token revocado.');
    }
}
