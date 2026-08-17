<?php

namespace Tests\Feature;

use App\Models\Framework;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrameworkCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
    }

    public function test_superadmin_can_view_frameworks_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();

        $this->actingAs($user)
            ->get('/admin/superadmin/frameworks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('superadmin/frameworks', false));
    }

    public function test_superadmin_can_create_framework(): void
    {
        $user = $this->makeAdmin();

        $this->actingAs($user)
            ->from('/admin/superadmin/frameworks')
            ->post('/admin/superadmin/frameworks', [
                'nama' => 'ISO 27001',
                'versi' => '2022',
                'url_file' => 'https://example.com/iso27001.pdf',
            ])
            ->assertRedirect('/admin/superadmin/frameworks')
            ->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseHas('frameworks', [
            'nama' => 'ISO 27001',
            'versi' => '2022',
            'url_file' => 'https://example.com/iso27001.pdf',
        ]);
    }

    public function test_creating_framework_requires_valid_fields(): void
    {
        $user = $this->makeAdmin();

        $this->actingAs($user)
            ->post('/admin/superadmin/frameworks', [
                'nama' => '',
                'versi' => '',
            ])
            ->assertSessionHasErrors(['nama', 'versi']);
    }

    public function test_creating_duplicate_framework_name_version_is_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();
        $existing = Framework::query()->firstOrFail();

        $this->actingAs($user)
            ->post('/admin/superadmin/frameworks', [
                'nama' => $existing->nama,
                'versi' => $existing->versi,
            ])
            ->assertSessionHasErrors(['nama']);
    }

    public function test_superadmin_can_update_framework(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();
        $framework = Framework::query()->firstOrFail();

        $this->actingAs($user)
            ->from('/admin/superadmin/frameworks')
            ->patch("/admin/superadmin/frameworks/{$framework->id}", [
                'nama' => 'ISO 27001 Updated',
                'versi' => '2025',
            ])
            ->assertRedirect('/admin/superadmin/frameworks')
            ->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseHas('frameworks', [
            'id' => $framework->id,
            'nama' => 'ISO 27001 Updated',
            'versi' => '2025',
        ]);
    }

    public function test_superadmin_can_delete_framework(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();
        $framework = Framework::query()->firstOrFail();

        $this->actingAs($user)
            ->from('/admin/superadmin/frameworks')
            ->delete("/admin/superadmin/frameworks/{$framework->id}")
            ->assertRedirect('/admin/superadmin/frameworks')
            ->assertSessionHas('flash.type', 'success');

        $this->assertSoftDeleted('frameworks', ['id' => $framework->id]);
    }

    public function test_guest_cannot_access_framework_crud_routes(): void
    {
        $this->post('/admin/superadmin/frameworks')->assertRedirect('/login');
        $this->patch('/admin/superadmin/frameworks/1')->assertRedirect('/login');
        $this->delete('/admin/superadmin/frameworks/1')->assertRedirect('/login');
    }
}
