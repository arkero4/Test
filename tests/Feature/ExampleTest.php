<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_and_administrator_sees_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee('Centro de operaciones');
    }
}
