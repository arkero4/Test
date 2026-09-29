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
        $data = $request->validate([
            'source' => ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_-]{0,63}\z/'],
            'external_reference' => ['required', 'string', 'max:255'],
            'sender' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'original_content' => ['required', 'string', 'max:65536'],
            'summary' => ['nullable', 'string', 'max:8192'],
            'context' => ['nullable', 'string', 'max:65536'],
            'project_slug' => ['nullable', 'string', Rule::exists('projects', 'slug')->where('status', 'ACTIVE')],
            'classification_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'kind' => ['sometimes', Rule::in(['DEVELOPMENT', 'SYSTEM_IMPROVEMENT'])],
            'priority' => ['sometimes', Rule::in(['LOW', 'NORMAL', 'HIGH', 'URGENT'])],
            'risk' => ['sometimes', Rule::in(['UNKNOWN', 'LOW', 'MEDIUM', 'HIGH'])],
            'requires_approval' => ['sometimes', 'boolean'],
            'received_at' => ['nullable', 'date'],
        ]);

        $projectId = isset($data['project_slug']) ? Project::where('slug', $data['project_slug'])->value('id') : null;
        $client = $request->attributes->get('ingestionClient');
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
                    'project_id' => $projectId,
                    'classification_confidence' => $data['classification_confidence'] ?? null,
                    'priority' => $data['priority'] ?? 'NORMAL',
                    'risk' => $data['risk'] ?? 'UNKNOWN',
                    'requires_approval' => $data['requires_approval'] ?? false,
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

        return response()->json([
            'id' => $requirement->id,
            'status' => $requirement->status,
            'source' => $requirement->source,
            'external_reference' => $requirement->external_reference,
            'created' => $requirement->wasRecentlyCreated,
            'url' => route('requirements.show', $requirement),
        ], $requirement->wasRecentlyCreated ? 201 : 200);
    }
}
