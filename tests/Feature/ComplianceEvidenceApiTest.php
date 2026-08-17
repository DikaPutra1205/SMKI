<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplianceEvidenceApiTest extends TestCase
{
    use RefreshDatabase;

    private array $uploaded = [];

    private function isRealBucket(): bool
    {
        // Only perform real-bucket I/O when running in the E2E group against a live endpoint.
        return (bool) env('SUPABASE_ENDPOINT');
    }

    private function seedEntry(): array
    {
        $unit = WorkUnit::create(['nama' => 'Unit QA']);
        $fw = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a',
        ]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        return ['unit' => $unit, 'control' => $control, 'pic' => $pic, 'entry' => $entry];
    }

    protected function tearDown(): void
    {
        // Bucket pollution guard: delete any objects we uploaded to the real bucket.
        // Only hits the network when a real SUPABASE_ENDPOINT is configured.
        if ($this->isRealBucket() && ! empty($this->uploaded)) {
            $disk = Storage::disk('supabase');
            foreach ($this->uploaded as $path) {
                try {
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                    }
                } catch (\Throwable $e) {
                    // best-effort cleanup; never fail the test on cleanup
                }
            }
        }
        parent::tearDown();
    }

    public function test_index_lists_all_versions_including_trashed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['entry' => $entry, 'pic' => $pic] = $this->seedEntry();
        $entry->evidences()->create([
            'uploaded_by' => $pic->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);
        $entry->evidences()->create([
            'uploaded_by' => $pic->id, 'file_url' => 'bukti/1/b.pdf',
            'version_number' => 2, 'is_active' => false, 'uploaded_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson("/api/checklist-entries/{$entry->id}/evidences")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version_number', 2);
    }

    public function test_index_trashed_only_filter(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['entry' => $entry, 'pic' => $pic] = $this->seedEntry();
        $e = $entry->evidences()->create([
            'uploaded_by' => $pic->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);
        $e->delete();

        $this->actingAs($admin)
            ->getJson("/api/checklist-entries/{$entry->id}/evidences?trashed=only")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── Real-bucket E2E ────────────────────────────────────────────────────────
    // Hits the live supabase bucket (bukti-kepatuhan). Skips unless SUPABASE_ENDPOINT
    // is set in the test env. Cleanup in tearDown.
    /**
     * @group supabase-e2e
     */
    public function test_store_uploads_to_supabase_real_bucket(): void
    {
        if (! env('SUPABASE_ENDPOINT')) {
            $this->markTestSkipped('SUPABASE_ENDPOINT not set — skipping real-bucket E2E.');
        }
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();

        $file = UploadedFile::fake()->create('bukti.pdf', 500, 'application/pdf');

        $response = $this->actingAs($pic)->postJson(
            "/api/checklist-entries/{$entry->id}/evidences",
            ['bukti_file' => $file, 'uploaded_by' => $pic->id]
        )->assertCreated();

        $path = $response->json('data.file_url');
        $this->uploaded[] = $path;

        $this->assertTrue(Storage::disk('supabase')->exists($path));
        $this->assertDatabaseHas('compliance_evidences', ['checklist_entry_id' => $entry->id, 'file_url' => $path]);
    }

    /**
     * @group supabase-e2e
     */
    public function test_store_exact_10mb_boundary_accepted_on_real_bucket(): void
    {
        if (! env('SUPABASE_ENDPOINT')) {
            $this->markTestSkipped('SUPABASE_ENDPOINT not set — skipping real-bucket E2E.');
        }
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();

        $file = UploadedFile::fake()->create('bukti.pdf', 10240, 'application/pdf');

        $response = $this->actingAs($pic)->postJson(
            "/api/checklist-entries/{$entry->id}/evidences",
            ['bukti_file' => $file, 'uploaded_by' => $pic->id]
        )->assertCreated();

        $this->uploaded[] = $response->json('data.file_url');
    }

    // ── Disk-faked (no network) contract tests ─────────────────────────────────
    public function test_store_rejects_missing_file(): void
    {
        Storage::fake('supabase');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();

        $this->actingAs($pic)
            ->postJson("/api/checklist-entries/{$entry->id}/evidences", ['uploaded_by' => $pic->id])
            ->assertStatus(422);
    }

    public function test_store_rejects_oversized_file(): void
    {
        Storage::fake('supabase');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();

        $file = UploadedFile::fake()->create('bukti.pdf', 10241, 'application/pdf');

        $this->actingAs($pic)
            ->postJson("/api/checklist-entries/{$entry->id}/evidences",
                ['bukti_file' => $file, 'uploaded_by' => $pic->id])
            ->assertStatus(422);
    }

    // D2 — mime restriction: non-document rejected.
    public function test_store_rejects_non_document_mime(): void
    {
        Storage::fake('supabase');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();

        $file = UploadedFile::fake()->create('bukti.exe', 100, 'application/x-msdownload');

        $this->actingAs($pic)
            ->postJson("/api/checklist-entries/{$entry->id}/evidences",
                ['bukti_file' => $file, 'uploaded_by' => $pic->id])
            ->assertStatus(422);
    }

    public function test_version_increment_and_prior_deactivated(): void
    {
        Storage::fake('supabase');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();

        $this->actingAs($pic)->postJson("/api/checklist-entries/{$entry->id}/evidences",
            ['bukti_file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'), 'uploaded_by' => $pic->id])
            ->assertCreated();

        $this->actingAs($pic)->postJson("/api/checklist-entries/{$entry->id}/evidences",
            ['bukti_file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'), 'uploaded_by' => $pic->id])
            ->assertCreated();

        $this->assertDatabaseHas('compliance_evidences', ['checklist_entry_id' => $entry->id, 'version_number' => 1, 'is_active' => false]);
        $this->assertDatabaseHas('compliance_evidences', ['checklist_entry_id' => $entry->id, 'version_number' => 2, 'is_active' => true]);
    }

    // store returns 500 when the S3 put yields no path.
    public function test_store_returns_500_on_upload_failure(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('supabase')->andReturn($disk);

        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();

        $this->actingAs($pic)->postJson("/api/checklist-entries/{$entry->id}/evidences",
            ['bukti_file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'), 'uploaded_by' => $pic->id])
            ->assertStatus(500);
    }

    // D3 — update() silently skips evidence creation on S3 failure (200, no row).
    public function test_update_silent_skip_on_upload_failure(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('supabase')->andReturn($disk);

        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();
        $before = $entry->evidences()->count();

        $this->actingAs($pic)->patchJson("/api/checklist-entries/{$entry->id}",
            ['status' => ChecklistEntry::STATUS_COMPLIANT, 'bukti_file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'), 'uploaded_by' => $pic->id])
            ->assertOk();

        $this->assertSame($before, $entry->fresh()->evidences()->count());
        $this->assertDatabaseHas('checklist_entries', ['id' => $entry->id, 'status' => 'compliant', 'tanggal_verifikasi' => null]);
    }

    public function test_destroy_soft_deletes_evidence(): void
    {
        Storage::fake('supabase');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();
        $evidence = $entry->evidences()->create([
            'uploaded_by' => $pic->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->actingAs($pic)->deleteJson("/api/evidences/{$evidence->id}")->assertOk();
        $this->assertSoftDeleted('compliance_evidences', ['id' => $evidence->id]);
    }

    public function test_restore_by_raw_id(): void
    {
        Storage::fake('supabase');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();
        $evidence = $entry->evidences()->create([
            'uploaded_by' => $pic->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);
        $evidence->delete();

        $this->actingAs($pic)->postJson("/api/evidences/{$evidence->id}/restore")->assertOk();
        $this->assertDatabaseHas('compliance_evidences', ['id' => $evidence->id]);
    }

    // D4 — no download/read endpoint exists; backend only writes.

    // Coverage gap: evidence index is nested under a checklist entry and never
    // scopes by the authenticated user, so any logged-in caller can list any
    // entry's evidence history. PASS here proves the absence of tenant scoping.
    public function test_index_cross_entry_read_is_not_scoped(): void
    {
        Storage::fake('supabase');
        $viewer = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry, 'pic' => $pic] = $this->seedEntry();
        $entry->evidences()->create([
            'uploaded_by' => $pic->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/checklist-entries/{$entry->id}/evidences")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // Coverage gap: uploaded_by is client-supplied with no check that it equals
    // the authenticated user, so any caller can attribute evidence to any user
    // (e.g. an admin). Documents current behavior.
    public function test_store_accepts_arbitrary_uploaded_by(): void
    {
        Storage::fake('supabase');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $other = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['entry' => $entry] = $this->seedEntry();

        $this->actingAs($pic)->postJson("/api/checklist-entries/{$entry->id}/evidences",
            ['bukti_file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'), 'uploaded_by' => $other->id])
            ->assertCreated();

        $this->assertDatabaseHas('compliance_evidences', ['checklist_entry_id' => $entry->id, 'uploaded_by' => $other->id]);
    }

    public function test_no_download_endpoint_exists(): void
    {
        Storage::fake('supabase');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        ['entry' => $entry] = $this->seedEntry();
        $evidence = $entry->evidences()->create([
            'uploaded_by' => $pic->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->actingAs($pic)->getJson("/api/checklist-entries/{$entry->id}/evidences/{$evidence->id}/download")
            ->assertStatus(404);
    }
}
