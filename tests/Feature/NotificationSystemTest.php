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
}
