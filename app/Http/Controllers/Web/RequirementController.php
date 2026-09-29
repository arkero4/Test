<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Project;
use App\Models\Requirement;
use App\Services\Lifecycle;
use App\Services\SensitiveText;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequirementController extends Controller
{
    public function index(Request $request)
    {
        $kind = $request->query('kind');
        if ($kind !== null && ! in_array($kind, ['DEVELOPMENT', 'PROJECT_RESPONSE', 'SYSTEM_IMPROVEMENT'], true)) {
            abort(422);
        }

        return view('requirements.index', [
            'requirements' => Requirement::with('project')->when($kind, fn ($query) => $query->where('kind', $kind))->latest()->paginate(25)->withQueryString(),
            'kind' => $kind,
        ]);
    }

    public function create()
    {
        return view('requirements.form', ['requirement' => new Requirement, 'projects' => Project::where('status', 'ACTIVE')->orderBy('name')->get()]);
    }

    public function edit(Requirement $requirement)
    {
        return view('requirements.form', ['requirement' => $requirement, 'projects' => Project::where('status', 'ACTIVE')->orderBy('name')->get()]);
    }

    public function show(Requirement $requirement, Lifecycle $lifecycle)
    {
        return view('requirements.show', [
            'requirement' => $requirement->load('project', 'plans', 'tasks.executions.worker', 'events', 'approvals'),
            'nextStatuses' => $lifecycle->availableTransitions($requirement),
        ]);
    }

    private function fields(Request $request): array
    {
        $data = SensitiveText::cleanArray($request->validate([
            'kind' => ['required', Rule::in(['DEVELOPMENT', 'PROJECT_RESPONSE', 'SYSTEM_IMPROVEMENT'])],
            'source' => ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_-]{0,63}\z/'],
            'external_reference' => ['nullable', 'string', 'max:255'], 'sender' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'], 'original_content' => ['required', 'string'],
            'summary' => ['nullable', 'string'], 'context' => ['nullable', 'string'],
            'project_id' => ['nullable', 'exists:projects,id'], 'priority' => ['required', Rule::in(['LOW', 'NORMAL', 'HIGH', 'URGENT'])],
            'risk' => ['required', Rule::in(['UNKNOWN', 'LOW', 'MEDIUM', 'HIGH'])], 'requires_approval' => ['boolean'],
        ]));
        if ($data['kind'] === 'PROJECT_RESPONSE') {
            $data['requires_approval'] = true;
        }

        return $data;
    }

    public function store(Request $request, Lifecycle $lifecycle)
    {
        $requirement = Requirement::create($this->fields($request) + ['status' => 'RECEIVED']);
        $lifecycle->event($requirement, 'requirement.created', 'user', to: 'RECEIVED', userId: $request->user()->id);

        return redirect()->route('requirements.show', $requirement)->with('ok', 'Requerimiento creado.');
    }

    public function update(Request $request, Requirement $requirement, Lifecycle $lifecycle)
    {
        if (in_array($requirement->status, ['COMPLETED', 'CANCELLED'], true)) {
            abort(409);
        }
        $fields = $this->fields($request);
        if ($fields['kind'] !== $requirement->kind && (! in_array($requirement->status, ['RECEIVED', 'ANALYZING', 'CLASSIFIED', 'WAITING_INFORMATION'], true) || $requirement->tasks()->exists())) {
            throw ValidationException::withMessages(['kind' => 'El tipo no se puede cambiar después de iniciar el trabajo.']);
        }
        $requirement->update($fields);
        $lifecycle->event($requirement, 'requirement.updated', 'user', userId: $request->user()->id);

        return redirect()->route('requirements.show', $requirement)->with('ok', 'Requerimiento actualizado.');
    }

    public function transition(Request $request, Requirement $requirement, Lifecycle $lifecycle)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Lifecycle::NEXT))]]);
        $lifecycle->transition($requirement, $data['status'], 'user', $request->user()->id);

        return back()->with('ok', 'Estado actualizado.');
    }

    public function recordResponse(Request $request, Requirement $requirement, Lifecycle $lifecycle)
    {
        $data = $request->validate(['response_note' => ['required', 'string', 'max:4000']]);
        $lifecycle->completeResponse($requirement, $data['response_note'], $request->user()->id);

        return back()->with('ok', 'Respuesta registrada y requerimiento completado.');
    }

    public function plan(Request $request, Requirement $requirement, Lifecycle $lifecycle)
    {
        $data = $request->validate(['summary' => ['required', 'string']]);
        if ($requirement->status !== 'PLANNING') {
            abort(409);
        }
        $requirement->plans()->create(SensitiveText::cleanArray($data) + ['version' => ($requirement->plans()->max('version') ?? 0) + 1]);
        $lifecycle->event($requirement, 'plan.created', 'user', userId: $request->user()->id);

        return back()->with('ok', 'Plan creado.');
    }

    public function requestApproval(Request $request, Requirement $requirement, Lifecycle $lifecycle)
    {
        $data = $request->validate(['action' => ['required', 'string', 'max:255'], 'reason' => ['required', 'string']]);
        $lifecycle->requestApproval($requirement, SensitiveText::clean($data['action']), SensitiveText::clean($data['reason']));

        return back()->with('ok', 'Aprobación solicitada.');
    }

    public function decide(Request $request, Requirement $requirement, Approval $approval, Lifecycle $lifecycle)
    {
        abort_unless($approval->requirement_id === $requirement->id && $approval->status === 'PENDING', 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])]]);
        $approval->update(['status' => $data['decision'], 'decided_by_user_id' => $request->user()->id, 'decided_at' => now()]);
        $lifecycle->event($requirement, 'approval.decided', 'user', details: ['decision' => $data['decision'], 'action' => $approval->action], userId: $request->user()->id);
        if ($data['decision'] === 'REJECTED' && $requirement->status === 'WAITING_APPROVAL') {
            $lifecycle->transition($requirement, 'FAILED', 'user', $request->user()->id);
        }

        return back()->with('ok', 'Decisión registrada.');
    }
}
