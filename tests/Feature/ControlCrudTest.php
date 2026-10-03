<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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
                'kategori' => 'teknologi',
            ])
            ->assertRedirect('/admin/kepatuhan/compliance');

        $this->assertDatabaseHas('controls', [
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.99.1',
            'judul' => 'Kontrol Baru',
            'kategori' => 'teknologi',
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
                'kategori' => 'teknologi',
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
                'kategori' => 'organisasional',
            ])
            ->assertRedirect('/admin/kepatuhan/compliance');

        $this->assertDatabaseHas('controls', [
            'id' => $control->id,
            'judul' => 'Judul Diubah',
            'kategori' => 'organisasional',
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

    // ── US-M1 role matrix on the web surface ─────────────────────────────────

    /**
     * US-M1 names superadmin first; the parallel admin_kepatuhan case above
     * never proved the superadmin half of the grant.
     */
    public function test_superadmin_can_write_controls_on_the_web_surface(): void
    {
        $framework = $this->framework();
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($superadmin)
            ->from('/admin/kepatuhan/compliance')
            ->post('/admin/kepatuhan/controls', [
                'framework_id' => $framework->id,
                'kode_klausul' => 'SA.1',
                'judul' => 'Kontrol Superadmin',
                'kategori' => 'organisasional',
            ])
            ->assertRedirect('/admin/kepatuhan/compliance');

        $control = Control::query()->where('kode_klausul', 'SA.1')->firstOrFail();

        $this->actingAs($superadmin)
            ->from('/admin/kepatuhan/compliance')
            ->put("/admin/kepatuhan/controls/{$control->id}", ['judul' => 'Diubah Superadmin'])
            ->assertRedirect('/admin/kepatuhan/compliance');

        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Diubah Superadmin']);

        $this->actingAs($superadmin)
            ->from('/admin/kepatuhan/compliance')
            ->delete("/admin/kepatuhan/controls/{$control->id}")
            ->assertRedirect('/admin/kepatuhan/compliance');

        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }

    /**
     * The write matrix for these roles is asserted on the API surface in
     * MasterDataScopeTest; the web verbs were untested, so an authorization
     * regression on Web\ControlController would have shipped silently.
     */
    #[DataProvider('rolesWithoutControlGrants')]
    public function test_roles_without_control_grants_cannot_write_controls_on_the_web_surface(string $role): void
    {
        $framework = $this->framework();
        $control = Control::create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.7.1',
            'judul' => 'Asli',
            'kategori' => 'teknologi',
        ]);
        $actor = User::factory()->create(['role' => $role]);

        $this->actingAs($actor)
            ->post('/admin/kepatuhan/controls', [
                'framework_id' => $framework->id,
                'kode_klausul' => 'X.1',
                'judul' => 'Sisip',
                'kategori' => 'teknologi',
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->put("/admin/kepatuhan/controls/{$control->id}", ['judul' => 'Dibajak'])
            ->assertForbidden();

        $this->actingAs($actor)
            ->delete("/admin/kepatuhan/controls/{$control->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('controls', ['kode_klausul' => 'X.1']);
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Asli']);
        $this->assertNotSoftDeleted('controls', ['id' => $control->id]);
    }

    public static function rolesWithoutControlGrants(): array
    {
        return [
            'koordinator_smki' => [User::ROLE_KOORDINATOR_SMKI],
            'auditor' => [User::ROLE_AUDITOR],
            'pic' => [User::ROLE_PIC],
        ];
    }

    // ── US-M1 uniqueness is per (framework_id, kode_klausul) ────────────────

    public function test_same_kode_klausul_is_allowed_in_a_different_framework(): void
    {
        $admin = $this->makeAdmin();
        $first = $this->framework();
        $second = $this->framework();
        Control::create([
            'framework_id' => $first->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'organisasional',
        ]);

        $this->actingAs($admin)
            ->post('/admin/kepatuhan/controls', [
                'framework_id' => $second->id,
                'kode_klausul' => 'A.5.1',
                'judul' => 'Policies (framework lain)',
                'kategori' => 'organisasional',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('controls', [
            'framework_id' => $second->id,
            'kode_klausul' => 'A.5.1',
        ]);
    }

    public function test_update_rejects_kode_klausul_already_used_in_the_same_framework(): void
    {
        $admin = $this->makeAdmin();
        $framework = $this->framework();
        Control::create([
            'framework_id' => $framework->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'organisasional',
        ]);
        $target = Control::create([
            'framework_id' => $framework->id, 'kode_klausul' => 'A.5.2', 'judul' => 'Roles', 'kategori' => 'organisasional',
        ]);

        $this->actingAs($admin)
            ->put("/admin/kepatuhan/controls/{$target->id}", [
                'kode_klausul' => 'A.5.1',
                'judul' => 'Roles',
                'kategori' => 'organisasional',
            ])
            ->assertSessionHasErrors('kode_klausul');

        $this->assertDatabaseHas('controls', ['id' => $target->id, 'kode_klausul' => 'A.5.2']);
    }

    public function test_update_allows_reusing_its_own_kode_klausul(): void
    {
        $admin = $this->makeAdmin();
        $framework = $this->framework();
        $control = Control::create([
            'framework_id' => $framework->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'organisasional',
        ]);

        $this->actingAs($admin)
            ->from('/admin/kepatuhan/compliance')
            ->put("/admin/kepatuhan/controls/{$control->id}", [
                'kode_klausul' => 'A.5.1',
                'judul' => 'Policies (revisi)',
                'kategori' => 'organisasional',
            ])
            ->assertRedirect('/admin/kepatuhan/compliance')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('controls', [
            'id' => $control->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies (revisi)',
        ]);
    }

    public function test_store_allows_reusing_the_kode_of_a_soft_deleted_control(): void
    {
        $admin = $this->makeAdmin();
        $framework = $this->framework();
        $trashed = Control::create([
            'framework_id' => $framework->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'organisasional',
        ]);
        $trashed->delete();

        $this->actingAs($admin)
            ->post('/admin/kepatuhan/controls', [
                'framework_id' => $framework->id,
                'kode_klausul' => 'A.5.1',
                'judul' => 'Policies (dipakai ulang)',
                'kategori' => 'organisasional',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted('controls', [
            'framework_id' => $framework->id, 'kode_klausul' => 'A.5.1',
        ]);
    }

    // ── US-M1 field contract on update ──────────────────────────────────────

    public function test_update_rejects_unknown_framework_and_out_of_range_enums_without_writing(): void
    {
        $admin = $this->makeAdmin();
        $framework = $this->framework();
        $control = Control::create([
            'framework_id' => $framework->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'organisasional',
        ]);

        $this->actingAs($admin)
            ->put("/admin/kepatuhan/controls/{$control->id}", ['framework_id' => 999999])
            ->assertSessionHasErrors('framework_id');

        $this->actingAs($admin)
            ->put("/admin/kepatuhan/controls/{$control->id}", ['kategori' => 'entah'])
            ->assertSessionHasErrors('kategori');

        $this->actingAs($admin)
            ->put("/admin/kepatuhan/controls/{$control->id}", ['domain_peran' => 'entah'])
            ->assertSessionHasErrors('domain_peran');

        $this->assertDatabaseHas('controls', [
            'id' => $control->id, 'framework_id' => $framework->id, 'kategori' => 'organisasional',
        ]);
        $this->assertNull($control->fresh()->domain_peran);
    }

    public function test_store_and_update_persist_domain_peran(): void
    {
        $admin = $this->makeAdmin();
        $framework = $this->framework();

        $this->actingAs($admin)->post('/admin/kepatuhan/controls', [
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.8.1',
            'judul' => 'Inventory',
            'kategori' => 'teknologi',
            'domain_peran' => 'controller',
        ])->assertSessionHasNoErrors();

        $control = Control::query()->where('kode_klausul', 'A.8.1')->firstOrFail();
        $this->assertSame('controller', $control->domain_peran);

        $this->actingAs($admin)
            ->put("/admin/kepatuhan/controls/{$control->id}", ['domain_peran' => 'processor'])
            ->assertSessionHasNoErrors();

        $this->assertSame('processor', $control->fresh()->domain_peran);
    }

    // ── Edge: unknown record ────────────────────────────────────────────────

    public function test_unknown_control_id_is_404_for_update_and_delete(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->put('/admin/kepatuhan/controls/999999', ['judul' => 'Hantu'])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete('/admin/kepatuhan/controls/999999')
            ->assertNotFound();
    }

    private function framework(): Framework
    {
        return Framework::create([
            'nama' => 'ISO '.fake()->unique()->numerify('###'),
            'versi' => '2022',
        ]);
    }
}
