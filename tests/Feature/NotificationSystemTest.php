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
use App\Notifications\FindingCreatedNotification;
use App\Notifications\FindingStatusChangedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $picA;

    protected WorkUnit $unitA;

    protected Control $control;

    protected ChecklistSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $adminRole = Role::where('name', 'admin_kepatuhan')->first();
        $picRole = Role::where('name', 'pic')->first();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Biro Teknologi Informasi']);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'unit_id' => null,
        ]);

        $this->picA = User::factory()->create([
            'role_id' => $picRole->id,
            'unit_id' => $this->unitA->id,
        ]);

        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->control = Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.8.8',
            'judul' => 'Manajemen Kerentanan Teknis',
        ]);

        $this->session = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_creating_finding_sends_notification_to_pic(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(14)->format('Y-m-d'),
            'catatan' => 'Ditemukan server web belum menerapkan security update patch.',
        ]);

        $response->assertCreated();

        Notification::assertSentTo(
            $this->picA,
            FindingCreatedNotification::class,
            function ($notification) {
                $mail = $notification->toMail($this->picA);
                $this->assertStringContainsString('A.8.8', $mail->subject);
                $this->assertEquals('emails.finding-created', $mail->view);
                $this->assertEquals($this->admin->name, $mail->viewData['actorName']);

                $dbData = $notification->toDatabase($this->picA);
                $this->assertEquals('finding_created', $dbData['type']);
                $this->assertEquals('danger', $dbData['severity']);

                return true;
            }
        );
    }

    public function test_pic_updating_finding_status_sends_notification_to_admin(): void
    {
        Notification::fake();

        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Sedang melakukan instalasi patch sistem.',
        ]);

        $response->assertOk();

        Notification::assertSentTo(
            $this->admin,
            FindingStatusChangedNotification::class,
            function ($notification) {
                $mail = $notification->toMail($this->admin);
                $this->assertStringContainsString('A.8.8', $mail->subject);
                $this->assertStringContainsString('Dalam Penanganan', $mail->subject);

                $dbData = $notification->toDatabase($this->admin);
                $this->assertEquals('finding_status_changed', $dbData['type']);
                $this->assertEquals('in_progress', $dbData['to_status']);

                return true;
            }
        );
    }

    public function test_admin_updating_finding_status_sends_notification_to_pic(): void
    {
        Notification::fake();

        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_RESOLVED,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_CLOSED,
            'catatan' => 'Verifikasi bukti tuntas dan disetujui.',
        ]);

        $response->assertOk();

        Notification::assertSentTo(
            $this->picA,
            FindingStatusChangedNotification::class,
            function ($notification) {
                $dbData = $notification->toDatabase($this->picA);
                $this->assertEquals('finding_status_changed', $dbData['type']);
                $this->assertEquals('closed', $dbData['to_status']);
                $this->assertEquals('success', $dbData['severity']);

                return true;
            }
        );
    }

    public function test_rejecting_checklist_entry_sends_notification_to_pic(): void
    {
        Notification::fake();

        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => 'pending_verification',
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
            'status' => 'non_compliant',
            'admin_notes' => 'Dokumen SOP belum ditandatangani oleh pimpinan satker.',
        ]);

        $response->assertRedirect();

        Notification::assertSentTo(
            $this->picA,
            ChecklistEntryRejectedNotification::class,
            function ($notification) {
                $mail = $notification->toMail($this->picA);
                $this->assertStringContainsString('A.8.8', $mail->subject);
                $this->assertStringContainsString('Tidak Patuh', $mail->subject);

                $dbData = $notification->toDatabase($this->picA);
                $this->assertEquals('checklist_rejected', $dbData['type']);
                $this->assertEquals('danger', $dbData['severity']);
                $this->assertStringContainsString('belum ditandatangani', $dbData['catatan_admin']);

                return true;
            }
        );
    }

    public function test_bulk_rejecting_checklist_entries_sends_notification_to_pic(): void
    {
        Notification::fake();

        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => 'pending_verification',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'status' => 'non_compliant',
            'admin_notes' => 'Bukti implementasi belum memenuhi klausul.',
        ]);

        $response->assertOk();

        Notification::assertSentTo(
            $this->picA,
            ChecklistEntryRejectedNotification::class
        );
    }

    public function test_notifications_api_endpoints_and_lifecycle(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        // Send real in-app database notifications
        $this->picA->notify(new FindingCreatedNotification($finding, $this->admin, 'Catatan temuan 1'));
        $this->picA->notify(new FindingStatusChangedNotification($finding, $this->admin, 'open', 'in_progress', 'Catatan temuan 2'));

        $this->assertEquals(2, $this->picA->unreadNotifications()->count());

        // 1. Get unread count
        $countRes = $this->actingAs($this->picA)->getJson('/api/v1/notifications/unread-count');
        $countRes->assertOk()->assertJson(['status' => 'success', 'unread_count' => 2]);

        // 2. Get list of notifications
        $listRes = $this->actingAs($this->picA)->getJson('/api/v1/notifications');
        $listRes->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'type', 'data', 'read_at', 'is_read', 'created_at'],
                ],
                'unread_count',
                'pagination',
            ])
            ->assertJson(['unread_count' => 2]);

        $notifId = $listRes->json('data.0.id');

        // 3. Mark single notification as read
        $markRes = $this->actingAs($this->picA)->postJson("/api/v1/notifications/{$notifId}/read");
        $markRes->assertOk()->assertJson(['status' => 'success', 'unread_count' => 1]);

        // 4. Mark all as read
        $markAllRes = $this->actingAs($this->picA)->postJson('/api/v1/notifications/read-all');
        $markAllRes->assertOk()->assertJson(['status' => 'success', 'unread_count' => 0]);

        $this->assertEquals(0, $this->picA->unreadNotifications()->count());

        // 5. Delete notification
        $delRes = $this->actingAs($this->picA)->deleteJson("/api/v1/notifications/{$notifId}");
        $delRes->assertOk()->assertJson(['status' => 'success', 'unread_count' => 0]);
    }

    public function test_index_with_unread_filter_returns_only_unread(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $this->picA->notify(new FindingCreatedNotification($finding, $this->admin, 'Unread notif'));
        $this->picA->notify(new FindingStatusChangedNotification($finding, $this->admin, 'open', 'in_progress', 'Also unread'));

        // Mark second one read
        $this->picA->unreadNotifications()->first()->markAsRead();

        $res = $this->actingAs($this->picA)->getJson('/api/v1/notifications?unread=1');
        $res->assertOk();

        // unread=1 via boolean() → true → uses unreadNotifications()
        $data = $res->json('data');
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_read'] === false);
        $this->assertEquals(1, $res->json('unread_count'));
    }

    public function test_mark_as_read_nonexistent_id_returns_404(): void
    {
        $fakeId = (string) Str::uuid();
        $res = $this->actingAs($this->picA)->postJson("/api/v1/notifications/{$fakeId}/read");
        $res->assertNotFound()
            ->assertJson(['status' => 'error']);
    }

    public function test_destroy_nonexistent_id_returns_404(): void
    {
        $fakeId = (string) Str::uuid();
        $res = $this->actingAs($this->picA)->deleteJson("/api/v1/notifications/{$fakeId}");
        $res->assertNotFound()
            ->assertJson(['status' => 'error']);
    }

    public function test_user_isolation_cannot_read_other_users_notification(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $this->picA->notify(new FindingCreatedNotification($finding, $this->admin, 'Pic notification'));
        $notifId = $this->picA->unreadNotifications()->first()->id;

        // Admin tries to mark pic's notification as read → 404 (scoped to user)
        $res = $this->actingAs($this->admin)->postJson("/api/v1/notifications/{$notifId}/read");
        $res->assertNotFound();
    }

    public function test_user_isolation_cannot_delete_other_users_notification(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $this->picA->notify(new FindingCreatedNotification($finding, $this->admin, 'Pic notification'));
        $notifId = $this->picA->unreadNotifications()->first()->id;

        // Admin tries to delete pic's notification → 404
        $res = $this->actingAs($this->admin)->deleteJson("/api/v1/notifications/{$notifId}");
        $res->assertNotFound();
    }

    public function test_mark_as_read_already_read_is_noop(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $this->picA->notify(new FindingCreatedNotification($finding, $this->admin, 'Already read'));
        $notifId = $this->picA->unreadNotifications()->first()->id;

        // Mark as read once
        $this->actingAs($this->picA)->postJson("/api/v1/notifications/{$notifId}/read")->assertOk();
        $this->assertEquals(0, $this->picA->unreadNotifications()->count());

        // Mark as read again — still 200, no error, count stays 0
        $res = $this->actingAs($this->picA)->postJson("/api/v1/notifications/{$notifId}/read");
        $res->assertOk()->assertJson(['status' => 'success', 'unread_count' => 0]);
    }

    public function test_unread_count_endpoint_returns_correct_count(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $this->picA->notify(new FindingCreatedNotification($finding, $this->admin, 'Notif 1'));
        $this->picA->notify(new FindingCreatedNotification($finding, $this->admin, 'Notif 2'));
        $this->picA->notify(new FindingCreatedNotification($finding, $this->admin, 'Notif 3'));

        $res = $this->actingAs($this->picA)->getJson('/api/v1/notifications/unread-count');
        $res->assertOk()->assertJson(['status' => 'success', 'unread_count' => 3]);

        // Mark one read
        $this->picA->unreadNotifications()->first()->markAsRead();

        $res = $this->actingAs($this->picA)->getJson('/api/v1/notifications/unread-count');
        $res->assertOk()->assertJson(['status' => 'success', 'unread_count' => 2]);
    }

    public function test_finding_created_notification_to_database_shape(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => 'major',
            'deadline' => now()->addDays(14),
        ]);

        $notification = new FindingCreatedNotification($finding, $this->admin, 'Catatan temuan');
        $dbData = $notification->toDatabase($this->picA);

        $this->assertArrayHasKey('type', $dbData);
        $this->assertEquals('finding_created', $dbData['type']);
        $this->assertArrayHasKey('title', $dbData);
        $this->assertStringContainsString('A.8.8', $dbData['title']);
        $this->assertArrayHasKey('message', $dbData);
        $this->assertArrayHasKey('finding_id', $dbData);
        $this->assertEquals($finding->id, $dbData['finding_id']);
        $this->assertArrayHasKey('kategori', $dbData);
        $this->assertEquals('major', $dbData['kategori']);
        $this->assertArrayHasKey('deadline', $dbData);
        $this->assertArrayHasKey('catatan', $dbData);
        $this->assertEquals('Catatan temuan', $dbData['catatan']);
        $this->assertArrayHasKey('url', $dbData);
        $this->assertStringContainsString("id={$finding->id}", $dbData['url']);
        $this->assertArrayHasKey('actor_id', $dbData);
        $this->assertEquals($this->admin->id, $dbData['actor_id']);
        $this->assertArrayHasKey('actor_name', $dbData);
        $this->assertEquals($this->admin->name, $dbData['actor_name']);
        $this->assertArrayHasKey('severity', $dbData);
    }

    public function test_finding_created_notification_severity_mapping(): void
    {
        $baseAttrs = [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ];

        $majorFinding = Finding::factory()->create([...$baseAttrs, 'kategori' => 'major']);
        $minorFinding = Finding::factory()->create([...$baseAttrs, 'kategori' => 'minor']);
        $obsFinding = Finding::factory()->create([...$baseAttrs, 'kategori' => 'observasi']);

        $majorDb = (new FindingCreatedNotification($majorFinding, $this->admin))->toDatabase($this->picA);
        $minorDb = (new FindingCreatedNotification($minorFinding, $this->admin))->toDatabase($this->picA);
        $obsDb = (new FindingCreatedNotification($obsFinding, $this->admin))->toDatabase($this->picA);

        $this->assertEquals('danger', $majorDb['severity']);
        $this->assertEquals('warning', $minorDb['severity']);
        $this->assertEquals('info', $obsDb['severity']);
    }

    public function test_finding_status_changed_notification_to_database_shape(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $notification = new FindingStatusChangedNotification(
            $finding, $this->admin, 'open', 'in_progress', 'Sedang ditangani'
        );
        $dbData = $notification->toDatabase($this->picA);

        $this->assertEquals('finding_status_changed', $dbData['type']);
        $this->assertStringContainsString('A.8.8', $dbData['title']);
        $this->assertArrayHasKey('message', $dbData);
        $this->assertArrayHasKey('finding_id', $dbData);
        $this->assertEquals($finding->id, $dbData['finding_id']);
        $this->assertEquals('open', $dbData['from_status']);
        $this->assertEquals('in_progress', $dbData['to_status']);
        $this->assertEquals('Sedang ditangani', $dbData['catatan']);
        $this->assertArrayHasKey('url', $dbData);
        $this->assertEquals($this->admin->id, $dbData['actor_id']);
        $this->assertEquals($this->admin->name, $dbData['actor_name']);
        $this->assertArrayHasKey('severity', $dbData);
    }

    public function test_finding_status_changed_notification_severity_by_status(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $cases = [
            ['from' => 'open', 'to' => 'open', 'expected' => 'danger'],
            ['from' => 'open', 'to' => 'in_progress', 'expected' => 'warning'],
            ['from' => 'in_progress', 'to' => 'resolved', 'expected' => 'info'],
            ['from' => 'resolved', 'to' => 'closed', 'expected' => 'success'],
        ];

        foreach ($cases as $case) {
            $dbData = (new FindingStatusChangedNotification(
                $finding, $this->admin, $case['from'], $case['to']
            ))->toDatabase($this->picA);

            $this->assertEquals(
                $case['expected'],
                $dbData['severity'],
                "Severity for {$case['from']}→{$case['to']} should be {$case['expected']}"
            );
        }
    }

    public function test_checklist_entry_rejected_notification_to_database_shape(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => 'pending_verification',
        ]);

        $notification = new ChecklistEntryRejectedNotification(
            $entry, $this->admin, 'Bukti belum lengkap'
        );
        $dbData = $notification->toDatabase($this->picA);

        $this->assertEquals('checklist_rejected', $dbData['type']);
        $this->assertStringContainsString('A.8.8', $dbData['title']);
        $this->assertArrayHasKey('message', $dbData);
        $this->assertEquals($entry->id, $dbData['entry_id']);
        $this->assertEquals($this->session->id, $dbData['session_id']);
        $this->assertEquals($this->control->id, $dbData['control_id']);
        $this->assertEquals('Bukti belum lengkap', $dbData['catatan_admin']);
        $this->assertStringContainsString("/admin/pic/checklist/{$this->session->id}", $dbData['url']);
        $this->assertEquals($this->admin->id, $dbData['actor_id']);
        $this->assertEquals($this->admin->name, $dbData['actor_name']);
        $this->assertEquals('danger', $dbData['severity']);
    }

    public function test_finding_status_changed_pic_actor_uses_pic_to_admin_view(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_IN_PROGRESS,
        ]);

        $notification = new FindingStatusChangedNotification(
            $finding, $this->picA, 'in_progress', 'resolved', 'Patch applied'
        );
        $mail = $notification->toMail($this->admin);

        $this->assertEquals('emails.finding-status-pic-to-admin', $mail->view);
        $this->assertStringContainsString('A.8.8', $mail->subject);
        $this->assertStringContainsString('Selesai Ditindaklanjuti', $mail->subject);
    }

    public function test_finding_status_changed_admin_actor_uses_admin_to_pic_view(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_RESOLVED,
        ]);

        $notification = new FindingStatusChangedNotification(
            $finding, $this->admin, 'resolved', 'closed', 'Verified'
        );
        $mail = $notification->toMail($this->picA);

        $this->assertEquals('emails.finding-status-admin-to-pic', $mail->view);
        $this->assertStringContainsString('A.8.8', $mail->subject);
        $this->assertStringContainsString('Disetujui & Ditutup', $mail->subject);
    }

    public function test_send_test_email_command_returns_failure_without_recipient(): void
    {
        $this->app['config']->set('mail.always_to', null);
        $this->app['config']->set('mail.from.address', null);

        $this->artisan('smki:test-email')->assertExitCode(1);
    }

    public function test_email_notification_renders_correct_view(): void
    {
        Notification::fake();

        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $notification = new FindingCreatedNotification($finding, $this->admin, 'Test catatan');
        $mail = $notification->toMail($this->picA);

        $this->assertEquals('emails.finding-created', $mail->view);
        $this->assertStringContainsString('[SMKI] Temuan Audit Baru', $mail->subject);
        $this->assertStringContainsString('A.8.8', $mail->subject);
        $this->assertEquals($this->admin->name, $mail->viewData['actorName']);
        $this->assertEquals('Test catatan', $mail->viewData['catatan']);
    }
}
