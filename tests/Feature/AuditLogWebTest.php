<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pic;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $this->auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
    }

    public function test_anonymous_redirected_to_login(): void
    {
        $this->get('/admin/kepatuhan/audit-logs')->assertRedirect(route('login'));
    }

    public function test_admin_can_view_audit_logs_page(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin)->get('/admin/kepatuhan/audit-logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-kepatuhan/audit-logs')
                ->has('logs')
                ->has('stats')
                ->has('actors'));
    }

    public function test_pic_cannot_view_audit_logs(): void
    {
        $this->actingAs($this->pic)->get('/admin/kepatuhan/audit-logs')
            ->assertForbidden();
    }

    public function test_auditor_can_view_audit_logs(): void
    {
        $this->withoutVite();

        $this->actingAs($this->auditor)->get('/admin/kepatuhan/audit-logs')
            ->assertOk();
    }

    public function test_page_accepts_filter_params(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin)->get('/admin/kepatuhan/audit-logs?action=login&start_date=2026-01-01&end_date=2026-12-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.action', 'login')
                ->where('filters.start_date', '2026-01-01')
                ->where('filters.end_date', '2026-12-31'));
    }
}
