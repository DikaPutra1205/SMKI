<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pic;

    private User $superadmin;

    private User $auditor;

    private User $koordinator;

    private WorkUnit $unit;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Pdf::fake();

        $this->unit = WorkUnit::factory()->create(['nama' => 'Pusat Data Komdigi']);
        $this->admin = User::factory()->create(['role' => 'admin_kepatuhan', 'unit_id' => $this->unit->id]);
        $this->pic = User::factory()->create(['role' => 'pic', 'unit_id' => $this->unit->id]);
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->auditor = User::factory()->create(['role' => 'auditor']);
        $this->koordinator = User::factory()->create(['role' => 'koordinator_smki', 'unit_id' => $this->unit->id]);

        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->control = Control::factory()->create(['framework_id' => $framework->id, 'kode_klausul' => 'A.5.1']);
    }

    public function test_superadmin_can_export_all_enabled_report_types(): void
    {
        $response1 = $this->actingAs($this->superadmin)->get('/reports/export-pdf?type=quick-summary');
        $response1->assertOk();
        $this->assertEquals('application/pdf', $response1->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response1->getContent());

        $response2 = $this->actingAs($this->superadmin)->get('/reports/export-pdf?type=executive');
        $response2->assertOk();
        $this->assertEquals('application/pdf', $response2->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response2->getContent());

        $response3 = $this->actingAs($this->superadmin)->get('/reports/export-pdf?type=audit-ready');
        $response3->assertStatus(501);
    }

    public function test_admin_kepatuhan_can_export_quick_summary_only(): void
    {
        $response1 = $this->actingAs($this->admin)->get('/reports/export-pdf?type=quick-summary');
        $response1->assertOk();

        $response2 = $this->actingAs($this->admin)->get('/reports/export-pdf?type=executive');
        $response2->assertForbidden();
    }

    public function test_auditor_can_export_both_quick_summary_and_executive(): void
    {
        $response1 = $this->actingAs($this->auditor)->get('/reports/export-pdf?type=quick-summary');
        $response1->assertOk();

        $response2 = $this->actingAs($this->auditor)->get('/reports/export-pdf?type=executive');
        $response2->assertOk();
    }

    public function test_koordinator_smki_can_export_executive_only(): void
    {
        $response1 = $this->actingAs($this->koordinator)->get('/reports/export-pdf?type=executive');
        $response1->assertOk();

        $response2 = $this->actingAs($this->koordinator)->get('/reports/export-pdf?type=quick-summary');
        $response2->assertForbidden();
    }

    public function test_pic_cannot_export_any_pdf_report(): void
    {
        $this->actingAs($this->pic)->get('/reports/export-pdf?type=quick-summary')->assertForbidden();
        $this->actingAs($this->pic)->get('/reports/export-pdf?type=executive')->assertForbidden();
    }

    public function test_reports_periods_endpoint_returns_available_periods_json(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/periods');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['value', 'label', 'year', 'month'],
                ],
                'work_units' => [
                    '*' => ['id', 'nama'],
                ],
            ]);
    }

    public function test_reports_work_units_endpoint_returns_work_units_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/work-units');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'nama'],
                ],
            ]);
    }

    public function test_report_export_with_unit_id_filter(): void
    {
        $response = $this->actingAs($this->superadmin)->get("/reports/export-pdf?type=quick-summary&unit_id={$this->unit->id}");
        $response->assertOk();
    }

    public function test_single_period_export_has_standardized_filename(): void
    {
        $response = $this->actingAs($this->koordinator)->get("/reports/export-pdf?type=executive&unit_id={$this->unit->id}&periode=2026-07");
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('Laporan_Eksekutif_SMKI_pusat_data_komdigi_Juli_2026.pdf', $disposition);
    }

    public function test_multi_month_export_returns_zip_archive_with_individual_pdfs(): void
    {
        $response = $this->actingAs($this->koordinator)->get(
            "/reports/export-pdf?type=executive&unit_id={$this->unit->id}&start_date=2026-06-01&end_date=2026-08-31&print_mode=per_month"
        );

        $response->assertOk();
        $this->assertEquals('application/zip', $response->headers->get('Content-Type'));
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('Laporan_Eksekutif_SMKI_pusat_data_komdigi_', $disposition);
        $this->assertStringEndsWith('.zip"', $disposition);

        // Verify ZIP content
        $tempPath = tempnam(sys_get_temp_dir(), 'test_zip_');
        file_put_contents($tempPath, $response->getContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tempPath));
        $this->assertEquals(3, $zip->numFiles);
        $this->assertStringContainsString('01_Laporan_Eksekutif_SMKI_pusat_data_komdigi_Juni_2026.pdf', $zip->getNameIndex(0));
        $this->assertStringContainsString('02_Laporan_Eksekutif_SMKI_pusat_data_komdigi_Juli_2026.pdf', $zip->getNameIndex(1));
        $this->assertStringContainsString('03_Laporan_Eksekutif_SMKI_pusat_data_komdigi_Agustus_2026.pdf', $zip->getNameIndex(2));
        $zip->close();
        @unlink($tempPath);
    }

    public function test_authorized_user_can_get_compliance_summary_report_data(): void
    {
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
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
                    'audit_metrics' => ['open_findings', 'total_risks', 'critical_high_risks'],
                ],
            ]);
    }

    public function test_authorized_user_can_export_compliance_summary_csv_and_it_records_audit_log(): void
    {
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
        ]);

        $response = $this->actingAs($this->admin)->get('/api/v1/reports/export-csv');

        $response->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('SMKI_Compliance_Report_', $response->headers->get('Content-Disposition'));

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

    public function test_exported_csv_contains_expected_rows_and_utf8_bom(): void
    {
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'admin_id' => $this->admin->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'catatan_admin' => 'Bukti lengkap',
            'tanggal_verifikasi' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get('/api/v1/reports/export-csv');

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'CSV harus diawali BOM UTF-8 untuk Excel.');
        $this->assertStringContainsString('"ID Entri","Kode Klausul","Judul Kontrol",Framework,"Unit Kerja"', $content);
        $this->assertStringContainsString('A.5.1', $content);
        $this->assertStringContainsString('ISO/IEC 27001', $content);
        $this->assertStringContainsString('Pusat Data Komdigi', $content);
        $this->assertStringContainsString('COMPLIANT', $content);
        $this->assertStringContainsString('Bukti lengkap', $content);
        $this->assertStringContainsString($this->admin->name, $content);
        $this->assertStringContainsString(now()->format('Y-m-d H:i'), $content);
    }

    public function test_export_records_append_only_anti_tamper_audit_log_with_details(): void
    {
        $this->actingAs($this->admin)->get('/api/v1/reports/export-csv')->assertOk();
        $this->actingAs($this->admin)->get('/api/v1/reports/export-csv')->assertOk();

        $logs = AuditLog::where('entity_type', 'Report')->where('aksi', 'export')->get();

        $this->assertCount(2, $logs, 'Setiap ekspor harus menambah entri jejak baru (append-only).');

        $detail = AuditLog::where('entity_type', 'Report')->where('aksi', 'export')
            ->orderByDesc('id')->first()->detail_perubahan;
        $this->assertEquals('compliance_summary_csv', $detail['report_type']);
        $this->assertNull($detail['scoped_unit_id']);
        $this->assertArrayHasKey('exported_at', $detail);
        $this->assertArrayHasKey('ip_address', $detail);
        $this->assertEquals($this->admin->id, $logs->first()->actor_id);
    }

    public function test_csv_export_is_scoped_to_requested_unit(): void
    {
        $otherUnit = WorkUnit::factory()->create(['nama' => 'Unit Lain']);

        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
        ]);
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $otherUnit->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
        ]);

        $response = $this->actingAs($this->admin)->get('/api/v1/reports/export-csv?unit_id='.$otherUnit->id);

        $content = $response->streamedContent();
        $this->assertStringContainsString('Unit Lain', $content);
        $this->assertStringNotContainsString('Pusat Data Komdigi', $content);
    }

    public function test_compliance_summary_reports_accurate_audit_metrics_counts(): void
    {
        $otherUnit = WorkUnit::factory()->create(['nama' => 'Unit Lain']);
        $otherControl = Control::factory()->create([
            'framework_id' => $this->control->framework_id,
            'kode_klausul' => 'B.9.9',
        ]);

        Finding::factory()->create(['control_id' => $this->control->id, 'unit_id' => $this->unit->id, 'status' => Finding::STATUS_OPEN]);
        Finding::factory()->create(['control_id' => $this->control->id, 'unit_id' => $this->unit->id, 'status' => Finding::STATUS_IN_PROGRESS]);
        Finding::factory()->create(['control_id' => $this->control->id, 'unit_id' => $otherUnit->id, 'status' => Finding::STATUS_OPEN]);
        Finding::factory()->create(['control_id' => $this->control->id, 'unit_id' => $this->unit->id, 'status' => Finding::STATUS_CLOSED]);

        Risk::factory()->withControl($this->control)->create(['level_risiko' => Risk::LEVEL_HIGH]);
        Risk::factory()->withControl($otherControl)->create(['level_risiko' => Risk::LEVEL_CRITICAL]);
        Risk::factory()->withControl($otherControl)->create(['level_risiko' => Risk::LEVEL_LOW]);

        ChecklistEntry::factory()->create(['control_id' => $this->control->id, 'unit_id' => $this->unit->id, 'status' => ChecklistEntry::WORKFLOW_SELESAI]);
        ChecklistEntry::factory()->create(['control_id' => $otherControl->id, 'unit_id' => $otherUnit->id, 'status' => ChecklistEntry::WORKFLOW_SELESAI]);

        $all = $this->actingAs($this->admin)->getJson('/api/v1/reports/compliance-summary');
        $all->assertOk();
        $this->assertEquals(3, $all->json('data.audit_metrics.open_findings'));
        $this->assertEquals(3, $all->json('data.audit_metrics.total_risks'));
        $this->assertEquals(2, $all->json('data.audit_metrics.critical_high_risks'));
        $this->assertEquals('Semua Unit Kerja', $all->json('data.scoped_unit'));
        $this->assertEquals($this->admin->id, $all->json('data.generated_by.id'));

        $scoped = $this->actingAs($this->admin)->getJson('/api/v1/reports/compliance-summary?unit_id='.$this->unit->id);
        $scoped->assertOk();
        $this->assertEquals(2, $scoped->json('data.audit_metrics.open_findings'));
        $this->assertEquals(1, $scoped->json('data.audit_metrics.total_risks'));
        $this->assertEquals(1, $scoped->json('data.audit_metrics.critical_high_risks'));
        $this->assertEquals('Pusat Data Komdigi', $scoped->json('data.scoped_unit'));
    }

    public function test_koordinator_can_export_reports(): void
    {
        $koordinator = User::factory()->create(['role' => 'koordinator_smki', 'unit_id' => $this->unit->id]);

        $this->actingAs($koordinator)->get('/api/v1/reports/export-csv')->assertOk();
    }

    public function test_web_export_route_serves_csv_and_blocks_pic(): void
    {
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/kepatuhan/reports/export');
        $response->assertOk();
        $this->assertNotEmpty($response->getContent());

        $this->actingAs($this->pic)->get('/admin/kepatuhan/reports/export')->assertForbidden();
    }

    public function test_authorized_user_can_export_compliance_summary_pdf_and_it_records_audit_log(): void
    {
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
        ]);

        $response = $this->actingAs($this->admin)->get('/api/v1/reports/export-pdf');

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('SMKI_Compliance_Report_', $response->headers->get('Content-Disposition'));

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Report',
            'aksi' => 'export',
            'actor_id' => $this->admin->id,
        ]);
    }
}
