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

class RequirementController extends Controller
{
    public function index()
    {
        return view('requirements.index', ['requirements' => Requirement::with('project')->latest()->paginate(25)]);
    }

    public function create()
    {
        return view('requirements.form', ['requirement' => new Requirement, 'projects' => Project::where('status', 'ACTIVE')->orderBy('name')->get()]);
    }

    public function edit(Requirement $requirement)
    {
        return view('requirements.form', ['requirement' => $requirement, 'projects' => Project::where('status', 'ACTIVE')->orderBy('name')->get()]);
    }

    public function show(Requirement $requirement)
    {
        return view('requirements.show', ['requirement' => $requirement->load('project', 'plans', 'tasks.executions.worker', 'events', 'approvals'), 'projects' => Project::where('status', 'ACTIVE')->orderBy('name')->get()]);
    }

    private function fields(Request $request): array
    {
        return SensitiveText::cleanArray($request->validate([
            'kind' => ['required', Rule::in(['DEVELOPMENT', 'SYSTEM_IMPROVEMENT'])],
            'source' => ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_-]{0,63}\z/'],
            'external_reference' => ['nullable', 'string', 'max:255'], 'sender' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'], 'original_content' => ['required', 'string'],
            'summary' => ['nullable', 'string'], 'context' => ['nullable', 'string'],
            'project_id' => ['nullable', 'exists:projects,id'], 'priority' => ['required', Rule::in(['LOW', 'NORMAL', 'HIGH', 'URGENT'])],
            'risk' => ['required', Rule::in(['UNKNOWN', 'LOW', 'MEDIUM', 'HIGH'])], 'requires_approval' => ['boolean'],
        ]));
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
        $requirement->update($this->fields($request));
        $lifecycle->event($requirement, 'requirement.updated', 'user', userId: $request->user()->id);

        return redirect()->route('requirements.show', $requirement)->with('ok', 'Requerimiento actualizado.');
    }

    public function transition(Request $request, Requirement $requirement, Lifecycle $lifecycle)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Lifecycle::NEXT))]]);
        $lifecycle->transition($requirement, $data['status'], 'user', $request->user()->id);

        return back()->with('ok', 'Estado actualizado.');
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
