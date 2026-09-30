<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Requirement;
use App\Services\Lifecycle;
use App\Services\SensitiveText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RequirementIngestionController extends Controller
{
    public function store(Request $request, Lifecycle $lifecycle)
    {
        $reference = $request->validate([
            'source' => ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_-]{0,63}\z/'],
            'external_reference' => ['required', 'string', 'max:255'],
        ]);
        $client = $request->attributes->get('ingestionClient');
        $existing = Requirement::where('source', $reference['source'])
            ->where('external_reference', $reference['external_reference'])->first();
        if ($existing) {
            return $this->replayResponse($existing, $client->slug);
        }

        $data = $request->validate([
            'source' => ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_-]{0,63}\z/'],
            'external_reference' => ['required', 'string', 'max:255'],
            'topic_key' => ['nullable', 'string', 'max:255'],
            'sender' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'original_content' => ['required', 'string', 'max:65536'],
            'summary' => ['nullable', 'string', 'max:8192'],
            'context' => ['nullable', 'string', 'max:65536'],
            'project_slug' => ['nullable', 'string', Rule::exists('projects', 'slug')->where('status', 'ACTIVE')],
            'classification_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'kind' => ['sometimes', Rule::in(['DEVELOPMENT', 'PROJECT_RESPONSE', 'SYSTEM_IMPROVEMENT'])],
            'priority' => ['sometimes', Rule::in(['LOW', 'NORMAL', 'HIGH', 'URGENT'])],
            'risk' => ['sometimes', Rule::in(['UNKNOWN', 'LOW', 'MEDIUM', 'HIGH'])],
            'requires_approval' => ['sometimes', 'boolean'],
            'received_at' => ['nullable', 'date'],
        ]);

        $projectId = isset($data['project_slug']) ? Project::where('slug', $data['project_slug'])->value('id') : null;
        $fields = SensitiveText::cleanArray([
            'sender' => $data['sender'] ?? null,
            'subject' => $data['subject'],
            'original_content' => $data['original_content'],
            'summary' => $data['summary'] ?? null,
            'context' => $data['context'] ?? null,
        ]);

        $requirement = DB::transaction(function () use ($data, $fields, $projectId, $client, $lifecycle) {
            $requirement = Requirement::createOrFirst(
                ['source' => $data['source'], 'external_reference' => $data['external_reference']],
                $fields + [
                    'kind' => $data['kind'] ?? 'DEVELOPMENT',
                    'topic_key' => $data['topic_key'] ?? null,
                    'project_id' => $projectId,
                    'classification_confidence' => $data['classification_confidence'] ?? null,
                    'priority' => $data['priority'] ?? 'NORMAL',
                    'risk' => $data['risk'] ?? 'UNKNOWN',
                    'requires_approval' => ($data['kind'] ?? 'DEVELOPMENT') === 'PROJECT_RESPONSE' || ($data['requires_approval'] ?? false),
                    'received_at' => $data['received_at'] ?? now(),
                    'status' => 'RECEIVED',
                ],
            );

            if ($requirement->wasRecentlyCreated) {
                $lifecycle->event($requirement, 'requirement.created', 'integration:'.$client->slug,
                    to: 'RECEIVED', details: ['source' => $data['source'], 'external_reference' => $data['external_reference']]);
            }

            return $requirement;
        });

        if (! $requirement->wasRecentlyCreated) {
            return $this->replayResponse($requirement, $client->slug);
        }

        return response()->json([
            'id' => $requirement->id,
            'status' => $requirement->status,
            'source' => $requirement->source,
            'external_reference' => $requirement->external_reference,
            'created' => true,
            'url' => route('requirements.show', $requirement),
        ], 201);
    }

    private function replayResponse(Requirement $requirement, string $clientSlug)
    {
        if (! $requirement->events()->where('action', 'requirement.created')->where('actor', 'integration:'.$clientSlug)->exists()) {
            return response()->json(['message' => 'Referencia externa ya utilizada.'], 409);
        }

        return response()->json([
            'id' => $requirement->id,
            'status' => $requirement->status,
            'source' => $requirement->source,
            'external_reference' => $requirement->external_reference,
            'created' => false,
            'url' => route('requirements.show', $requirement),
        ]);
    }
}
