<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use App\Notifications\FindingCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pic;

    private WorkUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = WorkUnit::factory()->create();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN, 'unit_id' => null]);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);

        $framework = Framework::factory()->create();
        $control = Control::factory()->create(['framework_id' => $framework->id]);
        $finding = Finding::factory()->create([
            'control_id' => $control->id, 'unit_id' => $this->unit->id, 'pic_id' => $this->pic->id,
        ]);

        // Seed 3 notifications
        foreach (range(1, 3) as $i) {
            $this->pic->notify(new FindingCreatedNotification($finding, $this->admin, "Notif {$i}"));
        }
    }

    public function test_anonymous_blocked(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/fake-id/read')->assertUnauthorized();
        $this->deleteJson('/api/v1/notifications/fake-id')->assertUnauthorized();
    }

    public function test_index_returns_paginated_notifications(): void
    {
        $this->actingAs($this->pic)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('pagination.total', 3);
    }

    public function test_unread_count_matches(): void
    {
        $this->actingAs($this->pic)->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJson(['unread_count' => 3]);
    }

    public function test_mark_single_as_read(): void
    {
        $notifId = $this->pic->notifications()->first()->id;

        $this->actingAs($this->pic)->postJson("/api/v1/notifications/{$notifId}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 2);

        $refreshed = $this->pic->notifications()->where('id', $notifId)->first();
        $this->assertNotNull($refreshed->read_at);
    }

    public function test_mark_nonexistent_notification_returns_404(): void
    {
        $fakeId = (string) Str::uuid();
        $this->actingAs($this->pic)->postJson("/api/v1/notifications/{$fakeId}/read")
            ->assertNotFound();
    }

    public function test_mark_all_as_read(): void
    {
        $this->actingAs($this->pic)->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_delete_notification(): void
    {
        $notifId = $this->pic->notifications()->first()->id;

        $this->actingAs($this->pic)->deleteJson("/api/v1/notifications/{$notifId}")
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notifId]);
    }

    public function test_delete_nonexistent_notification_returns_404(): void
    {
        $fakeId = (string) Str::uuid();
        $this->actingAs($this->pic)->deleteJson("/api/v1/notifications/{$fakeId}")
            ->assertNotFound();
    }

    public function test_mark_read_own_only(): void
    {
        // picA creates notifications, picB should not see them
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);

        $notifId = $this->pic->notifications()->first()->id;

        $this->actingAs($picB)->postJson("/api/v1/notifications/{$notifId}/read")
            ->assertNotFound();
    }

    public function test_delete_own_only(): void
    {
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);

        $notifId = $this->pic->notifications()->first()->id;

        $this->actingAs($picB)->deleteJson("/api/v1/notifications/{$notifId}")
            ->assertNotFound();
    }
}
