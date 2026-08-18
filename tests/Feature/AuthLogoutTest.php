<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_requires_authentication(): void
    {
        // Guest POSTing to logout should redirect to login
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_post_logout_terminates_session(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret12'),
        ]);

        $this->actingAs($user)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'secret12',
            ]);

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_get_logout_also_terminates_session(): void
    {
        // Route::match(['get','post']) — GET should also work per commit c912aa5
        $user = User::factory()->create([
            'password' => bcrypt('secret12'),
        ]);

        $this->actingAs($user)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'secret12',
            ]);

        $this->assertAuthenticatedAs($user);

        $this->get('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_logout_invalidates_session_token(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret12'),
        ]);

        $this->actingAs($user)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'secret12',
            ]);

        $session = $this->app['session']->all();

        $this->post('/logout')
            ->assertRedirect('/login');

        // Session should be regenerated — old token invalid
        $this->assertGuest();
        $this->assertNotEquals($session['_token'] ?? null, $this->app['session']->token() ?? null);
    }

    public function test_guest_get_logout_redirects_to_login(): void
    {
        $this->get('/logout')->assertRedirect('/login');
    }

    public function test_logout_regenerates_session_id(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret12'),
        ]);

        $this->actingAs($user)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'secret12',
            ]);
        $before = $this->app['session']->getId();

        $this->post('/logout')
            ->assertRedirect('/login');

        $this->assertNotEquals($before, $this->app['session']->getId());
    }

    public function test_web_logout_also_cuts_api_session_access(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret12'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret12',
        ]);
        $this->getJson('/api/users')->assertOk();

        $this->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->getJson('/api/users')->assertStatus(401);
    }
}
