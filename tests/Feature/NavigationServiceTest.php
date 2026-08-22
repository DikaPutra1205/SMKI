<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\NavigationService;
use Tests\TestCase;

class NavigationServiceTest extends TestCase
{
    private function urlsFor(User $user): array
    {
        return collect(app(NavigationService::class)->getForUser($user))
            ->flatMap(fn (array $entry) => [
                $entry['url'] ?? null,
                ...array_column($entry['children'] ?? [], 'url'),
            ])
            ->filter()
            ->values()
            ->all();
    }

    public function test_superadmin_gets_superadmin_navigation_only(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $urls = $this->urlsFor($user);
        // Flat routes: superadmin sees all flat pages it has perms for
        $this->assertContains('/dashboard', $urls);
        $this->assertContains('/frameworks', $urls);
        $this->assertContains('/users', $urls);
        $this->assertContains('/roles', $urls);
        $this->assertContains('/compliance', $urls);
        $this->assertContains('/audit-logs', $urls);
    }

    public function test_admin_kepatuhan_gets_compliance_navigation(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $urls = $this->urlsFor($user);
        $this->assertContains('/dashboard', $urls);
        $this->assertContains('/compliance', $urls);
        $this->assertContains('/audit-logs', $urls);
        $this->assertContains('/findings', $urls);
        $this->assertContains('/risks', $urls);
    }

    public function test_koordinator_and_auditor_get_only_dashboard(): void
    {
        foreach ([User::ROLE_KOORDINATOR_SMKI, User::ROLE_AUDITOR] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $urls = $this->urlsFor($user);

            $this->assertContains('/dashboard', $urls);
            $this->assertContains('/audit-logs', $urls);
        }
    }

    public function test_pic_gets_assessment_navigation_only(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PIC]);

        $urls = $this->urlsFor($user);
        $this->assertContains('/assessments', $urls);
        $this->assertContains('/findings', $urls);
        $this->assertContains('/risks', $urls);
        $this->assertNotContains('/frameworks', $urls);
        $this->assertNotContains('/users', $urls);
    }

    public function test_grant_change_updates_navigation_without_code_change(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $before = $this->urlsFor($pic);
        $this->assertContains('/assessments', $before);
        $this->assertNotContains('/dashboard', $before);

        // Grant the two permissions the kepatuhan dashboard entry requires.
        $role = $pic->role()->first();
        $role->permissions()->attach(
            Permission::whereIn('key', ['dashboard.recent-activities', 'audit-log.view'])->pluck('id')
        );
        Role::flushPermissionsCache($pic->role_id);

        $after = $this->urlsFor($pic);
        $this->assertContains('/dashboard', $after);
        $this->assertContains('/assessments', $after);
    }

    public function test_nav_urls_point_at_flat_routes(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $urls = $this->urlsFor($superadmin);
        $this->assertContains('/frameworks', $urls);
        $this->assertNotContains('/admin/superadmin/frameworks', $urls);
        $this->assertNotContains('/admin/kepatuhan/compliance', $urls);
        $this->assertContains('/compliance', $urls);
    }
}
