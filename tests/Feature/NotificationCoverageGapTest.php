<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use App\Notifications\FindingCreatedNotification;
use Tests\TestCase;

class NotificationCoverageGapTest extends TestCase
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
        $this->control = Control::factory()->create(['framework_id' => $framework->id]);
    }

    public function test_anonymous_blocked_on_notification_routes(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/fake-id/read')->assertUnauthorized();
        $this->deleteJson('/api/v1/notifications/fake-id')->assertUnauthorized();
    }

    public function test_empty_notifications_returns_200_with_empty_data(): void
    {
        $this->actingAs($this->picA)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('pagination.total', 0);

        $data = $this->actingAs($this->picA)->getJson('/api/v1/notifications')->json('data');
        $this->assertCount(0, $data);

        $this->actingAs($this->picA)->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJson(['status' => 'success', 'unread_count' => 0]);
    }

    public function test_limit_parameter_clamped_between_1_and_50(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id, 'status' => Finding::STATUS_OPEN,
        ]);

        foreach (range(1, 5) as $i) {
            $this->picA->notify(new FindingCreatedNotification($finding, $this->admin, "Notif {$i}"));
        }

        // huge limit → capped 50
        $this->actingAs($this->picA)->getJson('/api/v1/notifications?limit=999')
            ->assertOk()->assertJsonPath('pagination.per_page', 50);

        // negative → floored 1
        $this->actingAs($this->picA)->getJson('/api/v1/notifications?limit=-5')
            ->assertOk()->assertJsonPath('pagination.per_page', 1);

        // non-numeric → floored 1
        $this->actingAs($this->picA)->getJson('/api/v1/notifications?limit=abc')
            ->assertOk()->assertJsonPath('pagination.per_page', 1);

        // normal limit respected
        $res = $this->actingAs($this->picA)->getJson('/api/v1/notifications?limit=2')->assertOk();
        $res->assertJsonPath('pagination.per_page', 2);
        $this->assertCount(2, $res->json('data'));
        $res->assertJsonPath('pagination.total', 5);
    }
}
