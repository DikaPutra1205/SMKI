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

        $this->assertSame([
            '/admin/superadmin/dashboard',
            '/admin/superadmin/frameworks',
            '/admin/superadmin/users',
            '/admin/superadmin/roles',
        ], $this->urlsFor($user));
    }

    public function test_admin_kepatuhan_gets_compliance_navigation(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $this->assertSame([
            '/admin/kepatuhan/dashboard',
            '/admin/kepatuhan/compliance',
            '/admin/kepatuhan/checklist/verify',
            '/admin/kepatuhan/checklist/bulk-verify',
        ], $this->urlsFor($user));
    }

    public function test_koordinator_and_auditor_get_only_dashboard(): void
    {
        foreach ([User::ROLE_KOORDINATOR_SMKI, User::ROLE_AUDITOR] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertSame([
                '/admin/superadmin/users',
                '/admin/kepatuhan/dashboard',
            ], $this->urlsFor($user));
        }
    }

    public function test_pic_gets_assessment_navigation_only(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PIC]);

        $this->assertSame(['/admin/pic/assessments'], $this->urlsFor($user));
    }

    public function test_grant_change_updates_navigation_without_code_change(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $this->assertSame(['/admin/pic/assessments'], $this->urlsFor($pic));

        // Grant the two permissions the kepatuhan dashboard entry requires.
        $role = $pic->role()->first();
        $role->permissions()->attach(
            Permission::whereIn('key', ['dashboard.recent-activities', 'audit-log.view'])->pluck('id')
        );
        Role::flushPermissionsCache($pic->role_id);

        $this->assertSame([
            '/admin/kepatuhan/dashboard',
            '/admin/pic/assessments',
        ], $this->urlsFor($pic));
    }
}
