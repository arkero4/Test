<?php

namespace Tests\Feature;

use App\Models\DevelopmentTask;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Models\Worker;
use App\Services\Lifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkerFlowTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(string $requirementStatus = 'TECHNICAL_ANALYSIS', string $type = 'technical_analysis'): array
    {
        $project = Project::factory()->create();
        $requirement = Requirement::factory()->create(['project_id' => $project->id, 'status' => $requirementStatus]);
        $task = DevelopmentTask::factory()->create(['project_id' => $project->id, 'requirement_id' => $requirement->id, 'type' => $type, 'status' => 'QUEUED']);
        $token = str_repeat('a', 64);
        $worker = Worker::factory()->create(['token_hash' => hash('sha256', $token)]);
        $worker->projects()->attach($project);

        return compact('project', 'requirement', 'task', 'token', 'worker');
    }

    public function test_worker_api_rejects_requests_without_its_token(): void
    {
        $this->getJson('/api/workers/jobs/next')->assertUnauthorized();
        $this->withToken(str_repeat('x', 64))->getJson('/api/workers/jobs/next')->assertUnauthorized();
    }

    public function test_worker_cannot_accept_job_for_unauthorized_project(): void
    {
        $data = $this->scenario();
        $data['worker']->projects()->detach();
        $this->withToken($data['token'])->postJson("/api/jobs/{$data['task']->id}/accept")->assertUnprocessable();
        $this->assertDatabaseMissing('executions', ['task_id' => $data['task']->id]);
    }

    public function test_analysis_job_receives_read_only_context_and_returns_diagnosis(): void
    {
        $data = $this->scenario();
        $data['requirement']->update([
            'topic_key' => 'gmail:thread:42',
            'subject' => 'Corregir exportación',
            'original_content' => 'La columna de fecha no aparece en el CSV.',
            'summary' => 'Revisar exportación CSV',
            'context' => 'El cliente usa el reporte de ventas.',
        ]);
        $data['project']->contexts()->create(['kind' => 'architecture', 'title' => 'Módulos', 'content' => 'CRM local']);
        $response = $this->withToken($data['token'])->postJson("/api/jobs/{$data['task']->id}/accept")
            ->assertOk()->assertJsonPath('sandbox', 'read-only')->assertJsonPath('topic_key', 'gmail:thread:42');
        $prompt = $response->json('prompt');
        foreach (['CRM local', 'La columna de fecha no aparece en el CSV.', 'Revisar exportación CSV',
            'El cliente usa el reporte de ventas.', 'no instrucciones para el agente'] as $text) {
            $this->assertStringContainsString($text, $prompt);
        }
        $executionId = $response->json('execution_id');
        $this->withToken($data['token'])->postJson("/api/jobs/{$executionId}/complete", [
            'summary' => 'Causa probable identificada', 'modified_files' => [], 'result' => [
                'probable_cause' => 'Validación ausente', 'evidence' => ['Archivo revisado'], 'affected_components' => ['CRM'],
                'risk' => 'LOW', 'proposed_solution' => 'Validar entrada', 'suggested_subtasks' => [], 'required_tests' => [], 'missing_information' => [],
                'branch_recommendation' => ['create_new' => true, 'reason' => 'Cambio aislado', 'suggested_name' => 'fix/export-csv'],
            ],
            'codex_thread_id' => '01a0efe4-1c0b-7462-98d5-26650e463bc6',
        ])->assertOk()->assertJsonPath('status', 'COMPLETED');
        $this->assertDatabaseHas('requirements', ['id' => $data['requirement']->id, 'status' => 'PLANNING']);
        $this->assertDatabaseHas('requirements', ['id' => $data['requirement']->id, 'codex_thread_id' => '01a0efe4-1c0b-7462-98d5-26650e463bc6']);
        $this->assertDatabaseHas('approvals', ['requirement_id' => $data['requirement']->id, 'action' => 'create_branch', 'status' => 'PENDING']);
        $related = Requirement::factory()->create(['project_id' => $data['project']->id, 'topic_key' => 'gmail:thread:42', 'status' => 'TECHNICAL_ANALYSIS']);
        $relatedTask = DevelopmentTask::factory()->create(['requirement_id' => $related->id, 'project_id' => $data['project']->id, 'type' => 'technical_analysis', 'status' => 'QUEUED']);
        $this->withToken($data['token'])->postJson("/api/jobs/{$relatedTask->id}/accept")
            ->assertOk()->assertJsonPath('codex_thread_id', '01a0efe4-1c0b-7462-98d5-26650e463bc6');
        $this->assertDatabaseHas('requirement_events', ['requirement_id' => $data['requirement']->id, 'action' => 'execution.finished']);
    }

    public function test_analysis_with_modified_files_fails_and_does_not_advance(): void
    {
        $data = $this->scenario();
        $response = $this->withToken($data['token'])->postJson("/api/jobs/{$data['task']->id}/accept")->assertOk();
        $this->withToken($data['token'])->postJson('/api/jobs/'.$response->json('execution_id').'/complete', [
            'summary' => 'Cambios detectados', 'modified_files' => ['app/Service.php'],
        ])->assertOk()->assertJsonPath('status', 'FAILED');
        $this->assertDatabaseHas('requirements', ['id' => $data['requirement']->id, 'status' => 'FAILED']);
    }

    public function test_analysis_without_structured_diagnosis_is_rejected(): void
    {
        $data = $this->scenario();
        $response = $this->withToken($data['token'])->postJson("/api/jobs/{$data['task']->id}/accept")->assertOk();
        $this->withToken($data['token'])->postJson('/api/jobs/'.$response->json('execution_id').'/complete', [
            'summary' => 'Diagnóstico incompleto', 'result' => ['probable_cause' => 'Desconocida'],
        ])->assertUnprocessable()->assertJsonValidationErrors('result');
        $this->assertDatabaseHas('executions', ['id' => $response->json('execution_id'), 'status' => 'RUNNING']);
    }

    public function test_implementation_completion_enters_testing_and_scrubs_bearer_token(): void
    {
        $data = $this->scenario('QUEUED', 'implementation');
        $response = $this->withToken($data['token'])->postJson("/api/jobs/{$data['task']->id}/accept")->assertOk()->assertJsonPath('sandbox', 'workspace-write');
        $this->assertDatabaseHas('requirements', ['id' => $data['requirement']->id, 'status' => 'IMPLEMENTING']);
        $this->withToken($data['token'])->postJson('/api/jobs/'.$response->json('execution_id').'/complete', [
            'summary' => 'Hecho', 'stdout' => 'Authorization: Bearer secret-token-value', 'modified_files' => ['app/Foo.php'],
        ])->assertOk()->assertJsonPath('status', 'COMPLETED');
        $this->assertDatabaseHas('requirements', ['id' => $data['requirement']->id, 'status' => 'TESTING']);
        $this->assertDatabaseMissing('executions', ['id' => $response->json('execution_id'), 'stdout' => 'Authorization: Bearer secret-token-value']);
    }

    public function test_heartbeat_requests_stop_after_requirement_is_cancelled(): void
    {
        $data = $this->scenario('QUEUED', 'implementation');
        $this->withToken($data['token'])->postJson("/api/jobs/{$data['task']->id}/accept")->assertOk();
        $data['requirement']->update(['status' => 'CANCELLED']);

        $this->withToken($data['token'])->postJson('/api/workers/heartbeat')
            ->assertOk()->assertJsonPath('cancel_requested', true);
    }

    public function test_approval_is_required_before_completion(): void
    {
        $requirement = Requirement::factory()->create(['status' => 'WAITING_APPROVAL', 'requires_approval' => true]);
        $lifecycle = app(Lifecycle::class);
        $this->expectException(ValidationException::class);
        $lifecycle->transition($requirement, 'COMPLETED', 'user');
    }

    public function test_task_marked_for_approval_waits_for_human_decision(): void
    {
        $data = $this->scenario('QUEUED', 'implementation');
        $data['task']->update(['requires_approval' => true]);
        $response = $this->withToken($data['token'])->postJson("/api/jobs/{$data['task']->id}/accept")->assertOk();
        $this->withToken($data['token'])->postJson('/api/jobs/'.$response->json('execution_id').'/complete', [
            'summary' => 'Cambio listo', 'modified_files' => ['app/Foo.php'],
        ])->assertOk();
        $this->assertDatabaseHas('requirements', ['id' => $data['requirement']->id, 'status' => 'WAITING_APPROVAL']);
        $approval = $data['requirement']->approvals()->firstOrFail();
        $this->assertSame('PENDING', $approval->status);
        $this->actingAs(User::factory()->create())->post(route('requirements.approvals.decide', [$data['requirement'], $approval]), ['decision' => 'APPROVED'])->assertRedirect();
        $this->actingAs(User::factory()->create())->post(route('requirements.transition', $data['requirement']), ['status' => 'TESTING'])->assertRedirect();
        $this->assertDatabaseHas('requirements', ['id' => $data['requirement']->id, 'status' => 'TESTING']);
    }

    public function test_worker_registration_rejects_unexpected_environment_keys(): void
    {
        $data = $this->scenario();
        $this->withToken($data['token'])->postJson('/api/workers/register', [
            'uuid' => $data['worker']->uuid, 'environment' => ['platform' => 'darwin', 'secret' => 'should-not-be-sent'],
        ])->assertUnprocessable()->assertJsonValidationErrors('environment');
    }

    public function test_worker_token_is_shown_once_without_placing_it_in_session(): void
    {
        $worker = Worker::factory()->create(['token_hash' => null]);
        $response = $this->actingAs(User::factory()->create())->post(route('workers.token', $worker));
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSessionMissing('issued_token');
        $this->assertNotNull($worker->fresh()->token_hash);
        $this->assertSame(64, strlen($worker->fresh()->token_hash));
    }

    public function test_panel_forms_render_for_authenticated_administrator(): void
    {
        $data = $this->scenario();
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('projects.create'))->assertOk();
        $this->actingAs($user)->get(route('projects.edit', $data['project']))->assertOk();
        $this->actingAs($user)->get(route('workers.create'))->assertOk();
        $this->actingAs($user)->get(route('workers.edit', $data['worker']))->assertOk();
        $this->actingAs($user)->get(route('requirements.create'))->assertOk();
        $this->actingAs($user)->get(route('tasks.create', $data['requirement']))->assertOk();
    }

    public function test_requirement_page_escapes_untrusted_original_content(): void
    {
        $requirement = Requirement::factory()->create(['original_content' => '<script>alert(1)</script>']);
        $this->actingAs(User::factory()->create())->get(route('requirements.show', $requirement))
            ->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
