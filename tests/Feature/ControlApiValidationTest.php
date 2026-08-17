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
}
