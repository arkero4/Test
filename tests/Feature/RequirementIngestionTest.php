<?php

namespace Tests\Feature;

use App\Models\IngestionClient;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RequirementIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        $token = 'dvo_ing_'.str_repeat('a', 64);
        IngestionClient::create(['slug' => 'dot', 'name' => 'Dot', 'token_hash' => hash('sha256', $token)]);

        return $token;
    }

    private function payload(): array
    {
        return [
            'source' => 'email',
            'external_reference' => 'gmail:jaime.fuentes@tecnich.cl:message-123',
            'sender' => 'cliente@example.test',
            'subject' => 'Corregir exportación',
            'original_content' => 'El CSV tiene una columna incorrecta.',
        ];
    }

    public function test_ingestion_requires_its_own_token_and_rejects_worker_tokens(): void
    {
        $this->postJson('/api/v1/requirements', $this->payload())->assertUnauthorized();
        Worker::factory()->create(['token_hash' => hash('sha256', str_repeat('b', 64))]);
        $this->withToken(str_repeat('b', 64))->postJson('/api/v1/requirements', $this->payload())->assertUnauthorized();

        $token = $this->token();
        IngestionClient::where('slug', 'dot')->update(['status' => 'DISABLED']);
        $this->withToken($token)->postJson('/api/v1/requirements', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('requirements', 0);
    }

    public function test_dot_creates_one_audited_requirement_and_retries_are_idempotent(): void
    {
        $project = Project::factory()->create(['slug' => 'crm-nutrisco']);
        $token = $this->token();
        $payload = $this->payload() + ['project_slug' => $project->slug, 'classification_confidence' => 0.82];

        $first = $this->withToken($token)->postJson('/api/v1/requirements', $payload)
            ->assertCreated()->assertJsonPath('created', true)->assertJsonPath('status', 'RECEIVED');
        $id = $first->json('id');

        $this->withToken($token)->postJson('/api/v1/requirements', $payload)
            ->assertOk()->assertJsonPath('created', false)->assertJsonPath('id', $id);
        $this->assertDatabaseCount('requirements', 1);
        $this->assertDatabaseCount('requirement_events', 1);
        $this->assertDatabaseHas('requirements', ['id' => $id, 'project_id' => $project->id, 'status' => 'RECEIVED']);
        $this->assertDatabaseHas('requirement_events', ['requirement_id' => $id, 'actor' => 'integration:dot', 'action' => 'requirement.created']);
        $this->assertSame(0, Requirement::findOrFail($id)->tasks()->count());
    }

    public function test_retry_uses_stable_reference_even_if_project_was_deactivated_and_rejects_foreign_collisions(): void
    {
        $project = Project::factory()->create(['slug' => 'crm-nutrisco']);
        $token = $this->token();
        $payload = $this->payload() + ['project_slug' => $project->slug];
        $id = $this->withToken($token)->postJson('/api/v1/requirements', $payload)->assertCreated()->json('id');
        $project->update(['status' => 'INACTIVE']);

        $this->withToken($token)->postJson('/api/v1/requirements', [
            'source' => $payload['source'], 'external_reference' => $payload['external_reference'],
        ])->assertOk()->assertJsonPath('id', $id)->assertJsonPath('created', false);

        $otherToken = 'dvo_ing_'.str_repeat('b', 64);
        IngestionClient::create(['slug' => 'other', 'name' => 'Other', 'token_hash' => hash('sha256', $otherToken)]);
        $this->withToken($otherToken)->postJson('/api/v1/requirements', $payload)->assertStatus(409);
        $this->assertDatabaseCount('requirements', 1);
        $this->assertDatabaseCount('requirement_events', 1);
    }

    public function test_sources_are_extensible_and_unknown_projects_are_rejected(): void
    {
        $token = $this->token();
        $this->withToken($token)->postJson('/api/v1/requirements', $this->payload() + ['project_slug' => 'unknown'])
            ->assertUnprocessable()->assertJsonValidationErrors('project_slug');

        $payload = array_replace($this->payload(), ['source' => 'slack', 'external_reference' => 'workspace:channel:123']);
        $this->withToken($token)->postJson('/api/v1/requirements', $payload)->assertCreated();
        $this->assertDatabaseHas('requirements', ['source' => 'slack', 'external_reference' => 'workspace:channel:123', 'project_id' => null]);
    }

    public function test_project_email_requiring_only_a_response_can_be_recorded_without_a_task(): void
    {
        $token = $this->token();
        $payload = array_replace($this->payload(), [
            'external_reference' => 'gmail:jaime.fuentes@tecnich.cl:reply-456',
            'kind' => 'PROJECT_RESPONSE',
            'requires_approval' => false,
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/requirements', $payload)
            ->assertCreated()->assertJsonPath('status', 'RECEIVED');
        $requirement = Requirement::findOrFail($response->json('id'));
        $this->assertSame('PROJECT_RESPONSE', $requirement->kind);
        $this->assertTrue($requirement->requires_approval);
        $this->assertSame(0, $requirement->tasks()->count());
    }

    public function test_token_command_rotates_and_revokes_access(): void
    {
        Artisan::call('orchestrator:ingestion-token', ['slug' => 'dot']);
        preg_match('/dvo_ing_[a-f0-9]{64}/', Artisan::output(), $matches);
        $first = $matches[0] ?? '';
        $this->assertMatchesRegularExpression('/\Advo_ing_[a-f0-9]{64}\z/', $first);

        Artisan::call('orchestrator:ingestion-token', ['slug' => 'dot']);
        $client = IngestionClient::where('slug', 'dot')->firstOrFail();
        $this->assertNotSame(hash('sha256', $first), $client->token_hash);
        $this->assertDatabaseCount('ingestion_clients', 1);

        Artisan::call('orchestrator:ingestion-token', ['slug' => 'dot', '--revoke' => true]);
        $this->assertDatabaseHas('ingestion_clients', ['slug' => 'dot', 'status' => 'DISABLED', 'token_hash' => null]);
    }
}
