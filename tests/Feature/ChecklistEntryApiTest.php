<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    public function test_index_cross_unit_read_is_scoped(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_PIC]);
        ['unit' => $unit] = $this->seedUnitControlPics();

        $response = $this->actingAs($viewer)
            ->getJson("/api/checklist-entries?unit_id={$unit->id}");

        $response->assertForbidden();
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
        ['unit' => $unit, 'control' => $control] = $this->seedUnitControlPics();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
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

    // Period bug: konteks_penilaian month must follow the requested periode, not now().
    public function test_generate_monthly_uses_requested_periode_month(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit] = $this->seedUnitControlPics();

        $this->actingAs($admin)
            ->postJson('/api/checklist-entries/generate-monthly', ['periode' => '2026-03'])
            ->assertOk();

        $session = ChecklistSession::where('unit_id', $unit->id)
            ->where('periode', '2026-03')
            ->firstOrFail();

        $this->assertSame('2026-03', $session->periode);
        $this->assertStringContainsString('Maret 2026', $session->konteks_penilaian);
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

    public function test_index_filters_by_session_control_framework_kategori(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $unit = WorkUnit::create(['nama' => 'Unit QA 2']);
        $fw1 = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $fw2 = Framework::create(['nama' => 'NIST CSF', 'versi' => '2.0']);
        $ctrl1 = $fw1->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
        $ctrl2 = $fw1->controls()->create(['kode_klausul' => '4.1', 'judul' => 'Context', 'kategori' => 'clauses']);
        $ctrl3 = $fw2->controls()->create(['kode_klausul' => 'PR.AC-1', 'judul' => 'Identities', 'kategori' => 'annex_a']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $session = ChecklistSession::create(['konteks_penilaian' => 'Sesi filter', 'unit_id' => $unit->id, 'framework_id' => $fw1->id]);
        foreach ([$ctrl1, $ctrl2, $ctrl3] as $ctrl) {
            ChecklistEntry::create(['session_id' => $session->id, 'control_id' => $ctrl->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id]);
        }
        $otherSession = ChecklistSession::create(['konteks_penilaian' => 'Sesi kedua', 'unit_id' => $unit->id, 'framework_id' => $fw2->id]);
        ChecklistEntry::create(['session_id' => $otherSession->id, 'control_id' => $ctrl3->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id]);

        // pre-seeded entries for the unit suppress auto-provisioning
        $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}")->assertOk();

        $resSession = $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}&session_id={$session->id}");
        $this->assertSame(3, $resSession->json('data.total'));

        $resControl = $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}&control_id={$ctrl1->id}");
        $this->assertSame(1, $resControl->json('data.total'));

        $resFw = $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}&framework_id={$fw1->id}");
        $this->assertSame(2, $resFw->json('data.total'));

        $resKategori = $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}&kategori=clauses");
        $this->assertSame(1, $resKategori->json('data.total'));
    }

    public function test_index_filters_by_bulan_tahun(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT, 'tanggal_input' => '2026-03-10 09:00:00',
        ]);
        ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT, 'tanggal_input' => '2026-08-10 09:00:00',
        ]);

        $res = $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}&bulan=3&tahun=2026");
        $res->assertOk();
        $this->assertSame(1, $res->json('data.total'));

        $resAll = $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}");
        $this->assertSame(2, $resAll->json('data.total'));
    }

    public function test_index_trashed_filter(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);
        $entry->delete();

        // default scope excludes trashed; index() auto-provisions a fresh entry for the unit
        $default = $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}");
        $default->assertOk();
        $this->assertSame(1, $default->json('data.total'));
        $this->assertNotSame($entry->id, $default->json('data.data.0.id'));

        $trashed = $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}&trashed=only");
        $trashed->assertOk();
        $this->assertSame(1, $trashed->json('data.total'));
        $this->assertSame($entry->id, $trashed->json('data.data.0.id'));

        $with = $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}&trashed=with");
        $this->assertSame(2, $with->json('data.total'));
    }

    public function test_show_returns_404_for_soft_deleted_entry(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
        ]);
        $entry->delete();

        $this->actingAs($admin)->getJson("/api/checklist-entries/{$entry->id}")->assertStatus(404);
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        $this->actingAs($admin)->postJson('/api/checklist-entries', [
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['control_id']);

        $this->actingAs($admin)->postJson('/api/checklist-entries', [
            'control_id' => $control->id, 'pic_id' => $pic->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['unit_id']);

        $this->actingAs($admin)->postJson('/api/checklist-entries', [
            'control_id' => $control->id, 'unit_id' => $unit->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['pic_id']);

        $this->actingAs($admin)->postJson('/api/checklist-entries', [
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => 'compliant', 'session_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors(['session_id']);

        $this->assertSame(0, ChecklistEntry::count());
    }

    // API update() preserves tanggal_verifikasi on catatan-only edit (our verified fix)
    public function test_update_catatan_only_preserves_verification(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $verifiedAt = now()->subHour();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'admin_id' => $admin->id, 'tanggal_verifikasi' => $verifiedAt,
        ]);

        $this->actingAs($pic)
            ->patchJson("/api/checklist-entries/{$entry->id}", ['catatan' => 'Klarifikasi teks saja'])
            ->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame('Klarifikasi teks saja', $fresh->catatan);
        $this->assertNotNull($fresh->tanggal_verifikasi);
    }

    public function test_update_rejects_invalid_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}", ['status' => 'not_a_status'])
            ->assertStatus(422);
    }

    public function test_verify_rejects_unknown_admin(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_PARTIAL,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}/verify", [
                'admin_id' => 999999, 'status' => 'compliant',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_id']);
    }

    // D-gap — verify() trusts the caller's admin_id and never checks unit or role:
    // a PIC of another unit can verify a foreign unit's entry.
    public function test_verify_cross_unit_is_scoped(): void
    {
        $unitA = WorkUnit::create(['nama' => 'Unit A']);
        $unitB = WorkUnit::create(['nama' => 'Unit B']);
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unitB->id]);

        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unitA->id, 'pic_id' => $picA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $this->actingAs($picB)
            ->patchJson("/api/checklist-entries/{$entry->id}/verify", [
                'admin_id' => $picB->id, 'status' => 'compliant',
            ])
            ->assertForbidden();
    }

    public function test_generate_monthly_command_is_idempotent_and_assigns_pic(): void
    {
        $unit = WorkUnit::create(['nama' => 'Unit Bulanan']);
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $ctrl = $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $this->assertDatabaseHas('checklist_sessions', [
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'periode' => now()->format('Y-m'),
        ]);

        $session = ChecklistSession::where('unit_id', $unit->id)->first();
        $this->assertNotNull($session);

        $this->assertDatabaseHas('checklist_entries', [
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'control_id' => $ctrl->id,
            'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $countAfterFirstRun = ChecklistEntry::count();
        $sessionsAfterFirstRun = ChecklistSession::count();

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();
        $this->assertSame($countAfterFirstRun, ChecklistEntry::count(), 'Duplicate prevention: second run must not re-insert.');
        $this->assertSame($sessionsAfterFirstRun, ChecklistSession::count(), 'Duplicate prevention: second run must not duplicate session.');
    }

    public function test_web_pic_entry_update_scoped_to_own_pic_and_allows_nullable_catatan(): void
    {
        $unit = WorkUnit::create(['nama' => 'Unit PIC Web']);
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $otherPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        // non_compliant/partial status without catatan is now allowed (catatan is optional for PIC)
        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->patch("/admin/pic/checklist-entries/{$entry->id}", ['status' => 'non_compliant'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->patch("/admin/pic/checklist-entries/{$entry->id}", ['status' => 'partial'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->patch("/admin/pic/checklist-entries/{$entry->id}", [
                'status' => 'partial', 'catatan' => 'Baru terpenuhi sebagian',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('checklist_entries', [
            'id' => $entry->id, 'status' => 'partial', 'catatan' => 'Baru terpenuhi sebagian',
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->patch("/admin/pic/checklist-entries/{$entry->id}", [
                'status' => 'compliant', 'catatan' => 'Dokumen SOP tersedia',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('checklist_entries', [
            'id' => $entry->id, 'status' => 'compliant', 'catatan' => 'Dokumen SOP tersedia',
        ]);

        // another PIC cannot touch the entry (scoped via pic_id)
        $this->actingAs($otherPic)
            ->from('/admin/pic/checklist')
            ->patch("/admin/pic/checklist-entries/{$entry->id}", ['status' => 'partial', 'catatan' => 'x'])
            ->assertStatus(404);
    }

    public function test_web_pic_entry_evidence_upload_clears_verification(): void
    {
        Storage::fake('supabase');

        $unit = WorkUnit::create(['nama' => 'Unit Bukti Web']);
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $session = ChecklistSession::create(['konteks_penilaian' => 'Sesi bukti', 'unit_id' => $unit->id, 'framework_id' => $fw->id]);
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id, 'unit_id' => $unit->id,
            'pic_id' => $pic->id, 'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
            'admin_id' => $pic->id, 'tanggal_verifikasi' => now(), 'catatan_admin' => 'Tolak, bukti buram', 'catatan' => 'ok',
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                'bukti_file' => UploadedFile::fake()->create('sop.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect('/admin/pic/checklist');

        $this->assertDatabaseHas('compliance_evidences', [
            'checklist_entry_id' => $entry->id, 'uploaded_by' => $pic->id, 'version_number' => 1,
        ]);
        $this->assertDatabaseHas('checklist_entries', [
            'id' => $entry->id,
            'tanggal_verifikasi' => null,
            'catatan_admin' => null,
            'admin_id' => null,
        ]);
    }

    public function test_web_pic_entry_status_update_clears_verification_and_admin_notes(): void
    {
        $unit = WorkUnit::create(['nama' => 'Unit Update Web']);
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $session = ChecklistSession::create(['konteks_penilaian' => 'Sesi Update', 'unit_id' => $unit->id, 'framework_id' => $fw->id]);
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id, 'unit_id' => $unit->id,
            'pic_id' => $pic->id, 'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
            'admin_id' => $admin->id, 'tanggal_verifikasi' => now(), 'catatan_admin' => 'Perbaiki klausul ini',
        ]);

        $this->actingAs($pic)
            ->patchJson("/admin/pic/checklist-entries/{$entry->id}", [
                'status' => ChecklistEntry::STATUS_COMPLIANT,
            ])
            ->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::STATUS_COMPLIANT, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi);
        $this->assertNull($fresh->catatan_admin);
        $this->assertNull($fresh->admin_id);
    }
}
