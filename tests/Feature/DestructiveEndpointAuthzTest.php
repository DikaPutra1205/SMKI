<?php

namespace Tests\Feature;

use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the top destructive/import endpoints that were reachable by any
 * logged-in role before the Gate::authorize() additions.
 */
class DestructiveEndpointAuthzTest extends TestCase
{
    use RefreshDatabase;

    private function units(): array
    {
        return [
            'own' => WorkUnit::create(['nama' => 'Unit Sendiri']),
            'other' => WorkUnit::create(['nama' => 'Unit Lain']),
        ];
    }

    // ── control.delete ────────────────────────────────────────────────────────

    public function test_admin_can_delete_control(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $control = Control::create([
            'framework_id' => Framework::create(['nama' => 'F', 'versi' => '1'])->id,
            'kode_klausul' => 'A.1', 'judul' => 'T', 'kategori' => 'c',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/controls/{$control->id}")
            ->assertOk();

        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }

    public function test_pic_cannot_delete_control(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $control = Control::create([
            'framework_id' => Framework::create(['nama' => 'F', 'versi' => '1'])->id,
            'kode_klausul' => 'A.1', 'judul' => 'T', 'kategori' => 'c',
        ]);

        $this->actingAs($pic)
            ->deleteJson("/api/controls/{$control->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('controls', ['id' => $control->id]);
    }

    // ── framework.delete ────────────────────────────────────────────────────────

    public function test_admin_can_delete_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $fw = Framework::create(['nama' => 'ISO', 'versi' => '1']);

        $this->actingAs($admin)
            ->deleteJson("/api/frameworks/{$fw->id}")
            ->assertOk();

        $this->assertSoftDeleted('frameworks', ['id' => $fw->id]);
    }

    public function test_pic_cannot_delete_framework(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $fw = Framework::create(['nama' => 'ISO', 'versi' => '1']);

        $this->actingAs($pic)
            ->deleteJson("/api/frameworks/{$fw->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('frameworks', ['id' => $fw->id]);
    }

    // ── checklist-session.update: role gate + cross-unit tamper guard ─────────────

    public function test_owner_unit_can_update_session(): void
    {
        $units = $this->units();
        $user = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $units['own']->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'k', 'unit_id' => $units['own']->id, 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/checklist-sessions/{$session->id}", ['konteks_penilaian' => 'updated'])
            ->assertOk();

        $this->assertDatabaseHas('checklist_sessions', ['id' => $session->id, 'konteks_penilaian' => 'updated']);
    }

    public function test_other_unit_user_cannot_update_session(): void
    {
        $units = $this->units();
        $owner = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $units['own']->id]);
        $intruder = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $units['other']->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'k', 'unit_id' => $units['own']->id, 'created_by' => $owner->id, 'updated_by' => $owner->id,
        ]);

        $this->actingAs($intruder)
            ->patchJson("/api/checklist-sessions/{$session->id}", ['konteks_penilaian' => 'hacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('checklist_sessions', ['id' => $session->id, 'konteks_penilaian' => 'k']);
    }

    public function test_unauthorized_role_cannot_update_session(): void
    {
        $units = $this->units();
        // auditor has checklist-session.read but NOT .update
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR, 'unit_id' => $units['own']->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'k', 'unit_id' => $units['own']->id, 'created_by' => $auditor->id, 'updated_by' => $auditor->id,
        ]);

        $this->actingAs($auditor)
            ->patchJson("/api/checklist-sessions/{$session->id}", ['konteks_penilaian' => 'hacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('checklist_sessions', ['id' => $session->id, 'konteks_penilaian' => 'k']);
    }
}
