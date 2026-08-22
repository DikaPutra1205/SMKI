<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class PicDashboardTest extends TestCase
{
    public function test_pic_can_view_own_dashboard_via_admin_and_flat_routes(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $this->actingAs($pic)->get('/admin/pic/dashboard')->assertOk()->assertInertia(fn ($p) => $p->component('pic/dashboard'));
        $this->actingAs($pic)->get('/dashboard')->assertOk()->assertInertia(fn ($p) => $p->component('pic/dashboard'));
    }

    public function test_dashboard_requires_dashboard_read(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $role = $pic->role()->first();
        $role->permissions()->detach(Permission::where('key', 'dashboard.read')->first()->id);
        Role::flushPermissionsCache($pic->role_id);
        $this->actingAs($pic)->get('/admin/pic/dashboard')->assertForbidden();
    }

    public function test_non_pic_cannot_use_pic_admin_route(): void
    {
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $this->actingAs($auditor)->get('/admin/pic/dashboard')->assertForbidden();
    }

    public function test_summary_props_shape_and_scoping(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $this->actingAs($pic)->get('/admin/pic/dashboard')->assertInertia(fn ($p) => $p->has('summary')->has('recent_sessions'));
    }

    public function test_root_redirect_for_pic_goes_to_dashboard(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $this->actingAs($pic)->get('/')->assertRedirect('/dashboard');
    }
}
