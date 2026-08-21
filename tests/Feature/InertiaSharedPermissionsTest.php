<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaSharedPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function sharedPermissions(User $user, string $url): array
    {
        $response = $this->actingAs($user)->get($url);
        $response->assertOk();

        $page = $response->viewData('page');

        return $page['props']['auth']['permissions'] ?? [];
    }

    public function test_auth_permissions_are_shared_and_differ_per_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $adminPerms = $this->sharedPermissions($admin, '/admin/kepatuhan/compliance');

        $this->assertContains('control.create', $adminPerms);
        $this->assertContains('control.update', $adminPerms);
        $this->assertContains('control.delete', $adminPerms);
        $this->assertContains('checklist.bulk-verify', $adminPerms);

        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $auditorPerms = $this->sharedPermissions($auditor, '/admin/kepatuhan/compliance');

        $this->assertNotContains('control.create', $auditorPerms);
        $this->assertNotContains('control.update', $auditorPerms);
        $this->assertNotContains('control.delete', $auditorPerms);
        $this->assertNotContains('checklist.bulk-verify', $auditorPerms);

        $this->assertContains('finding.view', $auditorPerms);
        $this->assertContains('risk.view', $auditorPerms);
    }

    public function test_superadmin_receives_full_permission_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);

        $super = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $perms = $this->sharedPermissions($super, '/admin/superadmin/frameworks');

        foreach (['framework.create', 'user.create', 'role.delete', 'checklist.bulk-verify', 'control.delete'] as $key) {
            $this->assertContains($key, $perms);
        }
    }

    public function test_guest_shares_empty_permissions(): void
    {
        $response = $this->get('/login');
        $response->assertOk();

        $page = $response->viewData('page');

        $this->assertSame([], $page['props']['auth']['permissions']);
    }
}
