<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use App\Services\ComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComplianceSessionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_view_sessions_page_with_props(): void
    {
        $unit = WorkUnit::factory()->create(['nama' => 'Biro Teknologi']);
        $framework = Framework::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $session = ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'framework_id' => $framework->id,
            'periode' => 'Maret 2026',
        ]);

        ChecklistEntry::factory()->count(10)->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'tanggal_input' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/kepatuhan/sessions');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('shared/checklist/sessions', false)
            ->has('sessions', 1)
            ->has('workUnits')
            ->has('frameworks')
            ->has('periodeOptions'));
    }

    public function test_sessions_page_search_filter_returns_only_matching(): void
    {
        $unit = WorkUnit::factory()->create(['nama' => 'Unit A']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'periode' => 'Maret 2026',
            'konteks_penilaian' => 'Pengecekan Keamanan Informasi',
        ]);
        ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'periode' => 'April 2026',
            'konteks_penilaian' => 'Audit Internal Data Center',
        ]);

        $response = $this->actingAs($admin)->get('/admin/kepatuhan/sessions?search=Keamanan');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('shared/checklist/sessions', false)
            ->has('sessions', 1));
    }

    public function test_service_counts_verified_entries(): void
    {
        $unit = WorkUnit::factory()->create(['nama' => 'Unit B']);
        $session = ChecklistSession::factory()->create(['unit_id' => $unit->id]);

        ChecklistEntry::factory()->count(2)->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'tanggal_input' => now(),
            'tanggal_verifikasi' => now(),
        ]);

        $sessions = app(ComplianceService::class)->getAdminSessions([]);

        $this->assertCount(1, $sessions);
        $this->assertSame(2, $sessions[0]['verified_entries']);
        $this->assertArrayNotHasKey('status', $sessions[0]);
    }

    public function test_admin_generate_creates_session_and_seeds_entries_with_unit_pic(): void
    {
        $unit = WorkUnit::factory()->create();
        $framework = Framework::factory()->create();
        Control::factory()->count(3)->create(['framework_id' => $framework->id]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $response = $this->actingAs($admin)->post('/admin/kepatuhan/checklist-sessions', [
            'unit_id' => $unit->id,
            'framework_id' => $framework->id,
            'periode' => '2026-08',
            'konteks_penilaian' => 'Penilaian Bulanan SMKI - Agustus 2026',
        ]);

        $response->assertRedirect('/admin/kepatuhan/sessions');

        $session = ChecklistSession::where('unit_id', $unit->id)
            ->where('framework_id', $framework->id)
            ->where('periode', '2026-08')
            ->firstOrFail();

        $this->assertSame(3, $session->entries()->count());
        $this->assertDatabaseHas('checklist_entries', [
            'session_id' => $session->id,
            'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
            'catatan' => 'Belum diisi oleh PIC.',
        ]);
    }

    public function test_admin_generate_is_idempotent_on_same_unit_framework_periode(): void
    {
        $unit = WorkUnit::factory()->create();
        $framework = Framework::factory()->create();
        Control::factory()->count(2)->create(['framework_id' => $framework->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $payload = [
            'unit_id' => $unit->id,
            'framework_id' => $framework->id,
            'periode' => '2026-08',
            'konteks_penilaian' => 'Penilaian Bulanan SMKI - Agustus 2026',
        ];

        $this->actingAs($admin)->post('/admin/kepatuhan/checklist-sessions', $payload);
        $this->actingAs($admin)->post('/admin/kepatuhan/checklist-sessions', $payload);

        $session = ChecklistSession::where('unit_id', $unit->id)
            ->where('framework_id', $framework->id)
            ->where('periode', '2026-08')
            ->firstOrFail();
        $this->assertSame(1, ChecklistSession::where('unit_id', $unit->id)
            ->where('framework_id', $framework->id)
            ->where('periode', '2026-08')
            ->count());
        $this->assertSame(2, $session->entries()->count());
    }

    public function test_generate_refuses_duplicate_session_for_same_month(): void
    {
        $unit = WorkUnit::factory()->create();
        $framework = Framework::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'framework_id' => $framework->id,
            'periode' => '2026-08',
        ]);

        $this->actingAs($admin)
            ->post('/admin/kepatuhan/checklist-sessions', [
                'unit_id' => $unit->id,
                'framework_id' => $framework->id,
                'periode' => '2026-08',
                'konteks_penilaian' => 'Duplikat',
            ])
            ->assertRedirect('/admin/kepatuhan/sessions');

        $this->assertSame(1, ChecklistSession::where('unit_id', $unit->id)
            ->where('framework_id', $framework->id)
            ->where('periode', '2026-08')
            ->count());
    }

    public function test_generate_requires_create_permission(): void
    {
        $unit = WorkUnit::factory()->create();
        $framework = Framework::factory()->create();
        $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);

        $this->actingAs($koordinator)
            ->post('/admin/kepatuhan/checklist-sessions', [
                'unit_id' => $unit->id,
                'framework_id' => $framework->id,
                'periode' => '2026-08',
                'konteks_penilaian' => 'X',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_delete_session(): void
    {
        $unit = WorkUnit::factory()->create();
        $session = ChecklistSession::factory()->create(['unit_id' => $unit->id]);
        ChecklistEntry::factory()->count(2)->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $this->actingAs($admin)
            ->delete("/admin/kepatuhan/checklist-sessions/{$session->id}")
            ->assertRedirect('/admin/kepatuhan/sessions');

        $this->assertSoftDeleted('checklist_sessions', ['id' => $session->id]);
        $this->assertSame(0, ChecklistEntry::where('session_id', $session->id)->count());
    }

    public function test_admin_generate_monthly_creates_sessions_for_all_units(): void
    {
        $framework = Framework::factory()->create();
        $unitA = WorkUnit::factory()->create();
        $unitB = WorkUnit::factory()->create();
        Control::factory()->count(2)->create(['framework_id' => $framework->id]);
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unitB->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $periode = now()->format('Y-m');

        $response = $this->actingAs($admin)->post('/admin/kepatuhan/generate-monthly');

        $response->assertRedirect('/admin/kepatuhan/sessions');

        $this->assertSame(2, ChecklistSession::where('periode', $periode)->count());
        $this->assertDatabaseHas('checklist_sessions', ['unit_id' => $unitA->id, 'framework_id' => $framework->id, 'periode' => $periode]);
        $this->assertDatabaseHas('checklist_sessions', ['unit_id' => $unitB->id, 'framework_id' => $framework->id, 'periode' => $periode]);

        $sessionA = ChecklistSession::where('unit_id', $unitA->id)->where('periode', $periode)->firstOrFail();
        $this->assertDatabaseHas('checklist_entries', [
            'session_id' => $sessionA->id,
            'unit_id' => $unitA->id,
            'pic_id' => $picA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);
    }

    public function test_generate_monthly_requires_create_permission(): void
    {
        $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);

        $this->actingAs($koordinator)
            ->post('/admin/kepatuhan/generate-monthly')
            ->assertForbidden();
    }

    public function test_generate_monthly_is_idempotent_in_same_month(): void
    {
        $framework = Framework::factory()->create();
        $unit = WorkUnit::factory()->create();
        Control::factory()->count(2)->create(['framework_id' => $framework->id]);
        User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $periode = now()->format('Y-m');

        $this->actingAs($admin)->post('/admin/kepatuhan/generate-monthly');
        $this->actingAs($admin)->post('/admin/kepatuhan/generate-monthly');

        $this->assertSame(1, ChecklistSession::where('unit_id', $unit->id)
            ->where('framework_id', $framework->id)
            ->where('periode', $periode)
            ->count());
    }
}
