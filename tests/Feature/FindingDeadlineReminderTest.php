<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use App\Notifications\FindingDeadlineReminderNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FindingDeadlineReminderTest extends TestCase
{
    protected User $admin;

    protected User $picA;

    protected WorkUnit $unitA;

    protected Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'admin_kepatuhan')->first();
        $picRole = Role::where('name', 'pic')->first();

        $this->unitA = WorkUnit::factory()->create();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'unit_id' => null]);
        $this->picA = User::factory()->create(['role_id' => $picRole->id, 'unit_id' => $this->unitA->id]);

        $framework = Framework::factory()->create();
        $this->control = Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.8.8',
            'judul' => 'Manajemen Kerentanan Teknis',
        ]);
    }

    public function test_notification_shape(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MAJOR,
            'deadline' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $notification = new FindingDeadlineReminderNotification($finding, 7);

        $this->assertEquals(['database', 'mail'], $notification->via($this->picA));

        $db = $notification->toDatabase($this->picA);
        $this->assertEquals('finding_deadline_reminder', $db['type']);
        $this->assertEquals($finding->id, $db['finding_id']);
        $this->assertEquals(7, $db['days_remaining']);
        $this->assertStringContainsString("id={$finding->id}", $db['url']);
        $this->assertArrayHasKey('deadline', $db);
        $this->assertArrayHasKey('severity', $db);

        $mail = $notification->toMail($this->picA);
        $this->assertStringContainsString('A.8.8', $mail->subject);
        $this->assertStringContainsString('H-7', $mail->subject);
    }

    /**
     * @dataProvider thresholdProvider
     */
    public function test_command_notifies_pic_at_thresholds(int $days): void
    {
        Notification::fake();

        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays($days)->format('Y-m-d'),
        ]);

        $this->artisan('smki:remind-finding-deadlines')->assertExitCode(0);

        Notification::assertSentTo(
            $this->picA,
            FindingDeadlineReminderNotification::class,
            fn ($n) => $n->toDatabase($this->picA)['finding_id'] === $finding->id
                && $n->toDatabase($this->picA)['days_remaining'] === $days
        );
    }

    public static function thresholdProvider(): array
    {
        return [[7], [3], [1]];
    }

    public function test_command_skips_closed_and_null_deadline(): void
    {
        Notification::fake();

        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_CLOSED,
            'deadline' => now()->addDays(3)->format('Y-m-d'),
        ]);
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => null,
        ]);

        $this->artisan('smki:remind-finding-deadlines')->assertExitCode(0);

        Notification::assertNotSentTo($this->picA, FindingDeadlineReminderNotification::class);
    }

    public function test_command_notifies_overdue_daily(): void
    {
        Notification::fake();

        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_IN_PROGRESS,
            'deadline' => now()->subDay()->format('Y-m-d'),
        ]);

        $this->artisan('smki:remind-finding-deadlines')->assertExitCode(0);

        Notification::assertSentTo(
            $this->picA,
            FindingDeadlineReminderNotification::class,
            fn ($n) => $n->toDatabase($this->picA)['finding_id'] === $finding->id
                && $n->toDatabase($this->picA)['days_remaining'] < 0
        );
    }

    public function test_command_no_duplicate_same_day(): void
    {
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $this->artisan('smki:remind-finding-deadlines')->assertExitCode(0);
        $this->assertEquals(1, $this->picA->unreadNotifications()->count());

        $this->artisan('smki:remind-finding-deadlines')->assertExitCode(0);
        $this->assertEquals(1, $this->picA->fresh()->unreadNotifications()->count());
    }

    public function test_command_falls_back_to_unit_pic(): void
    {
        Notification::fake();

        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => null,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $this->artisan('smki:remind-finding-deadlines')->assertExitCode(0);

        Notification::assertSentTo(
            $this->picA,
            FindingDeadlineReminderNotification::class,
            fn ($n) => $n->toDatabase($this->picA)['finding_id'] === $finding->id
        );
    }

    public function test_command_skips_due_today_h0(): void
    {
        Notification::fake();

        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->format('Y-m-d'),
        ]);

        $this->artisan('smki:remind-finding-deadlines')->assertExitCode(0);

        Notification::assertNotSentTo($this->picA, FindingDeadlineReminderNotification::class);
    }

    public function test_command_includes_resolved_status(): void
    {
        Notification::fake();

        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_RESOLVED,
            'deadline' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $this->artisan('smki:remind-finding-deadlines')->assertExitCode(0);

        Notification::assertSentTo(
            $this->picA,
            FindingDeadlineReminderNotification::class,
            fn ($n) => $n->toDatabase($this->picA)['finding_id'] === $finding->id
        );
    }
}
