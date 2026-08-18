<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompliancePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AuditLog::query()->delete();
        ChecklistEntry::query()->forceDelete();
        Finding::query()->forceDelete();
        Risk::query()->forceDelete();
        ChecklistSession::query()->forceDelete();
        Control::query()->forceDelete();
        User::query()->delete();
        WorkUnit::query()->forceDelete();
        Framework::query()->forceDelete();
    }

    public function test_authenticated_admin_can_view_compliance_page_with_database_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $response = $this->actingAs($user)
            ->get('/admin/kepatuhan/compliance');

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin-kepatuhan/compliance', false)
            ->has('frameworks')
            ->has('controls')
            ->has('workUnits')
        );
    }

    public function test_compliance_page_props_include_sessions_and_echo_filters(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $unit = WorkUnit::factory()->create(['nama' => 'Unit Filter']);
        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);

        ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'framework_id' => $framework->id,
            'periode' => '2026-08',
        ]);

        ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'framework_id' => $framework->id,
            'periode' => '2026-07',
        ]);

        $response = $this->actingAs($user)
            ->get("/admin/kepatuhan/compliance?unit_id={$unit->id}&framework_id={$framework->id}&search=abc&status=compliant&kategori=Annex%20A");

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin-kepatuhan/compliance', false)
            ->has('sessions', 2)
            ->has('sessions.0', fn (Assert $session) => $session
                ->where('unit_id', $unit->id)
                ->where('unit_nama', 'Unit Filter')
                ->where('framework_nama', 'ISO/IEC 27001:2022')
                ->where('periode', '2026-07')
                ->etc()
            )
            ->where('filters.unit_id', (string) $unit->id)
            ->where('filters.framework_id', (string) $framework->id)
            ->where('filters.search', 'abc')
            ->where('filters.status', 'compliant')
            ->where('filters.kategori', 'Annex A')
        );
    }

    public function test_compliance_page_controls_have_mapped_shape_and_category_labels(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27701', 'versi' => '2019']);

        $annexCtrl = Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.5.4',
            'judul' => 'Annex control',
            'kategori' => 'annex_a',
        ]);

        Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => '4.2.1',
            'judul' => 'Klausul control',
            'kategori' => 'klausul_4_10',
        ]);

        $response = $this->actingAs($user)->get('/admin/kepatuhan/compliance');

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin-kepatuhan/compliance', false)
            ->has('controls', 2)
            ->where('controls.0.id', (string) $annexCtrl->id)
            ->where('controls.0.code', 'A.5.4')
            ->where('controls.0.title', 'Annex control')
            ->where('controls.0.category', 'Annex A')
            ->where('controls.0.framework_nama', 'ISO/IEC 27701:2019')
            ->where('controls.1.category', 'Klausul 4-10')
        );
    }

    public function test_compliance_page_framework_summaries_have_exact_percentage(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $unit = WorkUnit::factory()->create();
        $iso27001 = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $iso27701 = Framework::factory()->create(['nama' => 'ISO/IEC 27701', 'versi' => '2019']);
        $session = ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'framework_id' => $iso27001->id,
        ]);

        $ctrlA = Control::factory()->create(['framework_id' => $iso27001->id]);
        $ctrlB = Control::factory()->create(['framework_id' => $iso27701->id]);

        ChecklistEntry::factory()->create(['session_id' => $session->id, 'control_id' => $ctrlA->id, 'unit_id' => $unit->id, 'status' => ChecklistEntry::STATUS_COMPLIANT]);
        ChecklistEntry::factory()->create(['session_id' => $session->id, 'control_id' => $ctrlA->id, 'unit_id' => $unit->id, 'status' => ChecklistEntry::STATUS_NON_COMPLIANT]);
        ChecklistEntry::factory()->create(['session_id' => $session->id, 'control_id' => $ctrlB->id, 'unit_id' => $unit->id, 'status' => ChecklistEntry::STATUS_NA]);

        $response = $this->actingAs($user)->get('/admin/kepatuhan/compliance');

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin-kepatuhan/compliance', false)
            ->has('frameworks', 2)
            ->where('frameworks.0.compliance_percentage', 50)
            ->where('frameworks.0.controls_count', 1)
            ->where('frameworks.1.compliance_percentage', 0)
        );
    }

    public function test_compliance_page_filters_controls_by_category_and_search(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);

        Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.5.1',
            'judul' => 'Kebijakan keamanan informasi',
            'kategori' => 'annex_a',
        ]);

        Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => '4.4.1',
            'judul' => 'Organisasi',
            'kategori' => 'klausul_4_10',
        ]);

        $byCategory = $this->actingAs($user)
            ->get('/admin/kepatuhan/compliance?kategori='.urlencode('Annex A'));

        $byCategory->assertOk();
        $byCategory->assertInertia(fn (Assert $page) => $page
            ->has('controls', 1)
            ->where('controls.0.code', 'A.5.1')
        );

        $bySearch = $this->actingAs($user)
            ->get('/admin/kepatuhan/compliance?search=4.4');

        $bySearch->assertOk();
        $bySearch->assertInertia(fn (Assert $page) => $page
            ->has('controls', 1)
            ->where('controls.0.title', 'Organisasi')
        );
    }
}
