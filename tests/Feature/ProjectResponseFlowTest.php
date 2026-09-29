<?php

namespace Tests\Feature;

use App\Models\IngestionClient;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectResponseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_requirement_is_completed_only_after_human_records_what_was_sent(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['kind' => 'PROJECT_RESPONSE', 'requires_approval' => true]);

        $this->actingAs($user)->post(route('requirements.transition', $requirement), ['status' => 'WAITING_RESPONSE'])
            ->assertRedirect();
        $this->assertDatabaseHas('requirements', ['id' => $requirement->id, 'status' => 'WAITING_RESPONSE']);
        $this->actingAs($user)->post(route('requirements.response', $requirement), ['response_note' => ''])
            ->assertSessionHasErrors('response_note');
        $this->assertDatabaseHas('requirements', ['id' => $requirement->id, 'status' => 'WAITING_RESPONSE']);

        $this->actingAs($user)->post(route('requirements.response', $requirement), ['response_note' => 'Respondí por Gmail con los pasos y fecha de revisión.'])
            ->assertRedirect();
        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id, 'status' => 'COMPLETED',
            'responded_by_user_id' => $user->id,
            'response_note' => 'Respondí por Gmail con los pasos y fecha de revisión.',
        ]);
        $this->assertDatabaseHas('requirement_events', ['requirement_id' => $requirement->id, 'action' => 'response.recorded', 'user_id' => $user->id]);
        $this->actingAs($user)->post(route('requirements.response', $requirement), ['response_note' => 'Otro cierre'])
            ->assertSessionHasErrors('status');
    }

    public function test_response_requirements_cannot_enter_development_flow(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['kind' => 'PROJECT_RESPONSE']);

        $this->actingAs($user)->post(route('requirements.transition', $requirement), ['status' => 'ANALYZING'])
            ->assertSessionHasErrors('status');
        $this->actingAs($user)->get(route('requirements.show', $requirement))
            ->assertOk()->assertSee('WAITING_RESPONSE')->assertDontSee('Crear tarea');
        $this->assertDatabaseHas('requirements', ['id' => $requirement->id, 'status' => 'RECEIVED']);
    }

    public function test_integrations_page_shows_activity_without_exposing_tokens(): void
    {
        $user = User::factory()->create();
        $token = 'dvo_ing_'.str_repeat('a', 64);
        IngestionClient::create(['slug' => 'dot', 'name' => 'Dot', 'token_hash' => hash('sha256', $token)]);
        $requirement = Requirement::factory()->create();
        $requirement->events()->create(['action' => 'requirement.created', 'actor' => 'integration:dot', 'to_status' => 'RECEIVED']);

        $this->get(route('integrations.index'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('integrations.index'))
            ->assertOk()->assertSee('Dot')->assertSee('Emitida')->assertSee('1')
            ->assertDontSee($token)->assertDontSee(hash('sha256', $token));
    }
}
