<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use App\Notifications\ChecklistEntryRejectedNotification;
use App\Notifications\ChecklistUnfilledReminderNotification;
use App\Notifications\FindingCreatedNotification;
use App\Notifications\FindingDeadlineReminderNotification;
use App\Notifications\FindingStatusChangedNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FUNCTIONAL_SPEC.md §6 US-N3 — notification dispatch matrix: real persisted-row
 * coverage (no Notification::fake, relations intact through toDatabase/toMail)
 * plus fake-based spec matrix (N-e, approve-silence, N-b fan-out, unit-PIC
 * fallback, ShouldQueue/mail+database contract, dev-only surfaces).
 * Merged from NotificationRealDispatchTest + NotificationSpecComplianceTest.
 */
class NotificationDispatchTest extends TestCase
{
    protected User $admin;

    protected User $picA;

    protected WorkUnit $unitA;

    protected Control $control;

    protected ChecklistSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Biro Teknologi Informasi']);
        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', User::ROLE_ADMIN_KEPATUHAN)->value('id'),
            'unit_id' => null,
        ]);
        $this->picA = User::factory()->create([
            'role_id' => Role::where('name', User::ROLE_PIC)->value('id'),
            'unit_id' => $this->unitA->id,
        ]);

        $this->control = Control::factory()->create([
            'framework_id' => Framework::factory()->create()->id,
            'kode_klausul' => 'A.8.8',
            'judul' => 'Manajemen Kerentanan Teknis',
        ]);

        $this->session = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
        ]);
    }

    private function makeEntry(array $overrides = []): ChecklistEntry
    {
        return ChecklistEntry::factory()->create(array_merge([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ], $overrides));
    }

    private function makeFinding(array $overrides = []): Finding
    {
        return Finding::factory()->create(array_merge([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ], $overrides));
    }

    // ── N-e: single reject via the JSON API ───────────────────────────────────

    public function test_api_single_reject_notifies_pic_with_catatan_admin(): void
    {
        Notification::fake();

        $entry = $this->makeEntry();

        $this->actingAs($this->admin)
            ->patchJson("/api/checklist-entries/{$entry->id}/verify", [
                'admin_id' => $this->admin->id,
                'decision' => 'reject',
                'catatan_admin' => 'SOP belum ditandatangani pimpinan satker.',
            ])
            ->assertOk();

        Notification::assertSentTo(
            $this->picA,
            ChecklistEntryRejectedNotification::class,
            function (ChecklistEntryRejectedNotification $n) use ($entry) {
                $db = $n->toDatabase($this->picA);

                $this->assertSame('checklist_rejected', $db['type']);
                $this->assertSame('SOP belum ditandatangani pimpinan satker.', $db['catatan_admin']);
                $this->assertSame($entry->id, $db['entry_id']);
                $this->assertSame($this->admin->name, $db['actor_name']);
                $this->assertSame('emails.checklist-rejected', $n->toMail($this->picA)->view);

                return true;
            }
        );
    }

    // ── "approve sends nothing" (US-N3) ───────────────────────────────────────

    public function test_api_single_approve_sends_no_notification(): void
    {
        Notification::fake();

        $entry = $this->makeEntry();

        $this->actingAs($this->admin)
            ->patchJson("/api/checklist-entries/{$entry->id}/verify", [
                'admin_id' => $this->admin->id,
                'decision' => 'approve',
            ])
            ->assertOk();

        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $entry->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_web_single_approve_sends_no_notification(): void
    {
        Notification::fake();

        $entry = $this->makeEntry();

        $this->actingAs($this->admin)
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $entry->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_bulk_approve_sends_no_notification(): void
    {
        Notification::fake();

        $entries = collect(range(1, 3))->map(fn () => $this->makeEntry());

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => $entries->pluck('id')->all(),
            'decision' => 'approve',
        ])->assertOk();

        $entries->each(fn (ChecklistEntry $e) => $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $e->fresh()->status)
        );
        Notification::assertNothingSent();
    }

    // ── N-b: fan-out to every admin_kepatuhan, superadmin excluded ────────────

    public function test_pic_status_change_notifies_every_admin_kepatuhan_but_not_superadmin(): void
    {
        Notification::fake();

        $adminB = User::factory()->create([
            'role_id' => Role::where('name', User::ROLE_ADMIN_KEPATUHAN)->value('id'),
            'unit_id' => null,
        ]);
        $superadmin = User::factory()->create([
            'role_id' => Role::where('name', User::ROLE_SUPERADMIN)->value('id'),
            'unit_id' => null,
        ]);
        $auditor = User::factory()->create([
            'role_id' => Role::where('name', User::ROLE_AUDITOR)->value('id'),
            'unit_id' => null,
        ]);

        $finding = $this->makeFinding();

        $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Sedang memasang patch keamanan.',
        ])->assertOk();

        // one row + one mail per admin_kepatuhan
        Notification::assertSentToTimes($this->admin, FindingStatusChangedNotification::class, 1);
        Notification::assertSentToTimes($adminB, FindingStatusChangedNotification::class, 1);
        Notification::assertSentToTimes($this->picA, FindingStatusChangedNotification::class, 0);

        // spec scopes N-b to admin_kepatuhan only
        Notification::assertNotSentTo($superadmin, FindingStatusChangedNotification::class);
        Notification::assertNotSentTo($auditor, FindingStatusChangedNotification::class);
    }

    public function test_pic_to_admin_payload_carries_from_to_status_and_actor(): void
    {
        Notification::fake();

        $finding = $this->makeFinding(['status' => Finding::STATUS_OPEN]);

        $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_RESOLVED,
            'catatan' => 'Patch sudah terpasang.',
        ])->assertOk();

        Notification::assertSentTo($this->admin, FindingStatusChangedNotification::class, function ($n) {
            $db = $n->toDatabase($this->admin);

            $this->assertSame('finding_status_changed', $db['type']);
            $this->assertSame(Finding::STATUS_OPEN, $db['from_status']);
            $this->assertSame(Finding::STATUS_RESOLVED, $db['to_status']);
            $this->assertSame($this->picA->id, $db['actor_id']);
            $this->assertSame('emails.finding-status-pic-to-admin', $n->toMail($this->admin)->view);
            $this->assertStringStartsWith(
                '[SMKI] Laporan Tindak Lanjut Temuan: A.8.8 (Selesai Ditindaklanjuti',
                $n->toMail($this->admin)->subject
            );

            return true;
        });
    }

    public function test_admin_to_pic_subject_and_view_match_spec(): void
    {
        Notification::fake();

        $finding = $this->makeFinding(['status' => Finding::STATUS_RESOLVED]);

        $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_CLOSED,
            'catatan' => 'Bukti tuntas.',
        ])->assertOk();

        Notification::assertSentTo($this->picA, FindingStatusChangedNotification::class, function ($n) {
            $mail = $n->toMail($this->picA);

            $this->assertSame('emails.finding-status-admin-to-pic', $mail->view);
            $this->assertSame(
                '[SMKI] Hasil Verifikasi Temuan: A.8.8 (Disetujui & Ditutup)',
                $mail->subject
            );

            return true;
        });
    }

    // ── Unit-PIC fallback routing (spec: "unit PIC fallback") ────────────────

    public function test_finding_created_falls_back_to_unit_pic_when_pic_id_is_null(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => null,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'catatan' => 'Server belum dipatch.',
        ])->assertCreated();

        Notification::assertSentTo($this->picA, FindingCreatedNotification::class);
        // the publishing admin is never notified of their own action
        Notification::assertNotSentTo($this->admin, FindingCreatedNotification::class);
    }

    public function test_findings_pic_id_is_not_nullable_so_the_status_change_fallback_is_unreachable(): void
    {
        // ComplianceOfficerService.php:273 guards `$freshFinding->pic ?? <unit PIC>`,
        // but findings.pic_id is NOT NULL, so the fallback arm can never run for a
        // persisted finding. Only checklist_entries.pic_id is nullable.
        $this->assertFalse(
            Schema::getColumnType('findings', 'pic_id') === null,
            'findings.pic_id column must exist for this guard to be meaningful'
        );
        $this->assertTrue(
            collect(Schema::getColumns('findings'))->firstWhere('name', 'pic_id')['nullable'] === false,
            'findings.pic_id is NOT NULL, so ComplianceOfficerService.php:273 unit-PIC fallback is dead code'
        );
    }

    public function test_web_checklist_reject_falls_back_to_unit_pic_when_pic_id_is_null(): void
    {
        Notification::fake();

        $entry = $this->makeEntry(['pic_id' => null]);

        $this->actingAs($this->admin)->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
            'decision' => 'reject',
            'admin_notes' => 'Bukti belum memadai.',
        ])->assertRedirect();

        Notification::assertSentTo($this->picA, ChecklistEntryRejectedNotification::class);
    }

    public function test_api_checklist_reject_falls_back_to_unit_pic_when_pic_id_is_null(): void
    {
        Notification::fake();

        $entry = $this->makeEntry(['pic_id' => null]);

        $this->actingAs($this->admin)->patchJson("/api/checklist-entries/{$entry->id}/verify", [
            'admin_id' => $this->admin->id,
            'decision' => 'reject',
            'catatan_admin' => 'Bukti belum memadai.',
        ])->assertOk();

        Notification::assertSentTo($this->picA, ChecklistEntryRejectedNotification::class);
    }

    // ── N-f: one notification per entry, routed to each entry's own PIC ───────

    // Regression: bulk-reject used to 500 on lazy-loaded `pic` (Model::preventLazyLoading);
    // the bulk fetch eager-loads pic/control/session, so each PIC is notified once per own entry.
    public function test_bulk_reject_notifies_each_pic_once_per_their_own_entry(): void
    {
        Notification::fake();

        $unitB = WorkUnit::factory()->create();
        $picB = User::factory()->create([
            'role_id' => Role::where('name', User::ROLE_PIC)->value('id'),
            'unit_id' => $unitB->id,
        ]);

        $sessionB = ChecklistSession::factory()->create([
            'unit_id' => $unitB->id,
            'created_by' => $this->admin->id,
        ]);

        $entryA1 = $this->makeEntry();
        $entryA2 = $this->makeEntry();
        $entryB1 = ChecklistEntry::factory()->create([
            'session_id' => $sessionB->id,
            'control_id' => $this->control->id,
            'unit_id' => $unitB->id,
            'pic_id' => $picB->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entryA1->id, $entryA2->id, $entryB1->id],
            'decision' => 'reject',
            'admin_notes' => 'Seluruh bukti perlu dilengkapi.',
        ])->assertOk();

        Notification::assertSentToTimes($this->picA, ChecklistEntryRejectedNotification::class, 2);
        Notification::assertSentToTimes($picB, ChecklistEntryRejectedNotification::class, 1);
        Notification::assertNothingSentTo($this->admin);

        Notification::assertSentTo($this->picA, ChecklistEntryRejectedNotification::class, function ($n) use ($entryA1, $entryA2) {
            $this->assertContains($n->toDatabase($this->picA)['entry_id'], [$entryA1->id, $entryA2->id]);

            return true;
        });
    }

    // ── Channel contract: every production notification is queued, mail+db ────

    public function test_every_production_notification_is_queued_and_uses_mail_plus_database(): void
    {
        $unitB = WorkUnit::factory()->create();

        $instances = [
            new FindingCreatedNotification($this->makeFinding(), $this->admin, 'catatan'),
            new FindingStatusChangedNotification($this->makeFinding(), $this->picA, 'open', 'in_progress', 'catatan'),
            new ChecklistEntryRejectedNotification($this->makeEntry(), $this->admin, 'catatan'),
            new FindingDeadlineReminderNotification($this->makeFinding(['deadline' => now()->addDays(7)]), 7),
            new ChecklistUnfilledReminderNotification($this->session, 4, 10),
        ];

        foreach ($instances as $notification) {
            $this->assertInstanceOf(ShouldQueue::class, $notification, $notification::class);
            $this->assertSame(['database', 'mail'], $notification->via($this->picA), $notification::class);
        }

        $this->assertNotNull($unitB);
    }

    // ── Dev-only surfaces ────────────────────────────────────────────────────

    public function test_email_preview_gallery_is_not_routed_outside_local(): void
    {
        $this->assertFalse(app()->isLocal());

        $this->get('/email-preview')->assertNotFound();
        $this->get('/email-preview/1-1-finding-created')->assertNotFound();
        $this->get('/email-preview/2-1-checklist-rejected')->assertNotFound();
        $this->get('/email-preview/4-1-auth-reset-password')->assertNotFound();
    }

    public function test_dev_only_commands_are_registered(): void
    {
        $commands = array_keys($this->app[Kernel::class]->all());

        $this->assertContains('smki:test-email', $commands);
        $this->assertContains('notify:test', $commands);
        $this->assertContains('smki:remind-finding-deadlines', $commands);
        $this->assertContains('smki:remind-unfilled-checklists', $commands);
    }

    public function test_dev_commands_are_not_actually_restricted_to_local(): void
    {
        // Spec §6 calls smki:test-email + notify:test "dev-only". Only the
        // /email-preview HTTP gallery is wrapped in app()->isLocal(); both
        // Artisan commands are registered in every environment.
        $this->assertFalse(app()->isLocal());
        $before = DB::table('notifications')->count();

        $this->artisan('smki:test-email --to=nobody@example.test --template=1-1')
            ->assertSuccessful();

        // notify:test persists a real database notification row for the target user.
        $this->artisan('notify:test '.$this->picA->id)->assertSuccessful();

        $this->assertSame($before + 1, DB::table('notifications')->count());
        $this->assertSame(
            'finding_created',
            json_decode(DB::table('notifications')->latest('id')->value('data'), true)['type']
        );
    }

    public function test_inertia_shared_props_do_not_leak_notification_data(): void
    {
        $this->picA->notify(new FindingCreatedNotification(
            $this->makeFinding(),
            $this->admin,
            'rahasia-payload-probe'
        ));

        $targets = [
            '/admin/pic/dashboard' => $this->picA,
            '/temuan' => $this->picA,
            '/admin/kepatuhan/dashboard' => $this->admin,
        ];

        foreach ($targets as $url => $actor) {
            $response = $this->actingAs($actor)->get($url);
            $response->assertOk();

            $props = $response->viewData('page')['props'];

            $this->assertArrayNotHasKey('notifications', $props, $url);
            $this->assertArrayNotHasKey('unread_count', $props, $url);
            $this->assertStringNotContainsString(
                'rahasia-payload-probe',
                json_encode($props, JSON_THROW_ON_ERROR),
                $url
            );
        }
    }

    /**
     * Renders every §6 mail template through the real Blade stack so a missing
     * view variable or renamed component fails here instead of in a worker's inbox.
     */
    /**
     * US-N1 / US-N2 accept criteria in literal terms: one `notifications` row per
     * recipient (no Notification::fake here — this asserts real persisted rows).
     */
    public function test_real_database_rows_are_persisted_per_recipient(): void
    {
        // N-a: admin publishes a finding -> exactly one row for the target PIC.
        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(14)->format('Y-m-d'),
            'catatan' => 'Server belum dipatch.',
        ])->assertCreated();

        $this->assertSame(1, $this->picA->unreadNotifications()->count());
        $this->assertSame(0, $this->admin->unreadNotifications()->count());

        $rowA = DB::table('notifications')->where('notifiable_id', $this->picA->id)->first();
        $dataA = json_decode($rowA->data, true);

        $this->assertSame('finding_created', $dataA['type']);
        $this->assertSame('major', $dataA['kategori']);
        $this->assertSame(now()->addDays(14)->format('Y-m-d'), $dataA['deadline']);
        $this->assertSame($this->admin->name, $dataA['actor_name']);
        $this->assertStringStartsWith('/temuan?id=', $dataA['url']);

        // N-b: PIC moves the status -> one row for each of the two admins.
        $adminB = User::factory()->create([
            'role_id' => Role::where('name', User::ROLE_ADMIN_KEPATUHAN)->value('id'),
            'unit_id' => null,
        ]);
        $finding = $this->makeFinding();

        $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Progres patch.',
        ])->assertOk();

        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $adminB->unreadNotifications()->count());
        $this->assertSame(1, $this->picA->unreadNotifications()->count(), 'PIC must not be notified of their own action');

        $dataB = json_decode(
            DB::table('notifications')->where('notifiable_id', $adminB->id)->latest('created_at')->value('data'),
            true
        );

        $this->assertSame('finding_status_changed', $dataB['type']);
        $this->assertSame(Finding::STATUS_OPEN, $dataB['from_status']);
        $this->assertSame(Finding::STATUS_IN_PROGRESS, $dataB['to_status']);
        $this->assertSame($this->picA->id, $dataB['actor_id']);
    }

    public function test_every_mail_template_renders(): void
    {
        $finding = $this->makeFinding([
            'deadline' => now()->addDays(7)->format('Y-m-d'),
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);
        $entry = $this->makeEntry();

        $messages = [
            [(new FindingCreatedNotification($finding, $this->admin, 'Catatan temuan.'))->toMail($this->picA), 'A.8.8'],
            [(new FindingStatusChangedNotification($finding, $this->picA, 'open', 'in_progress', 'Progres.'))->toMail($this->admin), 'A.8.8'],
            [(new FindingStatusChangedNotification($finding, $this->admin, 'resolved', 'closed', 'Tuntas.'))->toMail($this->picA), 'A.8.8'],
            [(new ChecklistEntryRejectedNotification($entry, $this->admin, 'SOP belum ditandatangani.'))->toMail($this->picA), 'A.8.8'],
            [(new FindingDeadlineReminderNotification($finding, 7))->toMail($this->picA), 'A.8.8'],
            [(new FindingDeadlineReminderNotification($finding, -2))->toMail($this->picA), 'Overdue'],
            // session-scoped template: carries the periode, not a control code
            [(new ChecklistUnfilledReminderNotification($this->session, 4, 10))->toMail($this->picA), now()->format('Y-m')],
        ];

        foreach ($messages as [$message, $needle]) {
            $html = $message->render();

            $this->assertStringContainsString('<html', $html, $message->view);
            $this->assertStringContainsString('[SMKI]', $message->subject);
            $this->assertStringContainsString($needle, $html, $message->view);
        }
    }

    // ── GAP-N4 / GAP-N5: spec says "expect none", code ships both reminders ──
    public function test_gap_n4_and_n5_reminders_are_in_fact_implemented(): void
    {
        // Spec §6 GAP-N4/N5 record these as unimplemented. The repo has since
        // added RemindFindingDeadlinesCommand + RemindUnfilledChecklistsCommand
        // and schedules them daily at 08:00, so QA must NOT expect "none".
        $schedule = $this->app[Schedule::class];

        $commands = collect($schedule->events())->map(fn ($e) => $e->command)->implode('|');

        $this->assertStringContainsString('smki:remind-finding-deadlines', $commands);
        $this->assertStringContainsString('smki:remind-unfilled-checklists', $commands);

        Notification::fake();

        $finding = $this->makeFinding(['deadline' => now()->addDays(3)->format('Y-m-d')]);
        $this->artisan('smki:remind-finding-deadlines')->assertSuccessful();

        Notification::assertSentTo($this->picA, FindingDeadlineReminderNotification::class);
        Notification::assertNotSentTo($this->admin, FindingDeadlineReminderNotification::class);

        $this->assertNotNull($finding);
    }

    // ── Real dispatch: persisted rows, no fake ───────────────────────────────

    /**
     * N-d: ComplianceOfficerController.php:325 hands the notification
     * `$entry->fresh(['control', 'session'])`, so the queued model still has
     * every relation toDatabase()/toMail() read.
     */
    public function test_web_single_reject_persists_row_with_catatan_admin(): void
    {
        $entry = $this->makeEntry();

        $this->actingAs($this->admin)->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
            'decision' => 'reject',
            'admin_notes' => 'SOP belum ditandatangani pimpinan satker.',
        ])->assertRedirect();

        $this->assertSame(1, $this->picA->unreadNotifications()->count());

        $data = json_decode(
            DB::table('notifications')->where('notifiable_id', $this->picA->id)->value('data'),
            true
        );

        $this->assertSame('checklist_rejected', $data['type']);
        $this->assertSame($entry->id, $data['entry_id']);
        $this->assertSame('SOP belum ditandatangani pimpinan satker.', $data['catatan_admin']);
        $this->assertSame($this->control->id, $data['control_id']);
        $this->assertStringContainsString('A.8.8', $data['title'], 'title needs control->kode_klausul');
    }

    /** N-e: same accept criteria through the JSON API. */
    public function test_api_single_reject_persists_row_with_catatan_admin(): void
    {
        $entry = $this->makeEntry();

        $this->actingAs($this->admin)->patchJson("/api/checklist-entries/{$entry->id}/verify", [
            'admin_id' => $this->admin->id,
            'decision' => 'reject',
            'catatan_admin' => 'Bukti scanning tidak mencakup port database internal.',
        ])->assertOk();

        $this->assertSame(1, $this->picA->unreadNotifications()->count());

        $data = json_decode(
            DB::table('notifications')->where('notifiable_id', $this->picA->id)->value('data'),
            true
        );

        $this->assertSame('checklist_rejected', $data['type']);
        $this->assertSame('Bukti scanning tidak mencakup port database internal.', $data['catatan_admin']);
        $this->assertSame('danger', $data['severity']);
    }

    /**
     * The mail half of US-N3: the subject and body a PIC's inbox receives, built
     * from the same pre-loaded entry the controller passes to the notification.
     */
    public function test_reject_mail_carries_control_code_and_catatan(): void
    {
        $entry = $this->makeEntry();

        $notification = new ChecklistEntryRejectedNotification(
            $entry->fresh(['control', 'session']),
            $this->admin,
            'SOP belum ditandatangani pimpinan satker.'
        );

        $mail = $notification->toMail($this->picA);

        $this->assertSame('emails.checklist-rejected', $mail->view);
        $this->assertStringContainsString('[SMKI] Entri Ditolak (Perlu Perbaikan): A.8.8', $mail->subject);

        $html = $mail->render();

        $this->assertStringContainsString('Manajemen Kerentanan Teknis', $html);
        $this->assertStringContainsString('SOP belum ditandatangani pimpinan satker.', $html);
    }

    /** "approve sends nothing" (US-N3) asserted against persisted rows. */
    public function test_bulk_approve_persists_no_notification_rows(): void
    {
        $entries = collect(range(1, 2))->map(fn () => $this->makeEntry());

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => $entries->pluck('id')->all(),
            'decision' => 'approve',
        ])->assertOk();

        $this->assertSame(0, DB::table('notifications')->count());
    }
}
