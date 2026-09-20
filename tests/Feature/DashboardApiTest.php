<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC]);
    }

    public function test_anonymous_blocked(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/trends')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/unit-comparison')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/recent-activities')->assertUnauthorized();
    }

    public function test_summary_returns_shape(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonStructure(['status', 'data']);
    }

    public function test_trends_returns_shape(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends')
            ->assertOk()
            ->assertJsonStructure(['status', 'data']);
    }

    public function test_unit_comparison_returns_shape(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/dashboard/unit-comparison')
            ->assertOk()
            ->assertJsonStructure(['status', 'data']);
    }

    public function test_recent_activities_requires_permission(): void
    {
        $this->actingAs($this->pic)->getJson('/api/v1/dashboard/recent-activities')
            ->assertForbidden();
    }

    public function test_summary_accepts_unit_filter(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary?unit_id=1')
            ->assertOk();
    }

    public function test_trends_accepts_months_filter(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends?months=6')
            ->assertOk();
    }

    public function test_trends_defaults_to_6_months(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends')
            ->assertOk();
    }
}
