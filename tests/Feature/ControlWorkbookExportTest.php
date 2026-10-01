<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use App\Services\ControlWorkbookExportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;
use ZipArchive;

class ControlWorkbookExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pic;

    private User $superadmin;

    private User $auditor;

    private User $koordinator;

    private WorkUnit $unit;

    private WorkUnit $otherUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->unit = WorkUnit::factory()->create(['nama' => 'Pusat Data Komdigi']);
        $this->otherUnit = WorkUnit::factory()->create(['nama' => 'Sekretariat Jenderal']);
        $this->admin = User::factory()->create(['role' => 'admin_kepatuhan', 'unit_id' => $this->unit->id]);
        $this->pic = User::factory()->create(['role' => 'pic', 'unit_id' => $this->unit->id]);
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->auditor = User::factory()->create(['role' => 'auditor']);
        $this->koordinator = User::factory()->create(['role' => 'koordinator_smki', 'unit_id' => $this->unit->id]);

        $fw27001 = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $fw27701 = Framework::factory()->create(['nama' => 'ISO/IEC 27701', 'versi' => '2025']);

        // Control codes intentionally mirror the template's 27001 IDs so rows match.
        $c1 = Control::factory()->create(['framework_id' => $fw27001->id, 'kode_klausul' => 'A.5.1', 'kategori' => 'organisasional']);
        $c2 = Control::factory()->create(['framework_id' => $fw27001->id, 'kode_klausul' => 'A.8.8', 'kategori' => 'teknologi']);
        $c3 = Control::factory()->create(['framework_id' => $fw27001->id, 'kode_klausul' => 'A.5.2', 'kategori' => 'organisasional']);
        $c4 = Control::factory()->create(['framework_id' => $fw27701->id, 'kode_klausul' => '7.2.1', 'kategori' => 'organisasional', 'domain_peran' => 'controller']);
        $c5 = Control::factory()->create(['framework_id' => $fw27701->id, 'kode_klausul' => '8.2.1', 'kategori' => 'teknologi', 'domain_peran' => 'processor']);

        $periode = now()->format('Y-m');
        $s1 = ChecklistSession::factory()->create(['unit_id' => $this->unit->id, 'framework_id' => $fw27001->id, 'periode' => $periode]);
        $s2 = ChecklistSession::factory()->create(['unit_id' => $this->unit->id, 'framework_id' => $fw27701->id, 'periode' => $periode]);

        $e1 = ChecklistEntry::factory()->create([
            'session_id' => $s1->id,
            'control_id' => $c1->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 4,
            'catatan' => 'Kebijakan sudah disahkan direksi.',
            'tanggal_verifikasi' => now()->subDay(),
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $s1->id,
            'control_id' => $c2->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'level_maturity' => 2,
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $s1->id,
            'control_id' => $c3->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
            'catatan_admin' => 'Tidak ada layanan cloud publik di unit ini.',
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $s2->id,
            'control_id' => $c4->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES,
            'level_maturity' => 2,
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $s2->id,
            'control_id' => $c5->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 3,
            'tanggal_verifikasi' => now()->subDays(2),
        ]);

        Finding::factory()->create([
            'control_id' => $c1->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(14)->toDateString(),
        ]);

        ComplianceEvidence::create([
            'checklist_entry_id' => $e1->id,
            'uploaded_by' => $this->pic->id,
            'file_url' => 'bukti/kebijakan-keamanan-v3.pdf',
            'version_number' => 1,
            'is_active' => true,
            'uploaded_at' => now(),
        ]);
    }

    private function loadResponseWorkbook(mixed $response): Spreadsheet
    {
        // Download responses stream a temp file; read that file directly.
        return IOFactory::load($response->getFile()->getPathname());
    }

    private function findRowByKode($sheet, string $kode): ?int
    {
        foreach ($sheet->getRowIterator(5) as $row) {
            $cell = trim((string) $sheet->getCell('B'.$row->getRowIndex())->getValue());

            if ($cell === $kode) {
                return $row->getRowIndex();
            }
        }

        return null;
    }

    public function test_single_unit_export_returns_filled_xlsx(): void
    {
        $response = $this->actingAs($this->admin)->get("/reports/export-excel?unit_id={$this->unit->id}");

        $response->assertOk();
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));

        // Charts and drawings from the template Dashboard must survive the round-trip.
        $raw = new ZipArchive;
        $this->assertTrue($raw->open($response->getFile()->getPathname()));
        $entries = [];
        for ($i = 0; $i < $raw->numFiles; $i++) {
            $entries[] = $raw->getNameIndex($i);
        }
        $raw->close();
        $this->assertContains('xl/charts/chart1.xml', $entries);
        $this->assertContains('xl/charts/chart2.xml', $entries);
        $this->assertContains('xl/charts/chart3.xml', $entries);
        $this->assertNotEmpty(array_filter($entries, fn ($n) => str_starts_with($n, 'xl/drawings/drawing')));

        $wb = $this->loadResponseWorkbook($response);

        try {
            $this->assertSame(
                ['Panduan', 'Dashboard', 'ISO IEC 27001', 'ISO IEC 27701'],
                $wb->getSheetNames()
            );

            $sheet = $wb->getSheetByName('ISO IEC 27001');

            $row1 = $this->findRowByKode($sheet, 'A.5.1');
            $this->assertNotNull($row1);
            $this->assertSame('Ya', $sheet->getCell("G{$row1}")->getValue());
            $this->assertSame('Diterapkan', $sheet->getCell("I{$row1}")->getValue());
            $this->assertSame(4, (int) $sheet->getCell("J{$row1}")->getValue());
            $this->assertSame('Tinggi', $sheet->getCell("P{$row1}")->getValue());
            $this->assertSame('Kebijakan sudah disahkan direksi.', (string) $sheet->getCell("S{$row1}")->getValue());
            $this->assertStringContainsString($this->pic->name, (string) $sheet->getCell("L{$row1}")->getValue());
            $this->assertSame('kebijakan-keamanan-v3.pdf', (string) $sheet->getCell("M{$row1}")->getValue());
            $this->assertNotEmpty($sheet->getCell("N{$row1}")->getValue());
            $this->assertNotEmpty($sheet->getCell("O{$row1}")->getValue());
            $this->assertNotEmpty($sheet->getCell("Q{$row1}")->getValue());

            // Progres column keeps its template formula, derived from column I.
            $this->assertStringStartsWith('=', (string) $sheet->getCell("K{$row1}")->getValue());

            // dalam_tinjauan is exported as Dalam Proses (template has no Tinjauan option).
            $row2 = $this->findRowByKode($sheet, 'A.8.8');
            $this->assertNotNull($row2);
            $this->assertSame('Dalam Proses', $sheet->getCell("I{$row2}")->getValue());

            // tidak_berlaku maps to Tidak + N/A + justification.
            $row3 = $this->findRowByKode($sheet, 'A.5.2');
            $this->assertNotNull($row3);
            $this->assertSame('Tidak', $sheet->getCell("G{$row3}")->getValue());
            $this->assertSame('Tidak Berlaku (N/A)', $sheet->getCell("I{$row3}")->getValue());
            $this->assertSame('Tidak ada layanan cloud publik di unit ini.', (string) $sheet->getCell("H{$row3}")->getValue());

            // Dashboard cached values are recalculated from real data on save.
            // 27001: 93 rows, 92 applicable, 1 Diterapkan, 1 Proses, 90 Belum,
            // 1 N/A. 27701 merged: controller 7.2.1 (Proses) + processor 8.2.1
            // (Diterapkan), shared empty.
            $dashboard = $wb->getSheetByName('Dashboard');
            $this->assertSame(93, (int) $dashboard->getCell('C6')->getValue());
            $this->assertSame(1, (int) $dashboard->getCell('C7')->getValue());
            $this->assertSame(1, (int) $dashboard->getCell('C8')->getValue());
            $this->assertSame(0, (int) $dashboard->getCell('C9')->getValue());
            $this->assertSame(95, (int) $dashboard->getCell('C10')->getOldCalculatedValue());
            $this->assertSame(92, (int) $dashboard->getCell('D6')->getOldCalculatedValue());
            $this->assertSame(1, (int) $dashboard->getCell('E6')->getOldCalculatedValue());
            $this->assertSame(1, (int) $dashboard->getCell('F6')->getOldCalculatedValue());
            $this->assertSame(90, (int) $dashboard->getCell('G6')->getOldCalculatedValue());
            $this->assertSame(1, (int) $dashboard->getCell('H6')->getOldCalculatedValue());
            $this->assertSame(1, (int) $dashboard->getCell('D7')->getOldCalculatedValue());
            $this->assertSame(0, (int) $dashboard->getCell('E7')->getOldCalculatedValue());
            $this->assertSame(1, (int) $dashboard->getCell('F7')->getOldCalculatedValue());
            $this->assertSame(0, (int) $dashboard->getCell('G7')->getOldCalculatedValue());
            $this->assertSame(0, (int) $dashboard->getCell('H7')->getOldCalculatedValue());
            $this->assertSame(1, (int) $dashboard->getCell('D8')->getOldCalculatedValue());
            $this->assertSame(1, (int) $dashboard->getCell('E8')->getOldCalculatedValue());
            $this->assertSame(0, (int) $dashboard->getCell('F8')->getOldCalculatedValue());
            $this->assertSame(0, (int) $dashboard->getCell('G8')->getOldCalculatedValue());
            $this->assertSame(0, (int) $dashboard->getCell('H8')->getOldCalculatedValue());
            $this->assertSame(0, (int) $dashboard->getCell('D9')->getOldCalculatedValue());
            $this->assertSame(0, (int) $dashboard->getCell('H9')->getOldCalculatedValue());
            $this->assertSame(94, (int) $dashboard->getCell('D10')->getOldCalculatedValue());
            $this->assertSame(2, (int) $dashboard->getCell('E10')->getOldCalculatedValue());
            $this->assertSame(2, (int) $dashboard->getCell('F10')->getOldCalculatedValue());
            $this->assertSame(90, (int) $dashboard->getCell('G10')->getOldCalculatedValue());
            $this->assertSame(1, (int) $dashboard->getCell('H10')->getOldCalculatedValue());

            // Formulas point at the framework sheets, scopes via COUNTIFS.
            $this->assertStringContainsString("'ISO IEC 27001'", (string) $dashboard->getCell('D6')->getValue());
            $this->assertStringContainsString('COUNTIFS', (string) $dashboard->getCell('D7')->getValue());
            $this->assertStringContainsString("'ISO IEC 27701'", (string) $dashboard->getCell('D7')->getValue());
            $this->assertStringContainsString('PII Controller', (string) $dashboard->getCell('D7')->getValue());
            $this->assertStringContainsString('PII Processor', (string) $dashboard->getCell('D8')->getValue());
        } finally {
            $wb->disconnectWorksheets();
        }
    }

    public function test_reference_columns_and_dashboard_formulas_stay_intact(): void
    {
        $response = $this->actingAs($this->admin)->get("/reports/export-excel?unit_id={$this->unit->id}");
        $response->assertOk();

        $wb = $this->loadResponseWorkbook($response);
        $pristine = IOFactory::load(resource_path(ControlWorkbookExportService::TEMPLATE_RELATIVE_PATH));

        try {
            // The 27001 sheet matches the catalogue 1:1, so its reference columns
            // must stay byte-identical to the template.
            $filled = $wb->getSheetByName('ISO IEC 27001');
            $orig = $pristine->getSheetByName('ISO27001-2022');
            $this->assertNotNull($filled);
            $this->assertNotNull($orig);

            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
                $this->assertSame(
                    (string) $orig->getCell("{$col}5")->getValue(),
                    (string) $filled->getCell("{$col}5")->getValue(),
                    "Reference {$col}5 on ISO IEC 27001 must stay identical to the template"
                );
            }

            $dashboard = $wb->getSheetByName('Dashboard');
            $this->assertStringStartsWith('=', (string) $dashboard->getCell('D6')->getValue());
            $this->assertStringStartsWith('=', (string) $dashboard->getCell('I10')->getValue());
        } finally {
            $wb->disconnectWorksheets();
            $pristine->disconnectWorksheets();
        }
    }

    public function test_merged_27701_sheet_follows_database_scopes(): void
    {
        $response = $this->actingAs($this->admin)->get("/reports/export-excel?unit_id={$this->unit->id}");
        $response->assertOk();

        $wb = $this->loadResponseWorkbook($response);

        try {
            // Legacy scope sheets are gone; one merged sheet per framework.
            $this->assertNull($wb->getSheetByName('ISO27701-A1_Controller'));
            $this->assertNull($wb->getSheetByName('ISO27701-A2_Processor'));
            $this->assertNull($wb->getSheetByName('ISO27701-A3_Shared'));

            $sheet = $wb->getSheetByName('ISO IEC 27701');
            $this->assertNotNull($sheet);

            // Controller first, then processor (dashboard rows 7/8 order).
            $this->assertSame('7.2.1', trim((string) $sheet->getCell('B5')->getValue()));
            $this->assertSame('PII Controller', (string) $sheet->getCell('C5')->getValue());
            $this->assertSame(1, (int) $sheet->getCell('A5')->getValue());
            $this->assertSame('Dalam Proses', $sheet->getCell('I5')->getValue());
            $this->assertSame(2, (int) $sheet->getCell('J5')->getValue());
            $this->assertStringStartsWith('=', (string) $sheet->getCell('K5')->getValue());

            $this->assertSame('8.2.1', trim((string) $sheet->getCell('B6')->getValue()));
            $this->assertSame('PII Processor', (string) $sheet->getCell('C6')->getValue());
            $this->assertSame('Diterapkan', $sheet->getCell('I6')->getValue());

            // No surplus rows: nothing beyond the 2 catalogue controls.
            $this->assertSame('', trim((string) $sheet->getCell('B7')->getValue()));
        } finally {
            $wb->disconnectWorksheets();
        }
    }

    public function test_empty_period_range_falls_back_to_latest_sessions(): void
    {
        $response = $this->actingAs($this->admin)->get(
            "/reports/export-excel?unit_id={$this->unit->id}&start_date=2030-01-01&end_date=2030-01-31"
        );
        $response->assertOk();

        $wb = $this->loadResponseWorkbook($response);

        try {
            $sheet = $wb->getSheetByName('ISO IEC 27001');
            $row1 = $this->findRowByKode($sheet, 'A.5.1');
            $this->assertNotNull($row1);
            $this->assertSame('Diterapkan', $sheet->getCell("I{$row1}")->getValue());
        } finally {
            $wb->disconnectWorksheets();
        }
    }

    public function test_all_units_export_returns_zip_with_one_xlsx_per_unit(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/export-excel');

        $response->assertOk();
        $this->assertEquals('application/zip', $response->headers->get('Content-Type'));

        $tmp = $response->getFile()->getPathname();

        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($tmp));
            $this->assertSame(2, $zip->numFiles);

            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }

            foreach ($names as $name) {
                $this->assertStringEndsWith('.xlsx', $name);
            }

            // First workbook inside the archive must be a valid filled workbook.
            $extractDir = sys_get_temp_dir().'/smki_test_zip_extract_'.uniqid();
            mkdir($extractDir);
            $zip->extractTo($extractDir, $names[0]);
            $zip->close();

            $wb = IOFactory::load($extractDir.'/'.$names[0]);
            $this->assertContains('ISO IEC 27001', $wb->getSheetNames());
            $this->assertContains('ISO IEC 27701', $wb->getSheetNames());
            $wb->disconnectWorksheets();
            @unlink($extractDir.'/'.$names[0]);
            @rmdir($extractDir);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_excel_export_role_matrix(): void
    {
        $this->actingAs($this->superadmin)->get("/reports/export-excel?unit_id={$this->unit->id}")->assertOk();
        $this->actingAs($this->auditor)->get("/reports/export-excel?unit_id={$this->unit->id}")->assertOk();
        $this->actingAs($this->koordinator)->get("/reports/export-excel?unit_id={$this->unit->id}")->assertOk();
        $this->actingAs($this->pic)->get("/reports/export-excel?unit_id={$this->unit->id}")->assertForbidden();
    }

    private function panduanValue($sheet, string $label): ?string
    {
        foreach ($sheet->getRowIterator() as $row) {
            $r = $row->getRowIndex();

            if (trim((string) $sheet->getCell("B{$r}")->getValue()) === $label) {
                return (string) $sheet->getCell("C{$r}")->getValue();
            }
        }

        return null;
    }

    public function test_panduan_sheet_carries_per_unit_metadata(): void
    {
        $today = now()->isoFormat('D MMMM Y');

        $wb1 = $this->loadResponseWorkbook(
            $this->actingAs($this->admin)->get("/reports/export-excel?unit_id={$this->unit->id}")
        );

        try {
            $p1 = $wb1->getSheetByName('Panduan');
            $this->assertSame($this->unit->nama, $this->panduanValue($p1, 'Unit Kerja'));
            $this->assertSame($today, $this->panduanValue($p1, 'Tanggal Export'));
            $this->assertStringContainsString($this->admin->name, (string) $this->panduanValue($p1, 'Diekspor Oleh'));
            $this->assertNotEmpty($this->panduanValue($p1, 'Periode Data'));

            // Template version rows are preserved, never overwritten.
            $this->assertSame('08 September 2026', $this->panduanValue($p1, 'Dibuat:'));
            $this->assertSame('1.0', $this->panduanValue($p1, 'Versi Template:'));

            // Structure description follows the two framework sheets.
            $structure = (string) $this->panduanValue($p1, 'Struktur Sheet');
            $this->assertStringContainsString('ISO IEC 27001', $structure);
            $this->assertStringContainsString('ISO IEC 27701', $structure);
        } finally {
            $wb1->disconnectWorksheets();
        }

        $wb2 = $this->loadResponseWorkbook(
            $this->actingAs($this->admin)->get("/reports/export-excel?unit_id={$this->otherUnit->id}")
        );

        try {
            $p2 = $wb2->getSheetByName('Panduan');
            $this->assertSame($this->otherUnit->nama, $this->panduanValue($p2, 'Unit Kerja'));
            $this->assertSame($today, $this->panduanValue($p2, 'Tanggal Export'));
        } finally {
            $wb2->disconnectWorksheets();
        }
    }
}
