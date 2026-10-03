<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * §10 extras + §11 restore paths in one place: master-data import edge branches
 * (unmappable domain_peran, preview/apply deletion agreement, single-workbook
 * create, read-only denial) plus the restore HTTP gates (anonymous, foreign
 * PIC, parent direction, auditor/koordinator gaps, trashed-parent, 404).
 * Merged from Qa10ExtrasImportPreviewTest + Qa10Section11RestorePathsTest.
 */
class Qa10ExtrasTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $nama, ?WorkUnit $parent = null): WorkUnit
    {
        return WorkUnit::create(['nama' => $nama, 'parent_id' => $parent?->id]);
    }

    /**
     * @return array{0: WorkUnit, 1: ChecklistEntry}
     */
    private function entryFor(WorkUnit $unit, string $kode = 'A.5.1'): array
    {
        $fw = Framework::create(['nama' => 'ISO 27001 '.$unit->id, 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => $kode, 'judul' => 'Klausul '.$kode, 'kategori' => 'teknologi',
        ]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $entry = ChecklistEntry::create([
            'control_id' => $control->id,
            'unit_id' => $unit->id,
            'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $entry->delete();

        return [$unit, $entry];
    }

    /**
     * Real .xlsx bytes, written by Maatwebsite's own writer, with the two
     * required sheets always present.
     */
    private function uploadXlsx(array $sheets): UploadedFile
    {
        $sheets = array_merge([
            'Frameworks' => [['nama', 'versi', 'url_file']],
            'Controls' => [['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi', 'domain_peran']],
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

        $rel = 'qa10-import/'.uniqid('imp', true).'.xlsx';
        Excel::store($writer, $rel, 'local');

        return new UploadedFile(
            storage_path('app/private/'.$rel),
            'master-data.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function superadmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
    }

    // ── domain_peran mapping ───────────────────────────────────────────────

    /**
     * An unmappable `domain_peran` value is normalised to null and the row is
     * kept — the importer never persists the raw unknown value.
     */
    public function test_qa10_import_skips_rows_whose_domain_peran_cannot_be_mapped(): void
    {
        $fw = Framework::create(['nama' => 'ISO 27701', 'versi' => '2025']);

        $this->actingAs($this->superadmin())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27701', '2025', null]],
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi', 'domain_peran'],
                        ['ISO 27701', '2025', '7.2.1', 'Tujuan', 'organisasional', 'd1', 'controller'],
                        ['ISO 27701', '2025', '8.2.1', 'Pemrosesan', 'teknologi', 'd2', 'processor'],
                        ['ISO 27701', '2025', '5.1.1', 'Penjaga', 'organisasional', 'd3', 'moderator'],
                    ],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseHas('controls', ['kode_klausul' => '7.2.1', 'domain_peran' => 'controller']);
        $this->assertDatabaseHas('controls', ['kode_klausul' => '8.2.1', 'domain_peran' => 'processor']);
        $this->assertDatabaseHas('controls', ['kode_klausul' => '5.1.1', 'domain_peran' => null]);
        $this->assertSame(3, Control::count());
    }

    /**
     * GAP (data loss): the importer cannot tell "column absent" from "cell
     * blank" — `$row['domain_peran'] ?? ''` (SmkiMasterDataImport.php:203) both
     * fall into the `'' => null` arm, and the update branch always writes all
     * four fields (:300-305). A workbook without the `domain_peran` heading
     * (older template, hand-made sheet, export taken before the column existed)
     * therefore silently NULLs every existing controller/processor designation
     * on re-upload. Whether full-state sync is intended is a product call; the
     * current behaviour is pinned here.
     */
    public function test_qa10_gap_import_nulls_domain_peran_when_the_excel_column_is_absent(): void
    {
        $fw = Framework::create(['nama' => 'ISO 27701', 'versi' => '2025']);
        $fw->controls()->create([
            'kode_klausul' => '7.2.1', 'judul' => 'Tujuan', 'kategori' => 'organisasional',
            'domain_peran' => 'controller',
        ]);

        // A workbook whose Controls sheet has no domain_peran heading at all.
        $this->actingAs($this->superadmin())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27701', '2025', null]],
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi'],
                        ['ISO 27701', '2025', '7.2.1', 'Tujuan', 'organisasional', 'd1'],
                    ],
                ]),
            ])
            ->assertRedirect();

        $this->assertNull(
            Control::query()->where('kode_klausul', '7.2.1')->firstOrFail()->domain_peran,
            'GAP: domain_peran yang sudah terisi dihapus hanya karena kolomnya tidak ada di Excel'
        );
    }

    // ── preview vs apply: deletions ───────────────────────────────────────

    public function test_qa10_preview_reports_deletions_and_persists_nothing(): void
    {
        $keep = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $drop = Framework::create(['nama' => 'ISO 9001', 'versi' => '2015']);
        $keptControl = $keep->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);
        $droppedControl = $drop->controls()->create([
            'kode_klausul' => 'Q.1.1', 'judul' => 'Legacy', 'kategori' => 'organisasional',
        ]);

        $this->actingAs($this->superadmin())
            ->post('/admin/kepatuhan/master-data/import/preview', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi', 'domain_peran'],
                        ['ISO 27001', '2022', 'A.5.1', 'Policies', 'teknologi', '', ''],
                    ],
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('frameworks.deleted', 1)
            ->assertJsonPath('frameworks.created', 0)
            ->assertJsonPath('frameworks.updated', 0)
            ->assertJsonPath('controls.deleted', 0);

        // Dry run: nothing is trashed.
        $this->assertDatabaseHas('frameworks', ['id' => $drop->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('controls', ['id' => $droppedControl->id, 'deleted_at' => null]);
        $this->assertSame('Policies', $keptControl->fresh()->judul);
    }

    public function test_qa10_apply_then_preview_agree_on_the_outcome(): void
    {
        Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $admin = $this->superadmin();

        $file = fn () => $this->uploadXlsx([
            'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', 'https://a.test/a.pdf']],
            'Controls' => [
                ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi', 'domain_peran'],
                ['ISO 27001', '2022', 'A.5.1', 'Policies', 'teknologi', 'd1', ''],
            ],
        ]);

        $preview = $this->actingAs($admin)
            ->post('/admin/kepatuhan/master-data/import/preview', ['file' => $file()])
            ->assertOk()
            ->assertJsonPath('frameworks.updated', 1)
            ->assertJsonPath('controls.created', 1);

        $this->actingAs($admin)
            ->post('/admin/kepatuhan/master-data/import', ['file' => $file()])
            ->assertRedirect()
            ->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27001', 'url_file' => 'https://a.test/a.pdf']);
        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.1']);

        // Re-running the identical file must report "no change" everywhere.
        $this->actingAs($admin)
            ->post('/admin/kepatuhan/master-data/import/preview', ['file' => $file()])
            ->assertOk()
            ->assertJsonPath('frameworks.created', 0)
            ->assertJsonPath('frameworks.updated', 0)
            ->assertJsonPath('frameworks.deleted', 0)
            ->assertJsonPath('controls.created', 0)
            ->assertJsonPath('controls.updated', 0)
            ->assertJsonPath('controls.deleted', 0);
    }

    public function test_qa10_import_soft_deletes_controls_of_a_framework_dropped_from_the_excel_sheet(): void
    {
        $keep = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $drop = Framework::create(['nama' => 'ISO 9001', 'versi' => '2015']);
        $drop->controls()->create([
            'kode_klausul' => 'Q.1.1', 'judul' => 'Legacy', 'kategori' => 'organisasional',
        ]);

        $this->actingAs($this->superadmin())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO 27001', '2022', null]],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseHas('frameworks', ['id' => $keep->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('frameworks', ['id' => $drop->id]);
        $this->assertSoftDeleted('controls', ['kode_klausul' => 'Q.1.1']);
    }

    /**
     * The "2-sheet unified" claim in its strictest form: one workbook, one pass,
     * zero pre-existing master data. The Controls sheet must resolve the
     * framework the Frameworks sheet inserted moments earlier
     * (SmkiMasterDataImport.php:130) — otherwise every control row is silently
     * skipped and the import still reports success.
     */
    public function test_qa10_single_workbook_creates_framework_and_its_controls_in_one_pass(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/kepatuhan/master-data/import', [
                'file' => $this->uploadXlsx([
                    'Frameworks' => [
                        ['nama', 'versi', 'url_file'],
                        ['ISO/IEC 27701', '2025', null],
                    ],
                    'Controls' => [
                        ['framework_nama', 'framework_versi', 'kode_klausul', 'judul', 'kategori', 'deskripsi', 'domain_peran'],
                        ['ISO/IEC 27701', '2025', '7.2.1', 'Tujuan', 'organisasional', 'd1', 'controller'],
                        ['ISO/IEC 27701', '2025', '8.2.1', 'Pemrosesan', 'teknologi', 'd2', 'processor'],
                    ],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.type', 'success');

        $framework = Framework::query()->where('nama', 'ISO/IEC 27701')->firstOrFail();
        $this->assertSame(2, $framework->controls()->count());
        $this->assertSame('controller', $framework->controls()->where('kode_klausul', '7.2.1')->firstOrFail()->domain_peran);
        $this->assertSame('processor', $framework->controls()->where('kode_klausul', '8.2.1')->firstOrFail()->domain_peran);
    }

    public function test_qa10_read_only_roles_cannot_import_preview_or_export_master_data(): void
    {
        $file = $this->uploadXlsx([
            'Frameworks' => [['nama', 'versi', 'url_file'], ['ISO/IEC 42001', '2023', null]],
        ]);

        foreach ([User::ROLE_KOORDINATOR_SMKI, User::ROLE_AUDITOR] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertFalse($user->hasPermissionTo('control.import'), "{$role} tidak punya control.import");

            $this->actingAs($user)
                ->post('/admin/kepatuhan/master-data/import/preview', ['file' => $file])
                ->assertForbidden();
            $this->actingAs($user)
                ->post('/admin/kepatuhan/master-data/import', ['file' => $file])
                ->assertForbidden();
            $this->actingAs($user)
                ->get('/admin/kepatuhan/master-data/export')
                ->assertForbidden();
        }

        $this->assertDatabaseCount('frameworks', 0);
    }

    // ── Restore paths (§11): the HTTP gate users actually hit ─────────────

    public function test_qa10_restore_endpoints_reject_anonymous_callers(): void
    {
        [$unit, $entry] = $this->entryFor($this->unit('Unit Anon'));
        $evidence = $entry->evidences()->create([
            'uploaded_by' => $entry->pic_id, 'file_url' => 'bukti/anon/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);
        $evidence->delete();

        $finding = Finding::factory()->create(['unit_id' => $unit->id, 'pic_id' => $entry->pic_id]);
        $finding->delete();

        $this->postJson("/api/checklist-entries/{$entry->id}/restore")->assertUnauthorized();
        $this->postJson("/api/evidences/{$evidence->id}/restore")->assertUnauthorized();
        $this->post("/temuan/{$finding->id}/restore")->assertRedirect('/login');

        $this->assertSoftDeleted('checklist_entries', ['id' => $entry->id]);
        $this->assertSoftDeleted('compliance_evidences', ['id' => $evidence->id]);
        $this->assertSoftDeleted('findings', ['id' => $finding->id]);
    }

    public function test_qa10_checklist_entry_restore_denied_for_pic_of_another_unit(): void
    {
        [, $entry] = $this->entryFor($this->unit('Unit Asli'));
        $stranger = User::factory()->create([
            'role' => User::ROLE_PIC,
            'unit_id' => $this->unit('Unit Asing')->id,
        ]);

        $this->actingAs($stranger)
            ->postJson("/api/checklist-entries/{$entry->id}/restore")
            ->assertForbidden();

        $this->assertSoftDeleted('checklist_entries', ['id' => $entry->id]);
    }

    public function test_qa10_checklist_entry_restore_allowed_for_parent_unit_pic(): void
    {
        $parent = $this->unit('Unit Induk');
        $child = $this->unit('Unit Anak', $parent);
        [, $entry] = $this->entryFor($child);

        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);

        $this->actingAs($parentPic)
            ->postJson("/api/checklist-entries/{$entry->id}/restore")
            ->assertOk();

        $this->assertDatabaseHas('checklist_entries', ['id' => $entry->id, 'deleted_at' => null]);
    }

    /**
     * GAP (authorization): config/permissions.php grants `checklist.delete` /
     * `checklist.restore` to superadmin + admin_kepatuhan only (lines 120 / 196).
     * ChecklistEntryPolicy::restore() delegates to isUserAuthorizedForEntry(),
     * which returns `true` for every non-PIC user (ChecklistEntryPolicy.php:13-15)
     * and never consults `checklist.restore`. A read-only auditor or koordinator
     * can therefore un-delete a checklist row over the API.
     *
     * The assertion pins the current behaviour; expected = 403.
     */
    public function test_qa10_gap_auditor_can_restore_checklist_entry_without_restore_permission(): void
    {
        [, $entry] = $this->entryFor($this->unit('Unit Auditor'));
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);

        $this->assertFalse($auditor->hasPermissionTo('checklist.restore'));

        $this->actingAs($auditor)
            ->postJson("/api/checklist-entries/{$entry->id}/restore")
            ->assertOk();

        $this->assertDatabaseHas('checklist_entries', ['id' => $entry->id, 'deleted_at' => null]);
    }

    public function test_qa10_evidence_restore_denied_for_pic_of_another_unit(): void
    {
        [, $entry] = $this->entryFor($this->unit('Unit Pemilik'));
        $evidence = $entry->evidences()->create([
            'uploaded_by' => $entry->pic_id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);
        $evidence->delete();

        $stranger = User::factory()->create([
            'role' => User::ROLE_PIC,
            'unit_id' => $this->unit('Unit Tetangga')->id,
        ]);

        $this->actingAs($stranger)
            ->postJson("/api/evidences/{$evidence->id}/restore")
            ->assertForbidden();

        $this->assertSoftDeleted('compliance_evidences', ['id' => $evidence->id]);
    }

    /**
     * The evidence policy resolves its parent through
     * `ChecklistEntry::withTrashed()` (ComplianceEvidencePolicy.php:39), so a
     * restore must still succeed while the checklist entry is itself trashed.
     */
    public function test_qa10_evidence_restore_succeeds_while_parent_entry_is_soft_deleted(): void
    {
        [$unit, $entry] = $this->entryFor($this->unit('Unit Bertingkat'));
        $evidence = $entry->evidences()->create([
            'uploaded_by' => $entry->pic_id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);
        $evidence->delete();
        $this->assertSoftDeleted('checklist_entries', ['id' => $entry->id]);

        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $this->actingAs($pic)
            ->postJson("/api/evidences/{$evidence->id}/restore")
            ->assertOk();

        $this->assertDatabaseHas('compliance_evidences', ['id' => $evidence->id, 'deleted_at' => null]);
    }

    /**
     * GAP (authorization): `evidence.restore` is granted to PIC, admin and
     * superadmin only; auditor/koordinator hold `evidence.read` and nothing
     * else. ComplianceEvidencePolicy::restore() returns `true` for every
     * non-PIC user, so those read-only roles can un-delete evidence. Expected = 403.
     */
    public function test_qa10_gap_koordinator_can_restore_evidence_without_restore_permission(): void
    {
        [, $entry] = $this->entryFor($this->unit('Unit Koord'));
        $evidence = $entry->evidences()->create([
            'uploaded_by' => $entry->pic_id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);
        $evidence->delete();

        $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);
        $this->assertFalse($koordinator->hasPermissionTo('evidence.restore'));

        $this->actingAs($koordinator)
            ->postJson("/api/evidences/{$evidence->id}/restore")
            ->assertOk();

        $this->assertDatabaseHas('compliance_evidences', ['id' => $evidence->id, 'deleted_at' => null]);
    }

    public function test_qa10_finding_restore_denied_for_pic_even_on_own_unit_finding(): void
    {
        $unit = $this->unit('Unit Temuan');
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $finding = Finding::factory()->create(['unit_id' => $unit->id, 'pic_id' => $pic->id]);
        $finding->delete();

        $this->actingAs($pic)
            ->post("/temuan/{$finding->id}/restore")
            ->assertForbidden();

        $this->assertSoftDeleted('findings', ['id' => $finding->id]);
    }

    public function test_qa10_finding_restore_denied_for_auditor_and_koordinator(): void
    {
        $unit = $this->unit('Unit Temuan 2');
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $finding = Finding::factory()->create(['unit_id' => $unit->id, 'pic_id' => $pic->id]);
        $finding->delete();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_AUDITOR]))
            ->post("/temuan/{$finding->id}/restore")
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]))
            ->post("/temuan/{$finding->id}/restore")
            ->assertForbidden();

        $this->assertSoftDeleted('findings', ['id' => $finding->id]);
    }

    public function test_qa10_finding_restore_404_when_row_is_not_trashed(): void
    {
        $unit = $this->unit('Unit Temuan 3');
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $finding = Finding::factory()->create(['unit_id' => $unit->id, 'pic_id' => $pic->id]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]))
            ->post("/temuan/{$finding->id}/restore")
            ->assertNotFound();

        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'deleted_at' => null]);
    }
}
