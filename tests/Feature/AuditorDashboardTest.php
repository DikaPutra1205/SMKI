<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditorDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC]);
    }

    public function test_anonymous_redirected_to_login(): void
    {
        $this->get('/admin/auditor/dashboard')->assertRedirect(route('login'));
    }

    public function test_auditor_can_view_dashboard(): void
    {
        $this->withoutVite();

        $this->actingAs($this->auditor)->get('/admin/auditor/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auditor/dashboard')
                ->has('summary')
                ->has('trends')
                ->has('workUnits'));
    }

    public function test_pic_can_view_auditor_dashboard(): void
    {
        // GAP: AuditorDashboardController has no authz — any authenticated user can access
        $this->actingAs($this->pic)->get('/admin/auditor/dashboard')
            ->assertOk();
    }

    public function test_flat_dashboard_route_renders_for_auditor(): void
    {
        $this->withoutVite();

        $this->actingAs($this->auditor)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auditor/dashboard'));
    }

    public function test_page_accepts_filter_params(): void
    {
        $this->withoutVite();

        $this->actingAs($this->auditor)->get('/admin/auditor/dashboard?months=6')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.months', '6'));
    }
}
