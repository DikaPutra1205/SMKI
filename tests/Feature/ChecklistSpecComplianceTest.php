<?php

namespace Tests\Feature;

use App\Actions\CarryForwardVerifiedMap;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use App\Notifications\ChecklistEntryRejectedNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FUNCTIONAL_SPEC.md §3 QA coverage — US-C1..US-C5.
 *
 * Each test maps to one spec sentence. Tests that pin behaviour which
 * DEVIATES from the spec are marked `SPEC-GAP` and assert the current
 * behaviour so the deviation stays visible without reddening the suite.
 */
class ChecklistSpecComplianceTest extends TestCase
{
    private WorkUnit $unit;

    private WorkUnit $otherUnit;

    private Framework $fw;

    private Control $control;

    private User $pic;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = WorkUnit::create(['nama' => 'Unit Spec']);
        $this->otherUnit = WorkUnit::create(['nama' => 'Unit Spec Lain']);
        $this->fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $this->control = $this->fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
    }

    private function entry(array $overrides = []): ChecklistEntry
    {
        return ChecklistEntry::create(array_merge([
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
            'catatan' => '',
        ], $overrides));
    }

    private function makeSession(string $periode, ?int $unitId = null, ?int $frameworkId = null): ChecklistSession
    {
        return ChecklistSession::create([
            'konteks_penilaian' => "Sesi {$periode}",
            'unit_id' => $unitId ?? $this->unit->id,
            'framework_id' => $frameworkId ?? $this->fw->id,
            'periode' => $periode,
        ]);
    }

    // ── US-C1: 5 statuses + applyPicTouch 4-way auto-status matrix ────────────

    public function test_us_c1_status_domain_is_exactly_five_values(): void
    {
        $this->assertSame([
            'belum_dimulai',
            'dalam_proses',
            'dalam_tinjauan',
            'selesai_diterapkan',
            'tidak_berlaku',
        ], ChecklistEntry::workflowValues());
    }

    public function test_us_c1_apply_pic_touch_matrix_notes_evidence_na(): void
    {
        $entry = $this->entry();

        // notes + evidence → dalam_tinjauan
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_TINJAUAN, $entry->applyPicTouch('SOP ada', true, false));
        // notes only → dalam_proses
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $entry->applyPicTouch('SOP ada', false, false));
        // evidence only → dalam_proses
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $entry->applyPicTouch('', true, false));
        // neither → belum_dimulai
        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $entry->applyPicTouch('', false, false));
        // NA wins over notes + evidence
        $this->assertSame(ChecklistEntry::WORKFLOW_TIDAK_BERLAKU, $entry->applyPicTouch('SOP ada', true, true));
        // whitespace-only catatan counts as "no notes"
        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $entry->applyPicTouch("   \n ", false, false));
    }

    public function test_us_c1_web_upload_evidence_bumps_version_and_keeps_single_active(): void
    {
        Storage::fake('supabase');
        $entry = $this->entry(['catatan' => 'SOP tersedia']);

        foreach (range(1, 3) as $n) {
            $this->actingAs($this->pic)
                ->from('/admin/pic/checklist')
                ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                    'bukti_file' => UploadedFile::fake()->create("v{$n}.pdf", 100, 'application/pdf'),
                ])
                ->assertRedirect('/admin/pic/checklist');
        }

        $rows = ComplianceEvidence::where('checklist_entry_id', $entry->id)->orderBy('version_number')->get();
        $this->assertSame([1, 2, 3], $rows->pluck('version_number')->all(), 'version_number must ++ per upload');
        $this->assertSame([3], $rows->where('is_active', true)->pluck('version_number')->all(), 'only the newest revision stays active');
        $this->assertSame(2, $rows->where('is_active', false)->count());

        // notes + evidence → dalam_tinjauan, and it stays there across versions
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_TINJAUAN, $entry->fresh()->status);
    }

    public function test_us_c1_web_upload_evidence_resets_tanggal_verifikasi(): void
    {
        Storage::fake('supabase');
        $entry = $this->entry([
            'catatan' => 'SOP tersedia',
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'tanggal_verifikasi' => now()->subDay(),
            'admin_id' => $this->admin->id,
        ]);

        $this->actingAs($this->pic)
            ->from('/admin/pic/checklist')
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                'bukti_file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect('/admin/pic/checklist');

        $fresh = $entry->fresh();
        $this->assertNull($fresh->tanggal_verifikasi);
        $this->assertNotNull($fresh->tanggal_input);
    }

    public function test_us_c1_api_upload_evidence_clears_catatan_admin_and_admin_id(): void
    {
        Storage::fake('supabase');
        $entry = $this->entry([
            'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES,
            'catatan_admin' => 'Bukti buram, unggah ulang',
            'admin_id' => $this->admin->id,
            'tanggal_verifikasi' => now()->subDay(),
        ]);

        $this->actingAs($this->pic)
            ->postJson("/api/checklist-entries/{$entry->id}/evidences", [
                'bukti_file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
                'uploaded_by' => $this->pic->id,
            ])
            ->assertCreated();

        $fresh = $entry->fresh();
        $this->assertNull($fresh->catatan_admin);
        $this->assertNull($fresh->admin_id);
        $this->assertNull($fresh->tanggal_verifikasi);
    }

    /**
     * SPEC-GAP — FUNCTIONAL_SPEC.md:40 requires every evidence upload to reset
     * `catatan_admin` to NULL. `Web\ChecklistEntryController::uploadEvidence()`
     * only nulls `tanggal_verifikasi`, so a stale admin verdict survives a
     * brand-new evidence revision. The API path does clear it.
     */
    public function test_us_c1_gap_web_upload_evidence_keeps_stale_catatan_admin(): void
    {
        Storage::fake('supabase');
        $entry = $this->entry([
            'catatan' => 'SOP tersedia',
            'catatan_admin' => 'Bukti buram, unggah ulang',
            'admin_id' => $this->admin->id,
            'tanggal_verifikasi' => now()->subDay(),
        ]);

        $this->actingAs($this->pic)
            ->from('/admin/pic/checklist')
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                'bukti_file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect('/admin/pic/checklist');

        $fresh = $entry->fresh();
        $this->assertNull($fresh->tanggal_verifikasi, 'tanggal_verifikasi must reset');
        $this->assertSame('Bukti buram, unggah ulang', $fresh->catatan_admin, 'SPEC-GAP: should be NULL');
    }

    /**
     * SPEC-GAP — US-G2 promises a parent-unit PIC can supervise child-unit
     * records. `ChecklistEntryPolicy::update()` grants it, but
     * `Web\ChecklistEntryController::update()` / `batchUpdate()` /
     * `uploadEvidence()` / `deleteEvidence()` all resolve the row with
     * `ChecklistEntry::where('pic_id', $user->id)` (e.g. line 17) and never call
     * `Gate::authorize()`. So the parent PIC's checklist page renders (session
     * policy allows it) but every auto-save 404s — a read/write split that
     * strands the supervisory journey.
     */
    public function test_us_c1_gap_parent_pic_can_read_but_not_write_child_unit_entry(): void
    {
        $child = WorkUnit::create(['nama' => 'Unit Anak', 'parent_id' => $this->unit->id]);
        $childPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $child->id]);
        $session = $this->makeSession('2026-09', $child->id);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $child->id,
            'session_id' => $session->id, 'pic_id' => $childPic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI, 'catatan' => '',
        ]);

        // policy says yes …
        $this->assertTrue(Gate::forUser($this->pic)->allows('update', $entry));

        // … the page renders …
        $this->actingAs($this->pic)
            ->get("/admin/pic/checklist/{$session->id}")
            ->assertOk();

        // … but the write path 404s
        $this->actingAs($this->pic)
            ->patchJson("/admin/pic/checklist-entries/{$entry->id}", ['catatan' => 'Supervisi parent'])
            ->assertNotFound();

        $this->assertSame('', $entry->fresh()->catatan);
    }

    // ── US-C2: catatan vs catatan_admin, level_maturity 0-5 nullable ─────────

    public function test_us_c2_pic_accepts_level_maturity_zero_and_null_but_rejects_out_of_range(): void
    {
        $entry = $this->entry();

        foreach ([0, 5, null] as $level) {
            $this->actingAs($this->pic)
                ->patchJson("/admin/pic/checklist-entries/{$entry->id}", [
                    'catatan' => 'Dinilai', 'level_maturity' => $level,
                ])
                ->assertOk();
            $this->assertSame($level, $entry->fresh()->level_maturity);
        }

        foreach ([-1, 6] as $level) {
            $this->actingAs($this->pic)
                ->patchJson("/admin/pic/checklist-entries/{$entry->id}", ['level_maturity' => $level])
                ->assertStatus(422);
        }
    }

    public function test_us_c2_verify_single_approve_preserves_level_maturity_when_omitted(): void
    {
        $entry = $this->entry([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'level_maturity' => 3,
            'catatan' => 'SOP tersedia',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", ['decision' => 'approve'])
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertSame(3, $fresh->level_maturity);
        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $fresh->status);
    }

    public function test_us_c2_bulk_verify_never_touches_level_maturity(): void
    {
        $entry = $this->entry([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'level_maturity' => 2,
            'catatan' => 'SOP tersedia',
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id], 'decision' => 'approve',
            ])
            ->assertRedirect();

        $this->assertSame(2, $entry->fresh()->level_maturity);

        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id], 'decision' => 'reject', 'admin_notes' => 'Ulangi',
            ])
            ->assertRedirect();

        $this->assertSame(2, $entry->fresh()->level_maturity);
    }

    public function test_us_c2_bulk_verify_payload_cannot_smuggle_level_maturity(): void
    {
        $entry = $this->entry([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'level_maturity' => 2,
            'catatan' => 'SOP tersedia',
        ]);

        // no `prohibited` rule, but the service payload has no level_maturity key
        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'decision' => 'approve',
                'level_maturity' => 5,
            ])
            ->assertRedirect();

        $this->assertSame(2, $entry->fresh()->level_maturity);
    }

    public function test_us_c2_single_verify_rejects_out_of_range_level_maturity(): void
    {
        $entry = $this->entry([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'level_maturity' => 3,
        ]);

        foreach ([-1, 6, 'abc'] as $bad) {
            $this->actingAs($this->admin)
                ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                    'decision' => 'approve', 'level_maturity' => $bad,
                ])
                ->assertStatus(422);
        }

        $this->assertSame(3, $entry->fresh()->level_maturity);
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_TINJAUAN, $entry->fresh()->status);
    }

    public function test_us_c2_catatan_and_catatan_admin_stay_in_separate_columns(): void
    {
        $entry = $this->entry([
            'catatan' => 'Self assessment PIC',
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve',
            ])
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertSame('Self assessment PIC', $fresh->catatan);
        $this->assertNull($fresh->catatan_admin);

        // reject writes only catatan_admin, catatan untouched
        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'reject', 'admin_notes' => 'Verdict admin',
            ])
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertSame('Self assessment PIC', $fresh->catatan);
        $this->assertSame('Verdict admin', $fresh->catatan_admin);
    }

    // ── US-C3: single verify contract + notifications + authz ────────────────

    public function test_us_c3_single_verify_approve_stamps_verification_and_admin(): void
    {
        $entry = $this->entry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", ['decision' => 'approve'])
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $fresh->status);
        $this->assertNotNull($fresh->tanggal_verifikasi);
        $this->assertSame($this->admin->id, $fresh->admin_id);
    }

    public function test_us_c3_single_verify_reject_requires_catatan_admin(): void
    {
        $entry = $this->entry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", ['decision' => 'reject'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_notes']);

        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_TINJAUAN, $entry->fresh()->status);
    }

    public function test_us_c3_single_verify_reject_notifies_pic_with_admin_note(): void
    {
        Notification::fake();
        $entry = $this->entry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'reject', 'admin_notes' => 'Bukti tidak terbaca',
            ])
            ->assertRedirect();

        Notification::assertSentTo($this->pic, ChecklistEntryRejectedNotification::class);
    }

    public function test_us_c3_single_verify_approve_sends_no_notification(): void
    {
        Notification::fake();
        $entry = $this->entry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", ['decision' => 'approve'])
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_us_c3_bulk_verify_reject_notifies_target_pic(): void
    {
        Notification::fake();
        $entry = $this->entry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'decision' => 'reject',
                'admin_notes' => 'Perbaiki bukti',
            ])
            ->assertRedirect();

        Notification::assertSentToTimes($this->pic, ChecklistEntryRejectedNotification::class, 1);
    }

    public function test_us_c3_bulk_verify_approve_sends_no_notification(): void
    {
        Notification::fake();
        $entry = $this->entry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id], 'decision' => 'approve',
            ])
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_us_c3_verify_payload_cannot_smuggle_status(): void
    {
        $entry = $this->entry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve', 'status' => 'selesai_diterapkan',
            ])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id], 'decision' => 'approve', 'status' => 'selesai_diterapkan',
            ])
            ->assertStatus(422);
    }

    public function test_us_c3_verify_endpoints_reject_anonymous_and_unprivileged_callers(): void
    {
        $entry = $this->entry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        // anonymous → redirected to login, never reaches the handler
        $this->post("/admin/kepatuhan/checklist/verify/{$entry->id}", ['decision' => 'approve'])
            ->assertRedirect(route('login'));
        $this->post('/admin/kepatuhan/bulk-verify', [
            'entry_ids' => [$entry->id], 'decision' => 'approve',
        ])->assertRedirect(route('login'));
        $this->patchJson("/api/checklist-entries/{$entry->id}/verify", [
            'admin_id' => $this->admin->id, 'decision' => 'approve',
        ])->assertStatus(401);

        // authenticated but without checklist.bulk-verify
        foreach ([User::ROLE_PIC, User::ROLE_AUDITOR, User::ROLE_KOORDINATOR_SMKI] as $role) {
            $user = User::factory()->create(['role' => $role, 'unit_id' => $this->unit->id]);

            $this->actingAs($user)
                ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", ['decision' => 'approve'])
                ->assertForbidden();

            $this->actingAs($user)
                ->postJson('/admin/kepatuhan/bulk-verify', [
                    'entry_ids' => [$entry->id], 'decision' => 'approve',
                ])
                ->assertForbidden();
        }
    }

    /**
     * SPEC-GAP — `Api\ChecklistEntryController::verify()` returns 422 unless the
     * entry is `dalam_tinjauan` or `tidak_berlaku` (Api/ChecklistEntryController.php:319),
     * but the web `verifySingle()` has no equivalent precondition
     * (Web/ComplianceOfficerController.php:290). Same admin, same entry, two
     * different answers: the web route lets a never-started row jump straight
     * to `selesai_diterapkan` and stamp `tanggal_verifikasi`.
     */
    public function test_us_c3_gap_web_single_verify_has_no_status_precondition(): void
    {
        $entry = $this->entry([
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
            'catatan' => '',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", ['decision' => 'approve'])
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $fresh->status, 'SPEC-GAP: should be rejected 422');
        $this->assertNotNull($fresh->tanggal_verifikasi, 'SPEC-GAP: never-started entry got a verdict');

        // same entry, API route: correctly refused
        $api = $this->entry(['status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI, 'catatan' => '']);
        $this->actingAs($this->admin)
            ->patchJson("/api/checklist-entries/{$api->id}/verify", [
                'admin_id' => $this->admin->id, 'decision' => 'approve',
            ])
            ->assertStatus(422);

        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $api->fresh()->status);
    }

    public function test_us_c3_reject_notification_falls_back_to_unit_pic_when_entry_unassigned(): void
    {
        Notification::fake();
        // pic_id NULL (unit has no assigned PIC on the row) — observer backfill gap
        $entry = $this->entry([
            'pic_id' => null,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'reject', 'admin_notes' => 'Bukti tidak terbaca',
            ])
            ->assertRedirect();

        Notification::assertSentTo($this->pic, ChecklistEntryRejectedNotification::class);
    }

    public function test_us_c3_reject_does_not_self_notify_when_admin_owns_the_entry(): void
    {
        Notification::fake();
        $entry = $this->entry([
            'pic_id' => $this->admin->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'reject', 'admin_notes' => 'Perbaiki',
            ])
            ->assertRedirect();

        Notification::assertNothingSent();
        $this->assertSame('Perbaiki', $entry->fresh()->catatan_admin);
    }

    public function test_us_c3_reject_carries_admin_note_in_notification_payload(): void
    {
        Notification::fake();
        $entry = $this->entry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'reject', 'admin_notes' => 'Dokumen kedaluwarsa',
            ])
            ->assertRedirect();

        Notification::assertSentTo(
            $this->pic,
            ChecklistEntryRejectedNotification::class,
            function (ChecklistEntryRejectedNotification $notification) {
                return $notification->catatanAdmin === 'Dokumen kedaluwarsa'
                    && $notification->toDatabase($this->pic)['type'] === 'checklist_rejected'
                    && $notification->toDatabase($this->pic)['catatan_admin'] === 'Dokumen kedaluwarsa'
                    && $notification->toDatabase($this->pic)['entry_id'] === $notification->entry->id
                    && $notification->toDatabase($this->pic)['actor_id'] === $this->admin->id;
            }
        );
    }

    public function test_us_c3_verify_page_inertia_props_expose_entries_and_session(): void
    {
        $session = $this->makeSession('2026-09');
        $entry = $this->entry([
            'session_id' => $session->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/kepatuhan/checklist/verify?session_id={$session->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin-kepatuhan/checklist/verify')
                ->has('entries.data')
                ->has('session')
                ->has('workUnits')
                ->where('entries.data.0.id', $entry->id)
            );
    }

    // ── US-C4: monthly generator, one session per unit x framework ───────────

    public function test_us_c4_command_creates_one_session_and_one_entry_per_control(): void
    {
        $fw2 = Framework::create(['nama' => 'ISO 27701', 'versi' => '2025']);
        $fw2->controls()->create(['kode_klausul' => 'B.1.1', 'judul' => 'PIM', 'kategori' => 'people']);
        $this->fw->controls()->create(['kode_klausul' => 'A.5.2', 'judul' => 'Roles', 'kategori' => 'people']);

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $periode = now()->format('Y-m');
        $this->assertSame(2, ChecklistSession::where('unit_id', $this->unit->id)->where('periode', $periode)->count());
        $this->assertSame(3, ChecklistEntry::where('unit_id', $this->unit->id)->count());
        $this->assertSame(0, ChecklistEntry::where('unit_id', $this->unit->id)
            ->where('status', '!=', ChecklistEntry::WORKFLOW_BELUM_DIMULAI)->count());
    }

    public function test_us_c4_generator_is_scheduled_for_the_first_of_each_month(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command ?? '', 'smki:generate-monthly-checklist'));

        $this->assertCount(1, $events, 'routes/console.php:14 must register exactly one schedule entry');
        $this->assertSame('0 0 1 * *', $events->first()->expression);
    }

    public function test_us_c4_rerun_mid_month_inserts_nothing(): void
    {
        $this->fw->controls()->create(['kode_klausul' => 'A.5.2', 'judul' => 'Roles', 'kategori' => 'people']);

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();
        $entries = ChecklistEntry::count();
        $sessions = ChecklistSession::count();

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();
        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $this->assertSame($entries, ChecklistEntry::count());
        $this->assertSame($sessions, ChecklistSession::count());
    }

    public function test_us_c4_rerun_backfills_only_the_missing_control(): void
    {
        $second = $this->fw->controls()->create([
            'kode_klausul' => 'A.5.2', 'judul' => 'Roles', 'kategori' => 'people',
        ]);

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        // admin purged one clause mid-month; the next run must re-add only that one
        ChecklistEntry::where('unit_id', $this->unit->id)
            ->where('control_id', $second->id)->forceDelete();

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $this->assertSame(1, ChecklistEntry::where('unit_id', $this->unit->id)
            ->where('control_id', $second->id)->count());
        $this->assertSame(1, ChecklistSession::where('unit_id', $this->unit->id)
            ->where('periode', now()->format('Y-m'))->count());
    }

    public function test_us_c4_web_generate_monthly_requires_permission_and_is_idempotent(): void
    {
        $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);

        $this->actingAs($koordinator)
            ->post('/admin/kepatuhan/generate-monthly')
            ->assertForbidden();

        $this->actingAs($this->admin)->post('/admin/kepatuhan/generate-monthly')->assertRedirect();
        $periode = now()->format('Y-m');
        $this->actingAs($this->admin)->post('/admin/kepatuhan/generate-monthly')->assertRedirect();

        $this->assertSame(1, ChecklistSession::where('unit_id', $this->unit->id)
            ->where('periode', $periode)->count());
    }

    public function test_us_c1_web_upload_evidence_keeps_stale_admin_id(): void
    {
        Storage::fake('supabase');
        $entry = $this->entry([
            'catatan' => 'SOP tersedia',
            'catatan_admin' => 'Bukti buram, unggah ulang',
            'admin_id' => $this->admin->id,
            'tanggal_verifikasi' => now()->subDay(),
        ]);

        $this->actingAs($this->pic)
            ->from('/admin/pic/checklist')
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                'bukti_file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect('/admin/pic/checklist');

        $this->assertSame($this->admin->id, $entry->fresh()->admin_id, 'SPEC-GAP: API nulls admin_id, web does not');
    }

    public function test_us_c1_active_evidence_relation_exposes_only_newest_version(): void
    {
        Storage::fake('supabase');
        $entry = $this->entry(['catatan' => 'SOP tersedia']);

        foreach (range(1, 2) as $n) {
            $this->actingAs($this->pic)
                ->from('/admin/pic/checklist')
                ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                    'bukti_file' => UploadedFile::fake()->create("rev{$n}.pdf", 100, 'application/pdf'),
                ])
                ->assertRedirect('/admin/pic/checklist');
        }

        $this->assertSame(2, $entry->fresh()->activeEvidence->version_number);
    }

    public function test_us_c1_version_increment_continues_past_soft_deleted_revisions(): void
    {
        Storage::fake('supabase');
        $entry = $this->entry(['catatan' => 'SOP tersedia']);

        foreach (range(1, 2) as $n) {
            $this->actingAs($this->pic)
                ->from('/admin/pic/checklist')
                ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                    'bukti_file' => UploadedFile::fake()->create("rev{$n}.pdf", 100, 'application/pdf'),
                ])
                ->assertRedirect('/admin/pic/checklist');
        }

        $second = ComplianceEvidence::where('checklist_entry_id', $entry->id)
            ->where('version_number', 2)->firstOrFail();

        $this->actingAs($this->pic)
            ->deleteJson("/admin/pic/checklist-entries/{$entry->id}/evidence/{$second->id}")
            ->assertOk();

        $this->actingAs($this->pic)
            ->from('/admin/pic/checklist')
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                'bukti_file' => UploadedFile::fake()->create('rev3.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect('/admin/pic/checklist');

        $this->assertSame(
            [1, 2, 3],
            ComplianceEvidence::withTrashed()->where('checklist_entry_id', $entry->id)
                ->orderBy('version_number')->pluck('version_number')->all()
        );
    }

    public function test_us_c1_pic_cannot_write_admin_verdict_columns_directly(): void
    {
        $entry = $this->entry();

        // web PATCH: catatan_admin / tanggal_verifikasi are not writable inputs
        $this->actingAs($this->pic)
            ->patchJson("/admin/pic/checklist-entries/{$entry->id}", [
                'catatan' => 'Self assessment',
                'catatan_admin' => 'Menyerah: saya admin myself',
                'tanggal_verifikasi' => now()->toDateTimeString(),
            ])
            ->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame('Self assessment', $fresh->catatan);
        $this->assertNull($fresh->catatan_admin);
        $this->assertNull($fresh->tanggal_verifikasi);

        // API PATCH: same guarantee
        $this->actingAs($this->pic)
            ->patchJson("/api/checklist-entries/{$entry->id}", [
                'catatan' => 'Self assessment',
                'catatan_admin' => 'Menyerah: saya admin myself',
            ])
            ->assertOk();

        $this->assertNull($entry->fresh()->catatan_admin);
    }

    /**
     * US-G1 requires every route to check `hasPermissionTo()`.
     * `Api\ChecklistEntryController::generateMonthly()` gates on
     * `checklist.generate-monthly` + scopes to accessibleUnitIds.
     * pic (grant) → 200 scoped to own subtree; auditor (no grant) → 403.
     */
    public function test_us_c4_gap_api_generate_monthly_has_no_permission_gate(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);

        $this->actingAs($pic)
            ->postJson('/api/checklist-entries/generate-monthly')
            ->assertOk();

        $this->actingAs($auditor)
            ->postJson('/api/checklist-entries/generate-monthly')
            ->assertForbidden();

        $this->assertSame(1, ChecklistSession::where('unit_id', $this->unit->id)
            ->where('periode', now()->format('Y-m'))->count());
        $this->assertSame(0, ChecklistSession::where('unit_id', $this->otherUnit->id)
            ->where('periode', now()->format('Y-m'))->count());
    }

    public function test_us_c4_command_unit_option_scopes_generation(): void
    {
        $this->artisan('smki:generate-monthly-checklist', ['--unit' => $this->unit->id])
            ->assertSuccessful();

        $this->assertSame(1, ChecklistSession::where('unit_id', $this->unit->id)->count());
        $this->assertSame(0, ChecklistSession::where('unit_id', $this->otherUnit->id)->count());
    }

    public function test_us_c4_command_exits_nonzero_when_no_control_master_data(): void
    {
        Control::query()->delete();

        $this->artisan('smki:generate-monthly-checklist')->assertExitCode(1);
        $this->assertSame(0, ChecklistSession::count());
    }

    public function test_us_c4_command_exits_zero_when_no_work_units_match(): void
    {
        $this->artisan('smki:generate-monthly-checklist', ['--unit' => 999999])->assertExitCode(0);
    }

    public function test_us_c4_command_periode_option_backfills_an_arbitrary_period(): void
    {
        $this->artisan('smki:generate-monthly-checklist', ['--periode' => '2026-01'])
            ->assertSuccessful();

        // both seeded units × one framework
        $this->assertSame(2, ChecklistSession::where('periode', '2026-01')->count());
        $this->assertSame(0, ChecklistSession::where('periode', now()->format('Y-m'))->count());
    }

    public function test_us_c4_session_uniqueness_is_not_enforced_by_the_database(): void
    {
        // `firstOrCreate` is not atomic without a unique index, so two concurrent
        // runs (scheduler vs. manual POST /generate-monthly) can both insert.
        $hasUnique = collect(Schema::getIndexes('checklist_sessions'))->contains(
            fn ($i) => ($i['unique'] ?? false)
                && ($i['columns'] ?? []) === ['unit_id', 'framework_id', 'periode']
        );

        $this->assertFalse($hasUnique, 'SPEC-GAP: no unique index on (unit_id, framework_id, periode)');
    }

    public function test_us_c4_anonymous_cannot_trigger_monthly_generation(): void
    {
        $this->postJson('/api/checklist-entries/generate-monthly')->assertStatus(401);
        $this->postJson('/admin/kepatuhan/generate-monthly')->assertStatus(401);
    }

    // ── US-C5: rollover of verified rows into the new period ────────────────

    private function seedPreviousVerified(): ChecklistEntry
    {
        $prev = $this->makeSession(Carbon::parse(now()->format('Y-m'))->subMonth()->format('Y-m'));

        return $this->entry([
            'session_id' => $prev->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 4,
            'catatan' => 'SOP terpenuhi bulan lalu',
            'catatan_admin' => 'Sudah diverifikasi',
            'tanggal_verifikasi' => now()->subMonth(),
        ]);
    }

    public function test_us_c5_carry_forward_map_only_returns_verified_rows(): void
    {
        $this->seedPreviousVerified();
        $prevSession = ChecklistSession::where('unit_id', $this->unit->id)->firstOrFail();
        $this->entry([
            'session_id' => $prevSession->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);
        $this->entry([
            'session_id' => $prevSession->id, 'status' => ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
        ]);

        $map = CarryForwardVerifiedMap::fromPreviousPeriod(
            $this->unit->id, $this->fw->id, now()->format('Y-m')
        );

        $this->assertCount(1, $map);
        $this->assertSame($this->control->id, $map->first()->control_id);
    }

    public function test_us_c5_carry_forward_map_empty_when_no_previous_session(): void
    {
        $map = CarryForwardVerifiedMap::fromPreviousPeriod(
            $this->unit->id, $this->fw->id, now()->format('Y-m')
        );

        $this->assertTrue($map->isEmpty());
    }

    public function test_us_c5_rollover_clears_catatan_and_stamps_fresh_tanggal_input(): void
    {
        $this->seedPreviousVerified();
        Carbon::setTestNow(now()->addMinutes(5));

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $new = ChecklistEntry::where('unit_id', $this->unit->id)
            ->whereHas('session', fn ($q) => $q->where('periode', now()->format('Y-m')))
            ->firstOrFail();

        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $new->status);
        $this->assertSame(4, $new->level_maturity);
        $this->assertSame('', $new->catatan);
        $this->assertNull($new->catatan_admin);
        $this->assertNull($new->tanggal_verifikasi);
        $this->assertTrue($new->tanggal_input->greaterThan(now()->subMinute()));

        Carbon::setTestNow();
    }

    public function test_us_c5_rollover_resets_non_verified_and_new_controls(): void
    {
        $prev = $this->makeSession(Carbon::parse(now()->format('Y-m'))->subMonth()->format('Y-m'));
        $this->entry([
            'session_id' => $prev->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'level_maturity' => 5, 'catatan' => 'Menunggu verifikasi',
        ]);
        $newControl = $this->fw->controls()->create([
            'kode_klausul' => 'A.9.9', 'judul' => 'Klausul baru', 'kategori' => 'people',
        ]);

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $entries = ChecklistEntry::whereHas('session', fn ($q) => $q->where('periode', now()->format('Y-m')))
            ->where('unit_id', $this->unit->id)->get()->keyBy('control_id');

        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $entries[$this->control->id]->status);
        $this->assertNull($entries[$this->control->id]->level_maturity, 'unverified level must not carry');
        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $entries[$newControl->id]->status);
    }

    public function test_us_c5_rollover_ignores_periods_other_than_immediately_previous(): void
    {
        $old = $this->makeSession(Carbon::parse(now()->format('Y-m'))->subMonths(2)->format('Y-m'));
        $this->entry([
            'session_id' => $old->id, 'status' => ChecklistEntry::WORKFLOW_SELESAI, 'level_maturity' => 5,
        ]);

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $new = ChecklistEntry::whereHas('session', fn ($q) => $q->where('periode', now()->format('Y-m')))
            ->where('unit_id', $this->unit->id)->firstOrFail();

        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $new->status);
        $this->assertNull($new->level_maturity);
    }

    public function test_us_c5_rollover_never_crosses_unit_or_framework(): void
    {
        $otherPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->otherUnit->id]);
        $prevPeriod = Carbon::parse(now()->format('Y-m'))->subMonth()->format('Y-m');
        $this->makeSession($prevPeriod, $this->otherUnit->id);
        $this->entry([
            'session_id' => ChecklistSession::where('unit_id', $this->otherUnit->id)->firstOrFail()->id,
            'unit_id' => $this->otherUnit->id, 'pic_id' => $otherPic->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI, 'level_maturity' => 5,
        ]);

        $fw2 = Framework::create(['nama' => 'ISO 27701', 'versi' => '2025']);
        $ctrl2 = $fw2->controls()->create(['kode_klausul' => 'B.1.1', 'judul' => 'PIM', 'kategori' => 'people']);
        $this->makeSession($prevPeriod, null, $fw2->id);
        $this->entry([
            'session_id' => ChecklistSession::where('framework_id', $fw2->id)->firstOrFail()->id,
            'control_id' => $ctrl2->id, 'status' => ChecklistEntry::WORKFLOW_SELESAI, 'level_maturity' => 5,
        ]);

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, ChecklistEntry::where('control_id', $this->control->id)
            ->whereHas('session', fn ($q) => $q->where('periode', now()->format('Y-m')))
            ->where('unit_id', $this->unit->id)->firstOrFail()->status);
    }

    public function test_us_c5_rollover_rerun_does_not_duplicate_or_reset_carried_rows(): void
    {
        $this->seedPreviousVerified();

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();
        $count = ChecklistEntry::whereHas('session', fn ($q) => $q->where('periode', now()->format('Y-m')))->count();

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $rows = ChecklistEntry::whereHas('session', fn ($q) => $q->where('periode', now()->format('Y-m')))->get();
        $this->assertCount($count, $rows);
        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $rows->first()->status);
        $this->assertSame(4, $rows->first()->level_maturity);
    }

    public function test_us_c5_rollover_skips_soft_deleted_entry_of_current_period(): void
    {
        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $entry = ChecklistEntry::where('unit_id', $this->unit->id)->firstOrFail();
        $entry->update([
            'catatan' => 'Kerja PIC bulan ini',
            'level_maturity' => 3,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);
        $entry->delete();

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        // `pluck('control_id')` skips trashed rows, so the clause is re-provisioned
        $this->assertSame(2, ChecklistEntry::withTrashed()->where('unit_id', $this->unit->id)->count());
        $resurrected = ChecklistEntry::where('unit_id', $this->unit->id)
            ->where('control_id', $entry->control_id)->firstOrFail();
        $this->assertNotSame($entry->id, $resurrected->id);
        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $resurrected->status);
        $this->assertSame('', $resurrected->catatan);
        $this->assertNull($resurrected->level_maturity);
    }

    /**
     * SPEC-GAP — `GenerateMonthlyChecklistCommand::handle()` falls back to
     * `User::…->whereHas('role', pic)->first()` when a unit has no PIC
     * (GenerateMonthlyChecklistCommand.php:66), so a unit-less-of-PIC gets its
     * rows owned by another unit's PIC. US-G2 says a PIC only ever sees their
     * own subtree, so the misassigned row leaks into the wrong unit's queue
     * (and the fallback PIC cannot edit it: the web entry controller gates on
     * `pic_id`, so the unit ends up with rows nobody can fill).
     */
    public function test_us_c5_rollover_assigns_rows_to_a_pic_from_another_unit_when_unit_has_none(): void
    {
        $orphan = WorkUnit::create(['nama' => 'Unit Tanpa PIC']);

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $row = ChecklistEntry::where('unit_id', $orphan->id)->firstOrFail();
        $this->assertSame(
            $this->pic->id,
            $row->pic_id,
            'SPEC-GAP: should be null (no PIC for this unit), not another unit\'s PIC'
        );
    }

    /**
     * SPEC-GAP — US-C5 is "100% verified rollover" for every generator path, but
     * `Api\ChecklistEntryController::generateMonthly()` never calls
     * `CarryForwardVerifiedMap`, so hits on `POST /api/checklist-entries/generate-monthly`
     * create a fresh period with everything `belum_dimulai`. Console + web routes do roll over.
     */
    public function test_us_c5_gap_api_generate_monthly_does_not_carry_forward(): void
    {
        $this->seedPreviousVerified();

        $this->actingAs($this->admin)
            ->postJson('/api/checklist-entries/generate-monthly')
            ->assertOk();

        $new = ChecklistEntry::whereHas('session', fn ($q) => $q->where('periode', now()->format('Y-m')))
            ->where('unit_id', $this->unit->id)->firstOrFail();

        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $new->status, 'SPEC-GAP: should carry over');
        $this->assertNull($new->level_maturity, 'SPEC-GAP: should carry 4');
    }
}
