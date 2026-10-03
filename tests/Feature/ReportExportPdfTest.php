<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkUnit;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

/**
 * Scope: docs/FUNCTIONAL_SPEC.md §7 — GET /reports/export-pdf (US-E1).
 *
 * Covers the role×type 403 matrix, audit-ready 501 placeholder, audit-log
 * write, boundary validation, unit scoping and response-hygiene contracts.
 */
class ReportExportPdfTest extends TestCase
{
    private User $superadmin;

    private User $admin;

    private User $koordinator;

    private User $auditor;

    private User $pic;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        Pdf::fake();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Pusat Data Komdigi']);
        $this->unitB = WorkUnit::factory()->create(['nama' => 'Badan Evaluasi Kepatuhan']);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->admin = User::factory()->create(['role' => 'admin_kepatuhan', 'unit_id' => $this->unitA->id]);
        $this->koordinator = User::factory()->create(['role' => 'koordinator_smki', 'unit_id' => $this->unitA->id]);
        $this->auditor = User::factory()->create(['role' => 'auditor']);
        $this->pic = User::factory()->create(['role' => 'pic', 'unit_id' => $this->unitA->id]);
    }

    private function userForRole(string $role): User
    {
        return match ($role) {
            'superadmin' => $this->superadmin,
            'admin_kepatuhan' => $this->admin,
            'koordinator_smki' => $this->koordinator,
            'auditor' => $this->auditor,
            'pic' => $this->pic,
        };
    }

    /**
     * US-E1 access matrix. audit-ready is a 501 placeholder reachable only by
     * superadmin; every other unauthorized pair must fail closed with 403
     * *before* the 501 branch is reached.
     */
    public static function roleTypeMatrix(): array
    {
        return [
            'superadmin quick-summary' => ['superadmin', 'quick-summary', 200],
            'superadmin executive' => ['superadmin', 'executive', 200],
            'superadmin audit-ready' => ['superadmin', 'audit-ready', 501],

            'admin_kepatuhan quick-summary' => ['admin_kepatuhan', 'quick-summary', 200],
            'admin_kepatuhan executive' => ['admin_kepatuhan', 'executive', 403],
            'admin_kepatuhan audit-ready' => ['admin_kepatuhan', 'audit-ready', 403],

            'koordinator_smki quick-summary' => ['koordinator_smki', 'quick-summary', 403],
            'koordinator_smki executive' => ['koordinator_smki', 'executive', 200],
            'koordinator_smki audit-ready' => ['koordinator_smki', 'audit-ready', 403],

            'auditor quick-summary' => ['auditor', 'quick-summary', 200],
            'auditor executive' => ['auditor', 'executive', 200],
            'auditor audit-ready' => ['auditor', 'audit-ready', 403],

            'pic quick-summary' => ['pic', 'quick-summary', 403],
            'pic executive' => ['pic', 'executive', 403],
            'pic audit-ready' => ['pic', 'audit-ready', 403],
        ];
    }

    #[DataProvider('roleTypeMatrix')]
    public function test_role_by_type_access_matrix(string $role, string $type, int $expected): void
    {
        $response = $this->actingAs($this->userForRole($role))
            ->get("/reports/export-pdf?type={$type}");

        $response->assertStatus($expected);

        if ($expected === 200) {
            $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        } else {
            // Error responses must not be served as a report document. Assert on
            // the content type: a debug-mode error page legitimately echoes test
            // source, so sniffing the body for '%PDF-' is unreliable.
            $this->assertNotSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertNull($response->headers->get('Content-Disposition'));
        }
    }

    public function test_guest_cannot_export_pdf(): void
    {
        $response = $this->get('/reports/export-pdf?type=quick-summary');

        $response->assertRedirect('/login');
        $this->assertNotSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_guest_json_request_returns_401_not_a_report(): void
    {
        $this->getJson('/reports/export-pdf?type=executive')->assertUnauthorized();
    }

    public function test_audit_ready_placeholder_returns_501_for_superadmin(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/reports/export-pdf?type=audit-ready');

        $response->assertStatus(501);
    }

    public function test_denied_export_writes_no_audit_log_row(): void
    {
        AuditLog::query()->delete();

        foreach (['quick-summary', 'executive', 'audit-ready'] as $type) {
            $this->actingAs($this->pic)->get("/reports/export-pdf?type={$type}")->assertForbidden();
        }

        $this->assertSame(0, AuditLog::where('entity_type', 'Report')->count());
    }

    public function test_web_pdf_export_writes_audit_log_row(): void
    {
        AuditLog::query()->delete();

        $this->actingAs($this->admin)->get('/reports/export-pdf?type=quick-summary')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Report',
            'aksi' => 'export',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_web_pdf_export_writes_one_audit_log_row_per_export(): void
    {
        AuditLog::query()->delete();

        $this->actingAs($this->admin)->get('/reports/export-pdf?type=quick-summary')->assertOk();
        $this->actingAs($this->admin)->get('/reports/export-pdf?type=quick-summary')->assertOk();

        $this->assertSame(2, AuditLog::where('entity_type', 'Report')->where('aksi', 'export')->count());
    }

    public function test_invalid_periode_is_rejected_instead_of_crashing(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->getJson('/reports/export-pdf?type=quick-summary&periode=not-a-month');

        $response->assertStatus(422);
    }

    public function test_out_of_range_periode_is_rejected_instead_of_crashing(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->getJson('/reports/export-pdf?type=quick-summary&periode=2026-13');

        $response->assertStatus(422);
    }

    public function test_invalid_date_range_is_rejected_instead_of_crashing(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->getJson('/reports/export-pdf?type=quick-summary&start_date=xx&end_date=yy');

        $response->assertStatus(422);
    }

    public function test_invalid_type_is_rejected_with_422_for_json(): void
    {
        $this->actingAs($this->superadmin)
            ->getJson('/reports/export-pdf?type=bogus')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_invalid_print_mode_is_rejected_with_422_for_json(): void
    {
        $this->actingAs($this->superadmin)
            ->getJson('/reports/export-pdf?print_mode=weird')
            ->assertStatus(422)
            ->assertJsonValidationErrors('print_mode');
    }

    public function test_missing_type_defaults_to_quick_summary(): void
    {
        $this->actingAs($this->admin)->get('/reports/export-pdf')->assertOk();
        $this->actingAs($this->koordinator)->get('/reports/export-pdf')->assertForbidden();
    }

    public function test_unit_id_scopes_the_report_to_the_requested_unit(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get("/reports/export-pdf?type=executive&unit_id={$this->unitB->id}");

        $response->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');

        $this->assertStringContainsString('badan_evaluasi_kepatuhan', $disposition);
        $this->assertStringNotContainsString('pusat_data_komdigi', $disposition);
    }

    public function test_report_without_unit_id_is_org_wide(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/reports/export-pdf?type=executive');

        $response->assertOk();
        $this->assertStringContainsString(
            'Kementerian_Komdigi',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_per_month_print_mode_with_a_single_month_returns_pdf_not_zip(): void
    {
        $response = $this->actingAs($this->superadmin)->get(
            '/reports/export-pdf?type=executive&start_date=2026-07-01&end_date=2026-07-31&print_mode=per_month'
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_response_is_not_cacheable_and_hides_actor_identity(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/export-pdf?type=quick-summary');

        $response->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);

        $headers = implode(' ', array_map(
            fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v,
            $response->headers->all()
        ));

        $this->assertStringNotContainsString($this->admin->email, $headers);
    }

    public function test_rendered_report_does_not_expose_actor_email(): void
    {
        $this->actingAs($this->admin)->get('/reports/export-pdf?type=quick-summary')->assertOk();

        Pdf::assertDontSee($this->admin->email);
    }

    /*
    |--------------------------------------------------------------------------
    | Multi-month (per_month ZIP) branch — ReportGeneratorService::exportMultiMonthZip
    |--------------------------------------------------------------------------
    */

    public function test_per_month_print_mode_across_a_multi_month_range_returns_a_zip(): void
    {
        $response = $this->actingAs($this->superadmin)->get(
            "/reports/export-pdf?type=executive&unit_id={$this->unitA->id}"
            .'&start_date=2026-05-01&end_date=2026-07-31&print_mode=per_month'
        );

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'Laporan_Eksekutif_SMKI_pusat_data_komdigi_Mei-Juli_2026.zip',
            (string) $response->headers->get('Content-Disposition')
        );

        $this->assertSame([
            '01_Laporan_Eksekutif_SMKI_pusat_data_komdigi_Mei_2026.pdf',
            '02_Laporan_Eksekutif_SMKI_pusat_data_komdigi_Juni_2026.pdf',
            '03_Laporan_Eksekutif_SMKI_pusat_data_komdigi_Juli_2026.pdf',
        ], $this->zipEntryNames((string) $response->getContent()));
    }

    public function test_zip_branch_honours_the_same_role_matrix_as_the_single_pdf_branch(): void
    {
        $range = '&start_date=2026-05-01&end_date=2026-07-31&print_mode=per_month';

        $this->actingAs($this->admin)
            ->get("/reports/export-pdf?type=executive{$range}")
            ->assertForbidden();

        $this->actingAs($this->koordinator)
            ->get("/reports/export-pdf?type=quick-summary{$range}")
            ->assertForbidden();

        $this->actingAs($this->auditor)
            ->get("/reports/export-pdf?type=quick-summary{$range}")
            ->assertOk();

        $this->actingAs($this->superadmin)
            ->get("/reports/export-pdf?type=audit-ready{$range}")
            ->assertStatus(501);
    }

    public function test_pic_cannot_reach_the_zip_branch(): void
    {
        $this->actingAs($this->pic)
            ->get('/reports/export-pdf?type=quick-summary&start_date=2026-05-01'
                .'&end_date=2026-07-31&print_mode=per_month')
            ->assertForbidden();
    }

    public function test_zip_is_not_cacheable(): void
    {
        $cacheControl = (string) $this->actingAs($this->superadmin)
            ->get('/reports/export-pdf?type=executive&start_date=2026-05-01'
                .'&end_date=2026-07-31&print_mode=per_month')
            ->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
    }

    /*
    |--------------------------------------------------------------------------
    | Boundary + graceful-degradation contracts
    |--------------------------------------------------------------------------
    */

    public function test_non_integer_unit_id_is_rejected_with_422(): void
    {
        $this->actingAs($this->superadmin)
            ->getJson('/reports/export-pdf?type=executive&unit_id=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('unit_id');
    }

    public function test_unknown_unit_id_still_renders_a_pdf_scoped_to_that_id(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get('/reports/export-pdf?type=executive&unit_id=999999');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertStringContainsString(
            'Unit_999999',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_reversed_date_range_falls_back_to_a_single_pdf_instead_of_erroring(): void
    {
        $response = $this->actingAs($this->superadmin)->get(
            '/reports/export-pdf?type=executive&start_date=2026-07-01&end_date=2026-05-01&print_mode=per_month'
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * @return array<int, string>
     */
    private function zipEntryNames(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'smki_test_zip_');
        file_put_contents($path, $binary);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'export-pdf must return a readable ZIP archive');

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
            $this->assertStringStartsWith(
                '%PDF-',
                (string) $zip->getFromIndex($i),
                'each ZIP member must be a real PDF document'
            );
        }

        $zip->close();
        unlink($path);

        return $names;
    }
}
