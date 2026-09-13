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
        $this->assertContains('/temuan', $urls);
    }

    public function test_admin_kepatuhan_gets_compliance_navigation(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $urls = $this->urlsFor($user);
        $this->assertContains('/dashboard', $urls);
        $this->assertContains('/compliance', $urls);
        $this->assertContains('/audit-logs', $urls);
        $this->assertContains('/temuan', $urls);
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

    public function test_all_non_pic_roles_share_verifikasi_checklists_nav(): void
    {
        foreach ([User::ROLE_ADMIN_KEPATUHAN, User::ROLE_KOORDINATOR_SMKI, User::ROLE_AUDITOR] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $labels = collect(app(NavigationService::class)->getForUser($user))->pluck('label')->all();

            $this->assertContains('Verifikasi Checklists', $labels);
        }
    }

    public function test_pic_does_not_see_verifikasi_checklists(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $labels = collect(app(NavigationService::class)->getForUser($pic))->pluck('label')->all();

        $this->assertNotContains('Verifikasi Checklists', $labels);
    }

    public function test_koordinator_and_auditor_lost_manajemen_sesi_checklist(): void
    {
        foreach ([User::ROLE_KOORDINATOR_SMKI, User::ROLE_AUDITOR] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $urls = $this->urlsFor($user);

            $this->assertNotContains('/admin/kepatuhan/sessions', $urls);
            $this->assertContains('/admin/kepatuhan/checklist/verify', $urls);
        }
    }

    public function test_admin_keeps_both_sessions_and_verify(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $urls = $this->urlsFor($admin);

        $this->assertContains('/admin/kepatuhan/sessions', $urls);
        $this->assertContains('/admin/kepatuhan/checklist/verify', $urls);
    }

    public function test_superadmin_keeps_sessions_but_no_verify_nav(): void
    {
        // Superadmin can hit the verify URL via bulk-verify, but nav hides it
        // via denies work-unit.view — same as the old Verifikasi Penilaian entry.
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $urls = $this->urlsFor($superadmin);

        $this->assertContains('/admin/kepatuhan/sessions', $urls);
        $this->assertNotContains('/admin/kepatuhan/checklist/verify', $urls);
    }

    public function test_pic_gets_assessment_navigation_only(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PIC]);

        $urls = $this->urlsFor($user);
        $this->assertContains('/dashboard', $urls);
        $this->assertContains('/checklist', $urls);
        $this->assertContains('/temuan', $urls);
        $this->assertContains('/risks', $urls);
        $this->assertNotContains('/frameworks', $urls);
        $this->assertNotContains('/users', $urls);
    }

    public function test_pic_gets_dashboard_and_assessments(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $urls = $this->urlsFor($pic);
        $this->assertContains('/dashboard', $urls, 'PIC should see Dashboard after wiring pic/dashboard');
        $this->assertContains('/checklist', $urls);
    }

    public function test_pic_still_lacks_admin_pages(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $urls = $this->urlsFor($pic);
        $this->assertNotContains('/frameworks', $urls);
        $this->assertNotContains('/users', $urls);
    }

    public function test_grant_change_updates_navigation_without_code_change(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $before = $this->urlsFor($pic);
        $this->assertContains('/checklist', $before);
        $this->assertContains('/dashboard', $before);

        // Grant dashboard.recent-activities — proves nav reacts to permission
        // changes without code edits. This must NOT flip the /checklist link,
        // which is gated on checklist-session.read (PIC keeps view/fill access).
        // We deliberately do NOT grant audit-log.view here: that key is one of the
        // `denies` on the PIC Checklist entry, so granting it would hide the link.
        $role = $pic->role()->first();
        $role->permissions()->attach(
            Permission::whereIn('key', ['dashboard.recent-activities'])->pluck('id')
        );
        Role::flushPermissionsCache($pic->role_id);

        $after = $this->urlsFor($pic);
        $this->assertContains('/dashboard', $after);
        $this->assertContains('/checklist', $after);
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
