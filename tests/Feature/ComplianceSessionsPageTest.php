<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
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
            ->component('admin-kepatuhan/sessions', false)
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
            ->component('admin-kepatuhan/sessions', false)
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
}
