<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pic;

    private WorkUnit $unit;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = WorkUnit::factory()->create(['nama' => 'Pusat Data Komdigi']);
        $this->admin = User::factory()->create(['role' => 'admin_kepatuhan', 'unit_id' => $this->unit->id]);
        $this->pic = User::factory()->create(['role' => 'pic', 'unit_id' => $this->unit->id]);

        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->control = Control::factory()->create(['framework_id' => $framework->id, 'kode_klausul' => 'A.5.1']);
    }

    public function test_authorized_user_can_get_compliance_summary_report_data(): void
    {
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports/compliance-summary');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'generated_at',
                    'generated_by',
                    'scoped_unit',
                    'summary',
                    'unit_metrics',
                    'governance' => ['open_findings', 'total_risks', 'critical_high_risks'],
                ],
            ]);
    }

    public function test_authorized_user_can_export_compliance_summary_csv_and_it_records_audit_log(): void
    {
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $response = $this->actingAs($this->admin)->get('/api/v1/reports/export-csv');

        $response->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('SMKI_Compliance_Report_', $response->headers->get('Content-Disposition'));

        // Verify Anti-Tamper audit log creation
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Report',
            'aksi' => 'export',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_pic_cannot_export_reports_and_receives_forbidden(): void
    {
        $response = $this->actingAs($this->pic)->getJson('/api/v1/reports/compliance-summary');
        $response->assertForbidden();

        $exportResponse = $this->actingAs($this->pic)->get('/api/v1/reports/export-csv');
        $exportResponse->assertForbidden();
    }
}
