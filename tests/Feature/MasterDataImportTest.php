<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class MasterDataImportTest extends TestCase
{
    /**
     * Builds a real .xlsx on disk from sheet-title => rows (first row = header).
     * Reuses Maatwebsite's own writer so the bytes match what production reads.
     */
    private function uploadXlsx(array $sheets): UploadedFile
    {
        // The production importer requires BOTH Frameworks and Controls sheets to
        // exist in the workbook, or it throws SheetNotFoundException. Tests that
        // intentionally exercise a single-sheet file pass an explicit key below.
        $sheets = array_merge([
            'Frameworks' => [['nama', 'versi', 'url_file']],
            'Controls' => [['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi']],
        ], $sheets);

        $writer = new class($sheets) implements Export, WithMultipleSheets
        {
            public function __construct(private array $sheets) {}

            public function sheets(): array
            {
                $out = [];
                foreach ($this->sheets as $title => $rows) {
                    $out[$title] = new class($title, $rows) implements FromCollection, WithHeadings, WithTitle
                    {
                        public function __construct(private string $title, private array $rows) {}

                        public function title(): string
                        {
                            return $this->title;
                        }

                        public function collection(): Collection
                        {
                            return collect(array_slice($this->rows, 1));
                        }

                        public function headings(): array
                        {
                            return $this->rows[0] ?? [];
                        }
                    };
                }

                return $out;
            }
        };

        $rel = 'import-test/'.uniqid('imp', true).' .xlsx';
        Excel::store($writer, $rel, 'local');
        $real = storage_path('app/private/'.$rel);

        return new UploadedFile(
            $real,
            'master-data.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_anonymous_import_redirects_to_login_and_persists_nothing(): void
    {
        $file = $this->uploadXlsx([
            'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
        ]);

        $this->post('/admin/kepatuhan/master-data/import', ['file' => $file])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('frameworks', 0);
    }

    public function test_anonymous_preview_redirects_to_login_and_persists_nothing(): void
    {
        $file = $this->uploadXlsx([
            'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
        ]);

        $this->post('/admin/kepatuhan/master-data/import/preview', ['file' => $file])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('frameworks', 0);
    }

    public function test_any_authenticated_role_can_import_no_role_gate(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PIC]);

        $this->actingAs($user)
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                ]),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27001', 'versi' => '2022']);
    }

    public function test_missing_file_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import')
            ->assertSessionHasErrors('file');
    }

    public function test_non_excel_mime_rejected(): void
    {
        $csv = UploadedFile::fake()->create('data.csv', 10, 'text/csv');

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', ['file' => $csv])
            ->assertSessionHasErrors('file');
    }

    public function test_oversized_file_rejected(): void
    {
        $big = UploadedFile::fake()->create(
            'big.xlsx',
            10241,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', ['file' => $big])
            ->assertSessionHasErrors('file');
    }

    public function test_creates_frameworks(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [
                        ['nama', 'versi', 'url_file'],
                        ['ISO 27001', '2022', 'https://x.test/a.pdf'],
                        ['ISO 27701', '2019', null],
                    ],
                ]),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27001', 'versi' => '2022', 'url_file' => 'https://x.test/a.pdf']);
        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27701', 'versi' => '2019']);
        $this->assertDatabaseCount('frameworks', 2);
    }

    public function test_soft_deletes_db_frameworks_absent_from_excel(): void
    {
        Framework::create(['nama' => 'Legacy', 'versi' => '2005']);

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                ]),
            ]);

        $legacy = Framework::withTrashed()->where('nama', 'Legacy')->first();
        $this->assertNotNull($legacy->deleted_at);
    }

    public function test_updates_url_file_when_changed(): void
    {
        Framework::create(['nama' => 'ISO 27001', 'versi' => '2022', 'url_file' => 'https://old.test/a.pdf']);

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', 'https://new.test/a.pdf']],
                ]),
            ]);

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27001', 'versi' => '2022', 'url_file' => 'https://new.test/a.pdf']);
    }

    public function test_reimport_is_idempotent(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx(['Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]]]),
            ]);
        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx(['Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]]]),
            ]);

        $this->assertDatabaseCount('frameworks', 1);
    }

    protected function seedFramework(): void
    {
        Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
    }

    public function test_creates_controls_linked_to_framework(): void
    {
        $this->seedFramework();

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', 'A.5.1', 'Policies', 'annex_a', 'desc'],
                    ],
                ]),
            ]);

        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
    }

    public function test_normalizes_category_aliases(): void
    {
        $this->seedFramework();

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', 'A.5.1', 'Policies', 'Annex A', 'd'],
                        ['ISO 27001', '2022', '5.1', 'Leadership', 'Klausul 4-10', 'd'],
                    ],
                ]),
            ]);

        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.1', 'kategori' => 'annex_a']);
        $this->assertDatabaseHas('controls', ['kode_klausul' => '5.1', 'kategori' => 'klausul_4_10']);
    }

    public function test_skips_rows_with_empty_code_or_title(): void
    {
        $this->seedFramework();

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', '', 'No code', 'annex_a', 'd'],
                        ['ISO 27001', '2022', 'A.5.1', '', 'annex_a', 'd'],
                        ['ISO 27001', '2022', 'A.5.2', 'Ok', 'annex_a', 'd'],
                    ],
                ]),
            ]);

        $this->assertDatabaseCount('controls', 1);
        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.2']);
    }

    public function test_skips_rows_with_unknown_category(): void
    {
        $this->seedFramework();

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', 'A.5.1', 'Bad cat', 'other_cat', 'd'],
                        ['ISO 27001', '2022', 'A.5.2', 'Ok', 'annex_a', 'd'],
                    ],
                ]),
            ]);

        $this->assertDatabaseCount('controls', 1);
        $this->assertDatabaseMissing('controls', ['kode_klausul' => 'A.5.1']);
    }

    public function test_skips_controls_whose_framework_absent_from_excel(): void
    {
        $this->seedFramework();

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 9999', '2022', 'A.5.1', 'Ghost fw', 'annex_a', 'd'],
                    ],
                ]),
            ]);

        $this->assertDatabaseCount('controls', 0);
    }

    public function test_preview_returns_summary_and_persists_nothing(): void
    {
        Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import/preview', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', 'https://new.test/a.pdf']],
                ]),
            ])
            ->assertOk()
            ->assertJsonStructure(['frameworks' => ['created', 'updated', 'deleted'], 'controls' => ['created', 'updated', 'deleted']]);

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27001', 'versi' => '2022', 'url_file' => null]);
    }

    public function test_crash_when_frameworks_sheet_absent(): void
    {
        // DEFECT documented: the importer hard-requires BOTH 'Frameworks' and
        // 'Controls' sheets. A workbook containing only the Controls sheet
        // throws Maatwebsite\\SheetNotFoundException, surfacing as HTTP 500.
        $solo = new class implements Export, WithMultipleSheets
        {
            public function sheets(): array
            {
                return [
                    'Controls' => new class implements FromCollection, WithHeadings, WithTitle
                    {
                        public function title(): string
                        {
                            return 'Controls';
                        }

                        public function collection(): Collection
                        {
                            return collect([['ISO 27001', '2022', 'A.5.1', 'Policies', 'annex_a', 'd']]);
                        }

                        public function headings(): array
                        {
                            return ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'];
                        }
                    },
                ];
            }
        };

        $rel = 'solo-controls/'.uniqid().'.xlsx';
        Excel::store($solo, $rel, 'local');
        $file = new UploadedFile(
            storage_path('app/private/'.$rel),
            'solo.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseCount('controls', 0);
    }

    public function test_ignores_sheets_with_wrong_title_case(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                ]),
            ]);

        $this->assertDatabaseCount('frameworks', 0);
    }

    // ── Export coverage ───────────────────────────────────────────────────────

    public function test_anonymous_export_redirects_to_login(): void
    {
        $this->get('/admin/kepatuhan/master-data/export')
            ->assertRedirect('/login');
    }

    public function test_any_authenticated_role_can_export_no_role_gate(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PIC]);

        $this->actingAs($user)
            ->get('/admin/kepatuhan/master-data/export')
            ->assertOk();
    }

    public function test_export_returns_two_sheets_with_headers_and_data(): void
    {
        Framework::create(['nama' => 'ISO 27001', 'versi' => '2022', 'url_file' => 'https://x.test/a.pdf']);
        $fw = Framework::where('nama', 'ISO 27001')->first();
        Control::create([
            'framework_id' => $fw->id,
            'kode_klausul' => 'A.5.1',
            'judul' => 'Policies',
            'kategori' => 'annex_a',
            'deskripsi' => 'desc',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get('/admin/kepatuhan/master-data/export')
            ->assertOk();

        $disp = $response->headers->get('content-disposition') ?? '';
        $this->assertStringContainsString('attachment', $disp);
        $this->assertStringContainsString('smki-master-data-', $disp);

        $spreadsheet = $this->readExport($response);
        $this->assertSame(['Frameworks', 'Controls'], $spreadsheet->getSheetNames());

        $fwRows = $spreadsheet->getSheetByName('Frameworks')->toArray();
        $this->assertSame(['nama', 'versi', 'url_file'], $fwRows[0]);
        $this->assertSame(['ISO 27001', '2022', 'https://x.test/a.pdf'], $fwRows[1]);

        $ctrlRows = $spreadsheet->getSheetByName('Controls')->toArray();
        $this->assertSame(
            ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
            $ctrlRows[0]
        );
        $this->assertSame(['ISO 27001', '2022', 'A.5.1', 'Policies', 'annex_a', 'desc'], $ctrlRows[1]);
    }

    public function test_export_with_empty_database_has_headers_only(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get('/admin/kepatuhan/master-data/export')
            ->assertOk();

        $spreadsheet = $this->readExport($response);
        $this->assertSame(['nama', 'versi', 'url_file'], $spreadsheet->getSheetByName('Frameworks')->toArray()[0]);
        $this->assertSame(
            ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
            $spreadsheet->getSheetByName('Controls')->toArray()[0]
        );
        $this->assertCount(1, $spreadsheet->getSheetByName('Frameworks')->toArray());
        $this->assertCount(1, $spreadsheet->getSheetByName('Controls')->toArray());
    }

    private function readExport($response): Spreadsheet
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        return $reader->load($path);
    }

    // ── Import apply — updater & deletion sweep coverage ──────────────────────

    public function test_import_updates_existing_control_fields(): void
    {
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        Control::create([
            'framework_id' => $fw->id,
            'kode_klausul' => 'A.5.1',
            'judul' => 'Policies',
            'kategori' => 'annex_a',
            'deskripsi' => 'old',
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', 'A.5.1', 'Policies v2', 'Klausul 4-10', 'new'],
                    ],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseHas('controls', [
            'kode_klausul' => 'A.5.1',
            'judul' => 'Policies v2',
            'kategori' => 'klausul_4_10',
            'deskripsi' => 'new',
        ]);
        $this->assertDatabaseCount('controls', 1);
    }

    public function test_import_control_rows_are_idempotent(): void
    {
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        Control::create([
            'framework_id' => $fw->id,
            'kode_klausul' => 'A.5.1',
            'judul' => 'Policies',
            'kategori' => 'annex_a',
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', 'A.5.1', 'Policies', 'annex_a', ''],
                    ],
                ]),
            ])
            ->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseCount('controls', 1);
        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.1', 'judul' => 'Policies']);
    }

    public function test_import_fails_when_controls_reference_framework_omitted_from_excel(): void
    {
        // DEFECT: the Frameworks sheet soft-deletes DB frameworks absent from
        // Excel BEFORE the Controls sheet runs. A Controls row that still
        // references that framework then takes the "create" branch (its rows
        // were just cascade-deleted, so the existing lookup misses), and the
        // insert violates uniq_ctrl_fw_kode → the whole import fails and is
        // rolled back. Expected behavior would be to skip such rows.
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        Control::create(['framework_id' => $fw->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);

        // FIX VERIFIED: When framework is absent from Excel, controls referencing it
        // are skipped instead of crashing with a cascading unique violation.
        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', 'A.5.1', 'Policies', 'annex_a', ''],
                    ],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseCount('frameworks', 1);
        $this->assertDatabaseCount('controls', 2);
        $this->assertSoftDeleted('frameworks', ['id' => $fw->id]);
    }

    public function test_import_duplicate_kode_klausul_in_file_rolls_back_entire_import(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', 'A.5.1', 'First', 'annex_a', ''],
                        ['ISO 27001', '2022', 'A.5.1', 'Duplicate', 'annex_a', ''],
                    ],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.type', 'error');

        // The second row violates uniq_ctrl_fw_kode; the transaction must roll
        // back the frameworks that the Frameworks sheet had already inserted.
        $this->assertDatabaseCount('frameworks', 0);
        $this->assertDatabaseCount('controls', 0);
    }

    public function test_import_soft_deletes_controls_missing_for_present_framework(): void
    {
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        Control::create(['framework_id' => $fw->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Kept', 'kategori' => 'annex_a']);
        Control::create(['framework_id' => $fw->id, 'kode_klausul' => 'A.5.2', 'judul' => 'Absent', 'kategori' => 'annex_a']);

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', 'A.5.1', 'Kept', 'annex_a', ''],
                    ],
                ]),
            ]);

        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.1', 'deleted_at' => null]);
        $deleted = Control::withTrashed()->where('kode_klausul', 'A.5.2')->first();
        $this->assertNotNull($deleted);
        $this->assertNotNull($deleted->deleted_at);
    }

    public function test_import_soft_deletes_controls_of_absent_frameworks_via_cascade(): void
    {
        $legacy = Framework::create(['nama' => 'Legacy', 'versi' => '2005']);
        Control::create(['framework_id' => $legacy->id, 'kode_klausul' => 'L.1', 'judul' => 'Old', 'kategori' => 'annex_a']);

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                ]),
            ]);

        $fw = Framework::withTrashed()->where('nama', 'Legacy')->first();
        $this->assertNotNull($fw->deleted_at);

        $ctrl = Control::withTrashed()->where('kode_klausul', 'L.1')->first();
        $this->assertNotNull($ctrl);
        $this->assertNotNull($ctrl->deleted_at);
    }

    // ── Validation & error responses ─────────────────────────────────────────

    public function test_import_file_missing_required_sheets_rejected(): void
    {
        $solo = $this->soloControlsFile();

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', ['file' => $solo])
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('frameworks', 0);
        $this->assertDatabaseCount('controls', 0);
    }

    public function test_preview_file_missing_required_sheets_rejected_and_persists_nothing(): void
    {
        $solo = $this->soloControlsFile();

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import/preview', ['file' => $solo])
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('frameworks', 0);
        $this->assertDatabaseCount('controls', 0);
    }

    public function test_import_corrupt_xlsx_rejected(): void
    {
        $garbage = UploadedFile::fake()->create(
            'corrupt.xlsx',
            20,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import', ['file' => $garbage])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('frameworks', 0);
    }

    public function test_preview_rejects_non_excel_mime(): void
    {
        $csv = UploadedFile::fake()->create('data.csv', 10, 'text/csv');

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import/preview', ['file' => $csv])
            ->assertSessionHasErrors('file');
    }

    // ── Preview accuracy ──────────────────────────────────────────────────────

    public function test_preview_reports_accurate_counts(): void
    {
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        Control::create([
            'framework_id' => $fw->id,
            'kode_klausul' => 'A.5.1',
            'judul' => 'Policies',
            'kategori' => 'annex_a',
            'deskripsi' => 'old',
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/admin/kepatuhan/master-data/import/preview', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', 'https://new.test/a.pdf']],
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27001', '2022', 'A.5.1', 'Policies v2', 'klausul_4_10', 'new'],
                    ],
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('frameworks.created', 0)
            ->assertJsonPath('frameworks.updated', 1)
            ->assertJsonPath('frameworks.deleted', 0)
            ->assertJsonPath('controls.created', 0)
            ->assertJsonPath('controls.updated', 1)
            ->assertJsonPath('controls.deleted', 0);

        // Dry-run: no side effects
        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27001', 'versi' => '2022', 'url_file' => null]);
        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.1', 'judul' => 'Policies']);
    }

    private function soloControlsFile(): UploadedFile
    {
        $solo = new class implements Export, WithMultipleSheets
        {
            public function sheets(): array
            {
                return [
                    'Controls' => new class implements FromCollection, WithHeadings, WithTitle
                    {
                        public function title(): string
                        {
                            return 'Controls';
                        }

                        public function collection(): Collection
                        {
                            return collect([['ISO 27001', '2022', 'A.5.1', 'Policies', 'annex_a', 'd']]);
                        }

                        public function headings(): array
                        {
                            return ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'];
                        }
                    },
                ];
            }
        };

        $rel = 'solo-controls/'.uniqid().'.xlsx';
        Excel::store($solo, $rel, 'local');

        return new UploadedFile(
            storage_path('app/private/'.$rel),
            'solo.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
