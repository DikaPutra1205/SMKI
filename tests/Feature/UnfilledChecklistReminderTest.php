<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use App\Notifications\ChecklistUnfilledReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UnfilledChecklistReminderTest extends TestCase
{
    protected User $picA;

    protected WorkUnit $unitA;

    protected ChecklistSession $session;

    protected Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $picRole = Role::where('name', 'pic')->first();

        $this->unitA = WorkUnit::factory()->create();
        $this->picA = User::factory()->create(['role_id' => $picRole->id, 'unit_id' => $this->unitA->id]);

        $framework = Framework::factory()->create();
        $this->control = Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.5.1',
        ]);

        $this->session = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $framework->id,
            'periode' => now()->format('Y-m'),
        ]);
    }

    protected function freezeAtMonthEndMinus(int $days): void
    {
        $target = now()->endOfMonth()->subDays($days)->setTime(8, 0);
        Carbon::setTestNow($target);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_notification_shape(): void
    {
        $notification = new ChecklistUnfilledReminderNotification($this->session, 4, 10);

        $this->assertEquals(['database', 'mail'], $notification->via($this->picA));

        $db = $notification->toDatabase($this->picA);
        $this->assertEquals('checklist_unfilled_reminder', $db['type']);
        $this->assertEquals($this->session->id, $db['session_id']);
        $this->assertEquals(4, $db['unfilled_count']);
        $this->assertEquals(10, $db['total_count']);
        $this->assertStringContainsString((string) $this->session->id, $db['url']);

        $mail = $notification->toMail($this->picA);
        $this->assertStringContainsString($this->session->periode, $mail->subject);
    }

    public function test_command_sends_single_summary_on_threshold_date(): void
    {
        $this->freezeAtMonthEndMinus(3);
        Notification::fake();

        foreach (range(1, 3) as $i) {
            ChecklistEntry::factory()->create([
                'session_id' => $this->session->id,
                'control_id' => Control::factory()->create(['framework_id' => $this->session->framework_id])->id,
                'unit_id' => $this->unitA->id,
                'pic_id' => $this->picA->id,
                'status' => $i <= 2 ? ChecklistEntry::WORKFLOW_BELUM_DIMULAI : ChecklistEntry::WORKFLOW_SELESAI,
            ]);
        }

        $this->artisan('smki:remind-unfilled-checklists')->assertExitCode(0);

        Notification::assertSentToTimes($this->picA, ChecklistUnfilledReminderNotification::class, 1);
        Notification::assertSentTo(
            $this->picA,
            ChecklistUnfilledReminderNotification::class,
            fn ($n) => $n->toDatabase($this->picA)['unfilled_count'] === 2
                && $n->toDatabase($this->picA)['total_count'] === 3
        );
    }

    public function test_command_skips_completed_sessions(): void
    {
        $this->freezeAtMonthEndMinus(1);
        Notification::fake();

        ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
        ]);

        $this->artisan('smki:remind-unfilled-checklists')->assertExitCode(0);

        Notification::assertNotSentTo($this->picA, ChecklistUnfilledReminderNotification::class);
    }

    public function test_command_skips_non_threshold_dates(): void
    {
        Carbon::setTestNow(now()->startOfMonth()->addDays(5)->setTime(8, 0));
        Notification::fake();

        ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->artisan('smki:remind-unfilled-checklists')->assertExitCode(0);

        Notification::assertNotSentTo($this->picA, ChecklistUnfilledReminderNotification::class);
    }

    public function test_command_skips_units_without_pic(): void
    {
        $this->freezeAtMonthEndMinus(3);

        $orphanUnit = WorkUnit::factory()->create();
        $orphanSession = ChecklistSession::factory()->create([
            'unit_id' => $orphanUnit->id,
            'framework_id' => $this->session->framework_id,
            'periode' => now()->format('Y-m'),
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $orphanSession->id,
            'control_id' => $this->control->id,
            'unit_id' => $orphanUnit->id,
            'pic_id' => null,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->artisan('smki:remind-unfilled-checklists')->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_no_duplicate_same_day(): void
    {
        $this->freezeAtMonthEndMinus(1);
        Mail::fake();

        ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->artisan('smki:remind-unfilled-checklists')->assertExitCode(0);
        $this->assertEquals(1, $this->picA->unreadNotifications()->count());

        $this->artisan('smki:remind-unfilled-checklists')->assertExitCode(0);
        $this->assertEquals(1, $this->picA->fresh()->unreadNotifications()->count());
    }

    public function test_dalam_tinjauan_not_counted_unfilled(): void
    {
        $this->freezeAtMonthEndMinus(3);
        Notification::fake();

        ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => Control::factory()->create(['framework_id' => $this->session->framework_id])->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->artisan('smki:remind-unfilled-checklists')->assertExitCode(0);

        Notification::assertSentTo(
            $this->picA,
            ChecklistUnfilledReminderNotification::class,
            fn ($n) => $n->toDatabase($this->picA)['unfilled_count'] === 1
                && $n->toDatabase($this->picA)['total_count'] === 2
        );
    }

    public function test_pic_with_two_units_gets_two_summaries(): void
    {
        $this->freezeAtMonthEndMinus(1);
        Notification::fake();

        $unitB = WorkUnit::factory()->create();
        $this->picA->update(['unit_id' => $unitB->id]);
        $picB = $this->picA;
        $picA2 = User::factory()->create([
            'role_id' => Role::where('name', 'pic')->first()->id,
            'unit_id' => $this->unitA->id,
        ]);

        $sessionB = ChecklistSession::factory()->create([
            'unit_id' => $unitB->id,
            'framework_id' => $this->session->framework_id,
            'periode' => now()->format('Y-m'),
        ]);

        foreach ([$this->session, $sessionB] as $s) {
            ChecklistEntry::factory()->create([
                'session_id' => $s->id,
                'control_id' => $this->control->id,
                'unit_id' => $s->unit_id,
                'pic_id' => $s->unit_id === $this->unitA->id ? $picA2->id : $picB->id,
                'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
            ]);
        }

        $this->artisan('smki:remind-unfilled-checklists')->assertExitCode(0);

        Notification::assertSentToTimes($picA2, ChecklistUnfilledReminderNotification::class, 1);
        Notification::assertSentToTimes($picB, ChecklistUnfilledReminderNotification::class, 1);
    }
}
