<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_api_access_is_blocked(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
    }

    public function test_login_and_forgot_password_routes_are_public(): void
    {
        $this->get('/login')->assertStatus(200);
        $this->get('/forgot-password')->assertStatus(200);
        $this->post('/forgot-password', ['email' => 'x@y.z'])->assertStatus(200);
    }

    public function test_anonymous_web_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_authenticated_user_reaches_protected_api(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret12')]);

        $this->actingAs($user)->getJson('/api/users')->assertOk();
    }

    public function test_login_accepts_valid_credentials_and_starts_session(): void
    {
        $user = User::factory()->create([
            'email' => 'pic@smki.test',
            'password' => bcrypt('secret12'),
        ]);

        $this->post('/login', [
            'email' => 'pic@smki.test',
            'password' => 'secret12',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'pic@smki.test',
            'password' => bcrypt('secret12'),
        ]);

        $this->post('/login', [
            'email' => 'pic@smki.test',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
