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

    public function test_administrator_can_sign_in_with_email_in_any_case(): void
    {
        User::factory()->create(['email' => 'Jaime.Fuentes@tecnich.cl', 'password' => 'a-valid-password']);

        $this->post('/login', ['email' => 'jaime.fuentes@TECNICH.CL', 'password' => 'a-valid-password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }
}
