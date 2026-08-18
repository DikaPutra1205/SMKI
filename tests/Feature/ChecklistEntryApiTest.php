<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistEntryApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedUnitControlPics(): array
    {
        $unit = WorkUnit::create(['nama' => 'Unit QA']);
        $fw = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a',
        ]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        return compact('unit', 'control', 'pic', 'admin');
    }

    public function test_index_auto_provisions_and_lists(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        fwrite(STDERR, 'PRE index: controls='.Control::count().' frameworks='.Framework::count().' entries_all='.ChecklistEntry::count().' entries_for_unit='.ChecklistEntry::where('unit_id', $unit->id)->count()."\n");
        $this->actingAs($admin)
            ->getJson("/api/checklist-entries?unit_id={$unit->id}")
            ->assertOk();

        $this->assertDatabaseHas('checklist_entries', [
            'unit_id' => $unit->id,
            'control_id' => $control->id,
            'pic_id' => $pic->id,
        ]);
    }

    public function test_index_filters_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}")->assertOk();

        $entry = ChecklistEntry::where('unit_id', $unit->id)->first();
        $entry->update(['status' => ChecklistEntry::STATUS_COMPLIANT]);

        $response = $this->actingAs($admin)
            ->getJson("/api/checklist-entries?unit_id={$unit->id}&status=compliant");

        $response->assertOk();
        // index() returns a LengthAwarePaginator; ApiResponse wraps it so the
        // entries list lives at data.data and the match count at data.total.
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertSame(1, $data['total']);
        $this->assertCount(1, $data['data']);
        $this->assertSame('compliant', $data['data'][0]['status']);
    }

    public function test_index_paginated_structure(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit] = $this->seedUnitControlPics();

        $response = $this->actingAs($admin)
            ->getJson("/api/checklist-entries?unit_id={$unit->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'status', 'message',
            'data' => ['current_page', 'data', 'total', 'per_page', 'last_page'],
        ]);
    }

    public function test_index_all_returns_plain_array(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit] = $this->seedUnitControlPics();

        $response = $this->actingAs($admin)
            ->getJson("/api/checklist-entries?unit_id={$unit->id}&all=true");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('current_page', $data);
        $this->assertNotEmpty($data);
    }

    public function test_index_search_filter(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        ChecklistEntry::where('unit_id', $unit->id)->delete();

        ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/checklist-entries?unit_id={$unit->id}&search=".urlencode($control->kode_klausul));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame(1, $data['total']);
    }

    public function test_index_is_verified_filter(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit] = $this->seedUnitControlPics();
        $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}")->assertOk();
        $verified = ChecklistEntry::where('unit_id', $unit->id)->firstOrFail();
        $verified->update(['tanggal_verifikasi' => now(), 'admin_id' => $admin->id]);

        $response = $this->actingAs($admin)
            ->getJson("/api/checklist-entries?unit_id={$unit->id}&is_verified=true");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame(1, $data['total']);
        $this->assertNotNull($data['data'][0]['tanggal_verifikasi']);
    }

    // Coverage gap: index() keys off the request unit_id param and never scopes
    // by the authenticated user, so any logged-in caller can read any unit's
    // checklist. PASS here proves the absence of tenant scoping (cross-unit leak).
    public function test_index_cross_unit_read_is_not_scoped(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_PIC]);
        ['unit' => $unit] = $this->seedUnitControlPics();

        $response = $this->actingAs($viewer)
            ->getJson("/api/checklist-entries?unit_id={$unit->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total']);
    }

    public function test_store_creates_entry(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        $this->actingAs($admin)
            ->postJson('/api/checklist-entries', [
                'control_id' => $control->id,
                'unit_id' => $unit->id,
                'pic_id' => $pic->id,
                'status' => ChecklistEntry::STATUS_PARTIAL,
                'catatan' => 'Sebagian',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'partial');

        $this->assertDatabaseHas('checklist_entries', ['status' => 'partial', 'unit_id' => $unit->id]);
    }

    public function test_store_rejects_invalid_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        $this->actingAs($admin)
            ->postJson('/api/checklist-entries', [
                'control_id' => $control->id,
                'unit_id' => $unit->id,
                'pic_id' => $pic->id,
                'status' => 'bad',
            ])
            ->assertStatus(422);
    }

    public function test_show_returns_entry_with_evidences(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);
        $entry->evidences()->create([
            'uploaded_by' => $pic->id, 'file_url' => 'bukti/1/x.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson("/api/checklist-entries/{$entry->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['evidences']])
            ->assertJsonPath('data.evidences.0.version_number', 1);
    }

    public function test_update_changes_status_and_clears_verification(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
            'tanggal_verifikasi' => now(), 'admin_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}", [
                'status' => ChecklistEntry::STATUS_COMPLIANT, 'catatan' => 'Fixed',
            ])
            ->assertOk();

        $this->assertDatabaseHas('checklist_entries', [
            'id' => $entry->id, 'status' => 'compliant', 'tanggal_verifikasi' => null,
        ]);
    }

    public function test_verify_sets_admin_and_verification_date(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_PARTIAL,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}/verify", [
                'admin_id' => $admin->id,
                'status' => ChecklistEntry::STATUS_COMPLIANT,
                'catatan_admin' => 'OK',
            ])
            ->assertOk();

        $this->assertDatabaseHas('checklist_entries', [
            'id' => $entry->id, 'admin_id' => $admin->id, 'catatan_admin' => 'OK',
        ]);
        $this->assertNotNull(ChecklistEntry::find($entry->id)->tanggal_verifikasi);
    }

    // D6 — verify endpoint reachable by a pic (no authorization layer). Verify, don't fix.
    public function test_verify_reachable_by_pic_role(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['unit' => $unit, 'control' => $control] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        // pic calls an admin-only action and gets 200, not 403
        $this->actingAs($pic)
            ->patchJson("/api/checklist-entries/{$entry->id}/verify", [
                'admin_id' => $pic->id,
                'status' => ChecklistEntry::STATUS_COMPLIANT,
            ])
            ->assertOk();

        $this->assertNotNull(ChecklistEntry::find($entry->id)->tanggal_verifikasi);
    }

    public function test_destroy_soft_deletes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $this->actingAs($admin)->deleteJson("/api/checklist-entries/{$entry->id}")->assertOk();
        $this->assertSoftDeleted('checklist_entries', ['id' => $entry->id]);
    }

    public function test_restore_by_raw_id(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);
        $entry->delete();

        $this->actingAs($admin)
            ->postJson("/api/checklist-entries/{$entry->id}/restore")
            ->assertOk();

        $this->assertDatabaseHas('checklist_entries', ['id' => $entry->id]);
    }

    // D1 — generate-monthly route was previously a dead 500 (missing method).
    // Now that generateMonthly() exists, the route must return 200.
    public function test_generate_monthly_returns_ok(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control] = $this->seedUnitControlPics();

        $this->actingAs($admin)
            ->postJson('/api/checklist-entries/generate-monthly')
            ->assertOk()
            ->assertJsonStructure(['status', 'message', 'data' => ['created']]);
    }

    // Fix verified: a comment-only PATCH (no status key) must NOT wipe tanggal_verifikasi.
    public function test_update_comment_only_preserves_tanggal_verifikasi(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $verifiedAt = now()->subHour();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'tanggal_verifikasi' => $verifiedAt,
            'admin_id' => $admin->id,
        ]);

        // PATCH only catatan — no status field
        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}", [
                'catatan' => 'Updated catatan only',
            ])
            ->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame('Updated catatan only', $fresh->catatan);
        // tanggal_verifikasi should survive a comment-only edit
        $this->assertNotNull($fresh->tanggal_verifikasi);
    }

    // Edge: update with status same as current should also NOT wipe tanggal_verifikasi.
    public function test_update_same_status_preserves_tanggal_verifikasi(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'tanggal_verifikasi' => now()->subHour(),
            'admin_id' => $admin->id,
        ]);

        // Sending same status as current — verification date must stay
        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}", [
                'status' => ChecklistEntry::STATUS_COMPLIANT,
                'catatan' => 'No change in status',
            ])
            ->assertOk();

        $this->assertNotNull($entry->fresh()->tanggal_verifikasi);
    }

    // Edge: status CHANGE should clear tanggal_verifikasi.
    public function test_update_status_change_clears_tanggal_verifikasi(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'tanggal_verifikasi' => now()->subHour(),
            'admin_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}", [
                'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
            ])
            ->assertOk();

        $this->assertNull($entry->fresh()->tanggal_verifikasi);
    }

    // Edge: empty payload on update should return 200 (no-op), not 500.
    public function test_update_empty_payload_returns_ok(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}", [])
            ->assertOk();
    }

    // Edge: non-existent entry ID should 404, not 500.
    public function test_show_nonexistent_entry_returns_404(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->getJson('/api/checklist-entries/99999')
            ->assertNotFound();
    }
}
