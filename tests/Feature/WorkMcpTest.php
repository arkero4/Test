<?php

namespace Tests\Feature;

use App\Models\IngestionClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkMcpTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        $token = 'dvo_ing_'.str_repeat('c', 64);
        IngestionClient::create(['slug' => 'work', 'name' => 'Work', 'token_hash' => hash('sha256', $token)]);
        $this->withToken($token)->withHeader('Accept', 'application/json, text/event-stream');
    }

    private function rpc(string $method, array $params = [], int $id = 1): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) $params];
    }

    private function payload(): array
    {
        return [
            'source' => 'email',
            'external_reference' => 'gmail:account@example.test:message-123',
            'subject' => 'Export column missing',
            'original_content' => 'Please add the missing export column.',
        ];
    }

    private function callTool(array $arguments): array
    {
        return $this->rpc('tools/call', ['name' => 'create_requirement', 'arguments' => (object) $arguments]);
    }

    public function test_authentication_and_discovery_do_not_create_requirements(): void
    {
        $this->postJson('/api/mcp', $this->rpc('ping'))->assertUnauthorized();
        $this->authenticate();
        $this->postJson('/api/mcp', $this->rpc('initialize', ['protocolVersion' => '2025-06-18']))
            ->assertOk()->assertJsonPath('result.protocolVersion', '2025-06-18')
            ->assertJsonPath('result.serverInfo.name', 'dev-orchestrator-work');
        $this->postJson('/api/mcp', $this->rpc('tools/list'))
            ->assertOk()->assertJsonPath('result.tools.0.name', 'create_requirement')
            ->assertJsonCount(1, 'result.tools');
        $this->assertDatabaseCount('requirements', 0);
        IngestionClient::where('slug', 'work')->update(['status' => 'DISABLED']);
        $this->postJson('/api/mcp', $this->rpc('ping'))->assertUnauthorized();
    }

    public function test_mcp_reuses_rest_ingestion_deduplication_and_audit_without_tasks(): void
    {
        $this->authenticate();
        $first = $this->postJson('/api/mcp', $this->callTool($this->payload()))
            ->assertOk()->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.created', true)
            ->assertJsonPath('result.structuredContent.status', 'RECEIVED');
        $id = $first->json('result.structuredContent.id');
        $this->postJson('/api/mcp', $this->callTool($this->payload()))
            ->assertOk()->assertJsonPath('result.structuredContent.created', false)
            ->assertJsonPath('result.structuredContent.id', $id);
        $this->assertDatabaseCount('requirements', 1);
        $this->assertDatabaseCount('requirement_events', 1);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseHas('requirement_events', ['requirement_id' => $id, 'actor' => 'integration:work']);
    }

    public function test_validation_errors_and_unknown_tools_do_not_write(): void
    {
        $this->authenticate();
        $this->postJson('/api/mcp', $this->callTool([]))
            ->assertOk()->assertJsonPath('result.isError', true);
        $this->postJson('/api/mcp', $this->callTool($this->payload() + ['project_slug' => 'does-not-exist']))
            ->assertOk()->assertJsonPath('result.isError', true);
        $this->postJson('/api/mcp', $this->rpc('tools/call', ['name' => 'execute_worker']))
            ->assertOk()->assertJsonPath('error.code', -32602);
        $this->assertDatabaseCount('requirements', 0);
    }

    public function test_notifications_never_execute_tools_and_invalid_protocol_is_rejected(): void
    {
        $this->authenticate();
        $notification = $this->callTool($this->payload());
        unset($notification['id']);
        $this->postJson('/api/mcp', $notification)->assertStatus(202)->assertContent('');
        $this->withHeader('MCP-Protocol-Version', 'not-supported')
            ->postJson('/api/mcp', $this->rpc('ping'))->assertStatus(400);
        $this->assertDatabaseCount('requirements', 0);
    }

    public function test_bad_origin_and_invalid_json_rpc_are_rejected(): void
    {
        $this->authenticate();
        $this->postJson('/api/mcp', [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']])
            ->assertOk()->assertJsonPath('error.code', -32600);
        $this->postJson('/api/mcp', $this->rpc('unknown'))
            ->assertOk()->assertJsonPath('error.code', -32601);
        $this->withHeader('Origin', 'https://untrusted.example.test')
            ->postJson('/api/mcp', $this->rpc('ping'))->assertForbidden();
        $this->assertDatabaseCount('requirements', 0);
    }
}
