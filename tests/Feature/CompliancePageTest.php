<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompliancePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_can_view_compliance_page_with_database_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $response = $this->actingAs($user)
            ->get('/admin/kepatuhan/compliance');

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin-kepatuhan/compliance', false)
            ->has('frameworks')
            ->has('controls')
            ->has('workUnits')
        );
    }
}
