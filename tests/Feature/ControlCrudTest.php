<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
    }

    public function test_admin_can_create_control(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();
        $framework = Framework::query()->firstOrFail();

        $this->actingAs($user)
            ->from('/admin/kepatuhan/compliance')
            ->post('/admin/kepatuhan/controls', [
                'framework_id' => $framework->id,
                'kode_klausul' => 'A.99.1',
                'judul' => 'Kontrol Baru',
                'deskripsi' => 'Deskripsi kontrol baru',
                'kategori' => 'annex_a',
            ])
            ->assertRedirect('/admin/kepatuhan/compliance');

        $this->assertDatabaseHas('controls', [
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.99.1',
            'judul' => 'Kontrol Baru',
            'kategori' => 'annex_a',
        ]);
    }

    public function test_creating_control_requires_valid_fields(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();

        $this->actingAs($user)
            ->post('/admin/kepatuhan/controls', [
                'kode_klausul' => '',
                'judul' => '',
            ])
            ->assertSessionHasErrors(['framework_id', 'kode_klausul', 'judul', 'kategori']);
    }

    public function test_creating_duplicate_kode_klausul_within_framework_is_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();
        $existing = Control::query()->firstOrFail();

        $this->actingAs($user)
            ->post('/admin/kepatuhan/controls', [
                'framework_id' => $existing->framework_id,
                'kode_klausul' => $existing->kode_klausul,
                'judul' => 'Kode Duplikat',
                'kategori' => 'annex_a',
            ])
            ->assertSessionHasErrors('kode_klausul');
    }

    public function test_admin_can_update_control(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();
        $control = Control::query()->firstOrFail();

        $this->actingAs($user)
            ->from('/admin/kepatuhan/compliance')
            ->put("/admin/kepatuhan/controls/{$control->id}", [
                'kode_klausul' => $control->kode_klausul,
                'judul' => 'Judul Diubah',
                'kategori' => 'klausul_4_10',
            ])
            ->assertRedirect('/admin/kepatuhan/compliance');

        $this->assertDatabaseHas('controls', [
            'id' => $control->id,
            'judul' => 'Judul Diubah',
            'kategori' => 'klausul_4_10',
        ]);
    }

    public function test_admin_can_move_control_to_another_framework_on_update(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();
        $control = Control::query()->firstOrFail();
        $otherFramework = Framework::query()->where('id', '!=', $control->framework_id)->firstOrFail();

        $this->actingAs($user)
            ->put("/admin/kepatuhan/controls/{$control->id}", [
                'framework_id' => $otherFramework->id,
                'kode_klausul' => $control->kode_klausul,
                'judul' => $control->judul,
                'kategori' => $control->kategori,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('controls', [
            'id' => $control->id,
            'framework_id' => $otherFramework->id,
        ]);
    }

    public function test_admin_can_delete_control(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->makeAdmin();
        $control = Control::query()->firstOrFail();

        $this->actingAs($user)
            ->from('/admin/kepatuhan/compliance')
            ->delete("/admin/kepatuhan/controls/{$control->id}")
            ->assertRedirect('/admin/kepatuhan/compliance');

        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }

    public function test_guest_cannot_access_control_crud_routes(): void
    {
        // Inertia routes redirect unauthenticated to login
        $this->post('/admin/kepatuhan/controls')
            ->assertRedirect('/login');
        $this->put('/admin/kepatuhan/controls/1')
            ->assertRedirect('/login');
        $this->delete('/admin/kepatuhan/controls/1')
            ->assertRedirect('/login');
    }
}
