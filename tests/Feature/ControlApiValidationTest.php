<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlApiValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_store_control_validates_unique_kode_klausul_per_framework(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $framework = Framework::factory()->create();

        Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.5.1',
        ]);

        // Attempt duplicate store -> should return 422, not 500
        $response = $this->actingAs($user)
            ->postJson('/api/controls', [
                'framework_id' => $framework->id,
                'kode_klausul' => 'A.5.1',
                'judul' => 'Duplicated clause',
                'kategori' => 'annex_a',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['kode_klausul']);
    }

    public function test_api_update_control_validates_unique_kode_klausul_per_framework(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $framework = Framework::factory()->create();

        $control1 = Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.5.1',
            'judul' => 'Control 1',
        ]);

        $control2 = Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.5.2',
            'judul' => 'Control 2',
        ]);

        // Update control2 with same kode_klausul as control1 -> should return 422, not 500
        $response = $this->actingAs($user)
            ->patchJson("/api/controls/{$control2->id}", [
                'kode_klausul' => 'A.5.1',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['kode_klausul']);

        // Update control2 keeping its own kode_klausul -> should succeed
        $responseSuccess = $this->actingAs($user)
            ->patchJson("/api/controls/{$control2->id}", [
                'kode_klausul' => 'A.5.2',
                'judul' => 'Control 2 Updated',
            ]);

        $responseSuccess->assertOk();
    }

    public function test_api_update_rejects_move_to_framework_where_kode_already_exists(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $fw1 = Framework::factory()->create();
        $fw2 = Framework::factory()->create();

        $control1 = Control::factory()->create([
            'framework_id' => $fw1->id,
            'kode_klausul' => 'A.5.1',
        ]);

        Control::factory()->create([
            'framework_id' => $fw2->id,
            'kode_klausul' => 'A.5.1',
        ]);

        // Moving control1 into fw2 where 'A.5.1' already exists -> 422
        $response = $this->actingAs($user)
            ->patchJson("/api/controls/{$control1->id}", [
                'framework_id' => $fw2->id,
                'kode_klausul' => 'A.5.1',
                'judul' => 'Moved',
                'kategori' => 'annex_a',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['kode_klausul']);
        $this->assertDatabaseHas('controls', ['id' => $control1->id, 'framework_id' => $fw1->id]);
    }

    // KNOWN BUG: the Form Request unique rule excludes soft-deleted rows
    // (->whereNull('deleted_at')), but the DB constraint uniq_ctrl_fw_kode
    // (migration 2026_08_16_000011) is NOT partial on deleted_at. Reusing a
    // kode after soft-delete passes validation and then 500s on INSERT.
    // Pin the current behaviour; flip to assertCreated() once the constraint
    // becomes a partial unique index.
    public function test_api_recreating_kode_of_soft_deleted_control_hits_db_unique_violation(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $framework = Framework::factory()->create();

        $control = Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.5.1',
        ]);
        $control->delete();

        $response = $this->actingAs($user)
            ->postJson('/api/controls', [
                'framework_id' => $framework->id,
                'kode_klausul' => 'A.5.1',
                'judul' => 'Reused clause',
                'kategori' => 'annex_a',
            ]);

        $response->assertStatus(500);
    }

    public function test_api_store_rejects_empty_framework_id(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $response = $this->actingAs($user)
            ->postJson('/api/controls', [
                'framework_id' => '',
                'kode_klausul' => 'A.5.1',
                'judul' => 'Policies',
                'kategori' => 'annex_a',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['framework_id']);
    }

    public function test_api_update_rejects_empty_framework_id(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $framework = Framework::factory()->create();
        $control = Control::factory()->create(['framework_id' => $framework->id]);

        $response = $this->actingAs($user)
            ->patchJson("/api/controls/{$control->id}", ['framework_id' => '']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['framework_id']);
    }
}
