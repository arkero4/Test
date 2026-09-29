<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Worker;
use App\Services\SensitiveText;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index()
    {
        return view('projects.index', ['projects' => Project::with('preferredWorker')->orderBy('name')->paginate(25)]);
    }

    public function create()
    {
        return view('projects.form', ['project' => new Project, 'workers' => Worker::orderBy('name')->get()]);
    }

    public function edit(Project $project)
    {
        return view('projects.form', ['project' => $project->load('contexts'), 'workers' => Worker::orderBy('name')->get()]);
    }

    private function fields(Request $request, ?Project $project = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('projects')->ignore($project?->id)],
            'client' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'stack' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:100'], 'type' => ['required', Rule::in(['laravel_modern', 'laravel_legacy', 'flutter', 'python', 'rpa', 'integration', 'orchestrator', 'other'])],
            'repository' => ['nullable', 'url', 'max:255'], 'status' => ['required', Rule::in(['ACTIVE', 'PAUSED', 'ARCHIVED'])],
            'preferred_worker_id' => ['nullable', 'exists:workers,id'], 'preferred_agent' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string'], 'test_commands_text' => ['nullable', 'string'], 'restrictions' => ['nullable', 'string'], 'documentation' => ['nullable', 'string'],
        ]);
        $commands = array_values(array_filter(array_map('trim', explode("\n", $data['test_commands_text'] ?? ''))));
        unset($data['test_commands_text']);
        $data['test_commands'] = $commands;

        return SensitiveText::cleanArray($data);
    }

    public function store(Request $request)
    {
        $project = Project::create($this->fields($request));

        return redirect()->route('projects.edit', $project)->with('ok', 'Proyecto creado.');
    }

    public function update(Request $request, Project $project)
    {
        $project->update($this->fields($request, $project));

        return back()->with('ok', 'Proyecto actualizado.');
    }

    public function destroy(Project $project)
    {
        $project->update(['status' => 'ARCHIVED']);

        return redirect()->route('projects.index')->with('ok', 'Proyecto archivado.');
    }

    public function context(Request $request, Project $project)
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['architecture', 'module', 'convention', 'command', 'restriction', 'known_error', 'integration', 'decision', 'agent_instruction', 'documentation'])], 'title' => ['required', 'string', 'max:255'], 'content' => ['required', 'string']]);
        $project->contexts()->create(SensitiveText::cleanArray($data));

        return back()->with('ok', 'Contexto agregado.');
    }

    public function deleteContext(Project $project, int $context)
    {
        $project->contexts()->findOrFail($context)->delete();

        return back()->with('ok', 'Contexto eliminado.');
    }
}
