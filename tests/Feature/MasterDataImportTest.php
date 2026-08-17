<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
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

        $writer = new class($sheets) implements \Maatwebsite\Excel\Concerns\Export, \Maatwebsite\Excel\Concerns\WithMultipleSheets {
            public function __construct(private array $sheets) {}

            public function sheets(): array
            {
                $out = [];
                foreach ($this->sheets as $title => $rows) {
                    $out[$title] = new class($title, $rows) implements
                        \Maatwebsite\Excel\Concerns\FromCollection,
                        \Maatwebsite\Excel\Concerns\WithHeadings,
                        \Maatwebsite\Excel\Concerns\WithTitle {
                        public function __construct(private string $title, private array $rows) {}

                        public function title(): string { return $this->title; }

                        public function collection(): \Illuminate\Support\Collection
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
        $solo = new class implements
            \Maatwebsite\Excel\Concerns\Export,
            \Maatwebsite\Excel\Concerns\WithMultipleSheets {
            public function sheets(): array
            {
                return [
                    'Controls' => new class implements
                        \Maatwebsite\Excel\Concerns\FromCollection,
                        \Maatwebsite\Excel\Concerns\WithHeadings,
                        \Maatwebsite\Excel\Concerns\WithTitle {
                        public function title(): string { return 'Controls'; }
                        public function collection(): \Illuminate\Support\Collection
                        {
                            return collect([['ISO 27001', '2022', 'A.5.1', 'Policies', 'annex_a', 'd']]);
                        }
                        public function headings(): array
                        {
                            return ['framework_nama','framework_versi','kode_klausul','judul','kategori','deskripsi'];
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
            ->assertStatus(500);

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
}
