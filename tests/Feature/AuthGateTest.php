<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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
        ])->assertRedirect('/admin/pic/assessments');

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

    public function test_login_rejects_missing_fields(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_login_rejects_malformed_email(): void
    {
        $this->post('/login', ['email' => 'not-an-email', 'password' => 'secret12'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_redirects_superadmin_to_superadmin_dashboard(): void
    {
        User::factory()->create([
            'email' => 'sa@smki.test',
            'role' => User::ROLE_SUPERADMIN,
            'password' => bcrypt('secret12'),
        ]);

        $this->post('/login', ['email' => 'sa@smki.test', 'password' => 'secret12'])
            ->assertRedirect('/admin/superadmin/dashboard');
    }

    public function test_login_redirects_other_roles_to_kepatuhan_dashboard(): void
    {
        foreach ([User::ROLE_ADMIN_KEPATUHAN, User::ROLE_KOORDINATOR_SMKI, User::ROLE_AUDITOR] as $role) {
            $email = "{$role}@smki.test";
            $user = User::factory()->create([
                'email' => $email,
                'role' => $role,
                'password' => bcrypt('secret12'),
            ]);

            $this->post('/login', ['email' => $email, 'password' => 'secret12'])
                ->assertRedirect('/admin/kepatuhan/dashboard');
            $this->assertAuthenticatedAs($user);

            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_login_regenerates_session_id(): void
    {
        User::factory()->create([
            'email' => 'pic@smki.test',
            'password' => bcrypt('secret12'),
        ]);

        $this->get('/login');
        $before = $this->app['session']->getId();

        $this->post('/login', ['email' => 'pic@smki.test', 'password' => 'secret12'])
            ->assertRedirect('/admin/pic/assessments');

        $this->assertNotEquals($before, $this->app['session']->getId());
    }

    public function test_login_honors_intended_destination(): void
    {
        User::factory()->create([
            'email' => 'sa@smki.test',
            'role' => User::ROLE_SUPERADMIN,
            'password' => bcrypt('secret12'),
        ]);

        $this->get('/admin/pic/assessments')->assertRedirect('/login');

        $this->post('/login', ['email' => 'sa@smki.test', 'password' => 'secret12'])
            ->assertRedirect('/admin/pic/assessments');
    }

    public function test_authenticated_user_hitting_login_is_redirected_to_root(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PIC]);

        $this->actingAs($user)->get('/login')->assertRedirect('/');
    }

    public function test_anonymous_post_to_data_route_is_blocked(): void
    {
        $this->postJson('/api/checklist-sessions', ['nama' => 'x'])->assertStatus(401);
    }

    // ── API auth closures (routes/api.php) ─────────────────────────────────────

    public function test_api_login_starts_session_and_opens_protected_routes(): void
    {
        $user = User::factory()->create([
            'email' => 'pic@smki.test',
            'role' => User::ROLE_PIC,
            'password' => bcrypt('secret12'),
        ]);

        $this->postJson('/api/v1/login', ['email' => 'pic@smki.test', 'password' => 'secret12'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'user' => ['id', 'name', 'email', 'role', 'unit_id']]);

        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/users')->assertOk();
    }

    public function test_api_login_rejects_wrong_credentials_with_401(): void
    {
        User::factory()->create(['email' => 'pic@smki.test', 'password' => bcrypt('secret12')]);

        $this->postJson('/api/v1/login', ['email' => 'pic@smki.test', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('status', 'error');

        $this->assertGuest();
    }

    public function test_api_login_validates_input(): void
    {
        $this->postJson('/api/v1/login', [])->assertStatus(422);
        $this->postJson('/api/v1/login', ['email' => 'not-an-email', 'password' => 'x'])->assertStatus(422);
    }

    public function test_api_logout_terminates_session_and_blocks_protected_routes(): void
    {
        User::factory()->create(['email' => 'pic@smki.test', 'password' => bcrypt('secret12')]);

        $this->postJson('/api/v1/login', ['email' => 'pic@smki.test', 'password' => 'secret12'])->assertOk();
        $this->getJson('/api/users')->assertOk();

        $this->postJson('/api/v1/logout')->assertOk()->assertJsonPath('status', 'success');
        $this->assertGuest();
        $this->getJson('/api/users')->assertStatus(401);
    }

    public function test_api_logout_guest_returns_success(): void
    {
        $this->postJson('/api/v1/logout')->assertOk()->assertJsonPath('status', 'success');
    }

    // ── RBAC Gates (app/Providers/AppServiceProvider.php) ─────────────────────

    public function test_view_audit_logs_and_export_reports_gate_role_matrix(): void
    {
        $allowed = [
            User::factory()->create(['role' => User::ROLE_SUPERADMIN]),
            User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]),
            User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]),
            User::factory()->create(['role' => User::ROLE_AUDITOR]),
        ];
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);

        foreach ($allowed as $user) {
            $this->assertTrue(Gate::forUser($user)->allows('view-audit-logs'));
            $this->assertTrue(Gate::forUser($user)->allows('export-reports'));
        }

        $this->assertTrue(Gate::forUser($pic)->denies('view-audit-logs'));
        $this->assertTrue(Gate::forUser($pic)->denies('export-reports'));
    }

    public function test_manage_compliance_gate_allows_only_compliance_roles(): void
    {
        $allowed = [
            User::factory()->create(['role' => User::ROLE_SUPERADMIN]),
            User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]),
        ];
        $denied = [
            User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]),
            User::factory()->create(['role' => User::ROLE_AUDITOR]),
            User::factory()->create(['role' => User::ROLE_PIC]),
        ];

        foreach ($allowed as $user) {
            $this->assertTrue(Gate::forUser($user)->allows('manage-compliance'));
        }
        foreach ($denied as $user) {
            $this->assertTrue(Gate::forUser($user)->denies('manage-compliance'));
        }
    }

    public function test_user_role_helper_methods(): void
    {
        $roles = [
            [User::ROLE_SUPERADMIN, 'isSuperAdmin'],
            [User::ROLE_ADMIN_KEPATUHAN, 'isAdmin'],
            [User::ROLE_KOORDINATOR_SMKI, 'isKoordinator'],
            [User::ROLE_AUDITOR, 'isAuditor'],
            [User::ROLE_PIC, 'isPic'],
        ];

        foreach ($roles as [$role, $method]) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue($user->{$method}());

            foreach ($roles as [$other, $otherMethod]) {
                if ($method !== $otherMethod) {
                    $this->assertFalse($user->{$otherMethod}());
                }
            }
        }
    }
}
