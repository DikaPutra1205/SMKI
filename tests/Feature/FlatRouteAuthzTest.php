<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class FlatRouteAuthzTest extends TestCase
{
    public function test_dashboard_flat_route_renders_for_each_authorized_role(): void
    {
        foreach ([User::ROLE_SUPERADMIN, User::ROLE_ADMIN_KEPATUHAN, User::ROLE_AUDITOR, User::ROLE_PIC] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/dashboard')->assertOk();
        }
    }

    public function test_unauthorized_role_cannot_open_restricted_flat_page(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $this->actingAs($pic)->get('/frameworks')->assertForbidden();
    }

    public function test_notifications_flat_route_renders_for_every_authenticated_role(): void
    {
        foreach ([
            User::ROLE_SUPERADMIN,
            User::ROLE_ADMIN_KEPATUHAN,
            User::ROLE_KOORDINATOR_SMKI,
            User::ROLE_AUDITOR,
            User::ROLE_PIC,
        ] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/notifications')->assertOk();
        }
    }

    public function test_notifications_flat_route_requires_authentication(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_login_redirect_uses_role_id_dispatcher(): void
    {
        $pic = User::factory()->create(['email' => 'pic@smki.test', 'password' => bcrypt('secret12'), 'role' => User::ROLE_PIC]);
        $this->post('/login', ['email' => 'pic@smki.test', 'password' => 'secret12'])->assertRedirect('/dashboard');

        $this->post('/logout');

        $sa = User::factory()->create(['email' => 'sa@smki.test', 'password' => bcrypt('secret12'), 'role' => User::ROLE_SUPERADMIN]);
        $this->post('/login', ['email' => 'sa@smki.test', 'password' => 'secret12'])->assertRedirect('/dashboard');
    }

    public function test_root_redirect_uses_role_id_dispatcher(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $this->actingAs($pic)->get('/')->assertRedirect('/dashboard');

        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->actingAs($sa)->get('/')->assertRedirect('/dashboard');
    }
}
