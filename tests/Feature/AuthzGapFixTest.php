<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthzGapFixTest extends TestCase
{
    use RefreshDatabase;

    private function seedEntry(): array
    {
        $own = WorkUnit::create(['nama' => 'Own']);
        $other = WorkUnit::create(['nama' => 'Other']);
        $fw = Framework::create(['nama' => 'ISO', 'versi' => '1']);
        $control = $fw->controls()->create(['kode_klausul' => 'A.1', 'judul' => 'T', 'kategori' => 'teknologi']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $own->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $own->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'tanggal_verifikasi' => now()->subHour(),
        ]);
        $session = ChecklistSession::create(['konteks_penilaian' => 'S', 'unit_id' => $other->id]);

        return compact('own', 'other', 'control', 'pic', 'entry', 'session');
    }

    public function test_c3_auditor_cannot_rewrite_checklist_entry(): void
    {
        ['entry' => $entry] = $this->seedEntry();
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $verifiedAt = $entry->tanggal_verifikasi;

        $this->actingAs($auditor)
            ->patchJson("/api/checklist-entries/{$entry->id}", ['catatan' => 'Hacked'])
            ->assertForbidden();

        $this->assertEquals($verifiedAt->toDateTimeString(), $entry->fresh()->tanggal_verifikasi->toDateTimeString());
    }

    public function test_c3_koordinator_cannot_rewrite_checklist_entry(): void
    {
        ['entry' => $entry] = $this->seedEntry();
        $koord = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);

        $this->actingAs($koord)
            ->patchJson("/api/checklist-entries/{$entry->id}", ['catatan' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_h2_generate_monthly_rejects_invalid_periode(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/checklist-entries/generate-monthly', ['periode' => 'not-a-date; rm -rf'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['periode']);
    }

    public function test_h2_pic_scoped_to_own_unit(): void
    {
        $own = WorkUnit::create(['nama' => 'Own']);
        $other = WorkUnit::create(['nama' => 'Other']);
        $fw = Framework::create(['nama' => 'ISO', 'versi' => '1']);
        $fw->controls()->create(['kode_klausul' => 'A.1', 'judul' => 'T', 'kategori' => 'teknologi']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $own->id]);

        $this->actingAs($pic)->postJson('/api/checklist-entries/generate-monthly')->assertOk();

        $this->assertSame(1, ChecklistSession::where('unit_id', $own->id)->count());
        $this->assertSame(0, ChecklistSession::where('unit_id', $other->id)->count());
    }

    public function test_h4_pic_session_index_scoped(): void
    {
        ['own' => $own, 'other' => $other] = $this->seedEntry();
        ChecklistSession::create(['konteks_penilaian' => 'Own S', 'unit_id' => $own->id]);
        ChecklistSession::create(['konteks_penilaian' => 'Other S', 'unit_id' => $other->id]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $own->id]);

        $rows = $this->actingAs($pic)->getJson('/api/checklist-sessions?all=true')->assertOk()->json('data');
        $unitIds = collect($rows)->pluck('unit_id')->unique()->all();

        $this->assertNotContains($other->id, $unitIds);
    }

    public function test_h4_pic_cannot_show_sibling_session(): void
    {
        ['session' => $session, 'own' => $own] = $this->seedEntry();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $own->id]);

        $this->actingAs($pic)->getJson("/api/checklist-sessions/{$session->id}")->assertForbidden();
    }

    public function test_h5_pic_blocked_from_compliance_page(): void
    {
        $unit = WorkUnit::create(['nama' => 'U']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $this->actingAs($pic)->get('/admin/kepatuhan/compliance')->assertForbidden();
    }

    public function test_h7_test_upload_route_removed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)->postJson('/api/test-upload', [])->assertNotFound();
    }
}
