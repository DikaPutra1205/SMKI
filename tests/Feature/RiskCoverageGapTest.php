<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RiskCoverageGapTest extends TestCase
{
    private User $admin;

    private User $koordinator;

    private User $picA;

    private User $picB;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create();
        $this->unitB = WorkUnit::factory()->create();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN, 'unit_id' => $this->unitA->id]);
        $this->koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI, 'unit_id' => $this->unitA->id]);
        $this->picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $this->picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);

        $framework = Framework::factory()->create();
        $this->control = Control::factory()->create(['framework_id' => $framework->id]);
    }

    public function test_anonymous_blocked_on_all_risk_routes(): void
    {
        $risk = Risk::factory()->create(['control_id' => $this->control->id, 'unit_id' => $this->unitA->id]);

        $this->getJson('/api/risks')->assertUnauthorized();
        $this->getJson("/api/risks/{$risk->id}")->assertUnauthorized();
        $this->postJson('/api/risks', [])->assertUnauthorized();
        $this->getJson('/api/v1/compliance-officer/risks')->assertUnauthorized();
        $this->getJson('/api/v1/compliance-officer/risks/matrix')->assertUnauthorized();
        $this->getJson("/api/v1/compliance-officer/risks/{$risk->id}")->assertUnauthorized();

        $this->get('/admin/kepatuhan/risks')->assertRedirect(route('login'));
        $this->get('/risks')->assertRedirect(route('login'));
    }

    public function test_store_risk_validation_rejects_bad_payload(): void
    {
        // missing control_id → 422
        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/risks', [
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_HIGH,
        ])->assertUnprocessable()->assertJsonValidationErrors(['control_id']);

        // invalid level_risiko → 422
        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/risks', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'level_risiko' => 'extreme',
        ])->assertUnprocessable()->assertJsonValidationErrors(['level_risiko']);

        // non-existent control_id → 422
        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/risks', [
            'control_id' => 999999,
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_LOW,
        ])->assertUnprocessable()->assertJsonValidationErrors(['control_id']);
    }

    public function test_update_risk_rejects_invalid_status(): void
    {
        $risk = Risk::factory()->create(['control_id' => $this->control->id, 'unit_id' => $this->unitA->id]);

        $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/risks/{$risk->id}", [
            'status' => 'bogus',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->actingAs($this->admin)->putJson("/api/risks/{$risk->id}", [
            'status' => 'bogus',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_generic_risk_api_happy_paths(): void
    {
        $risk = Risk::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_MEDIUM,
            'status' => Risk::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)->getJson('/api/risks')
            ->assertOk()->assertJsonStructure(['status', 'data' => ['data']]);

        $this->actingAs($this->admin)->getJson("/api/risks/{$risk->id}")
            ->assertOk()->assertJsonPath('data.id', $risk->id);

        $this->actingAs($this->admin)->postJson('/api/risks', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_LOW,
            'pemilik_risiko' => 'Owner API',
        ])->assertCreated()->assertJsonPath('status', 'success');
    }

    public function test_risk_matrix_shape(): void
    {
        Risk::factory()->create(['control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'level_risiko' => Risk::LEVEL_HIGH, 'status' => Risk::STATUS_OPEN]);

        $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/risks/matrix')
            ->assertOk()
            ->assertJsonStructure(['status', 'data' => ['total_risks', 'by_level', 'by_status']]);
    }

    public function test_pic_create_forces_own_unit(): void
    {
        // PIC sends own unit → 201, stored as own
        $res = $this->actingAs($this->picA)->postJson('/api/risks', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_HIGH,
            'pemilik_risiko' => 'PIC A',
        ]);

        $res->assertCreated();
        $this->assertEquals($this->unitA->id, $res->json('data.unit_id'));
        $this->assertDatabaseHas('risks', ['id' => $res->json('data.id'), 'unit_id' => $this->unitA->id]);

        // PIC attempts cross-unit → 403 via StoreRiskRequest policy check
        $this->actingAs($this->picA)->postJson('/api/risks', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitB->id,
            'level_risiko' => Risk::LEVEL_HIGH,
            'pemilik_risiko' => 'PIC A cross',
        ])->assertForbidden();
    }

    public function test_koordinator_cannot_delete_risk(): void
    {
        $risk = Risk::factory()->create(['control_id' => $this->control->id, 'unit_id' => $this->unitA->id]);

        $this->actingAs($this->koordinator)->deleteJson("/api/risks/{$risk->id}")->assertForbidden();
        $this->assertDatabaseHas('risks', ['id' => $risk->id, 'deleted_at' => null]);
    }

    public function test_soft_deleted_risk_hidden(): void
    {
        $risk = Risk::factory()->create(['control_id' => $this->control->id, 'unit_id' => $this->unitA->id]);

        $this->actingAs($this->admin)->deleteJson("/api/risks/{$risk->id}")->assertOk();
        $this->assertSoftDeleted('risks', ['id' => $risk->id]);

        $this->actingAs($this->admin)->getJson("/api/risks/{$risk->id}")->assertNotFound();

        $ids = collect($this->actingAs($this->admin)->getJson('/api/risks')->json('data.data'))->pluck('id');
        $this->assertNotContains($risk->id, $ids);
    }

    public function test_overdue_false_when_mitigated_or_accepted(): void
    {
        $mitigated = Risk::factory()->create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id,
            'status' => Risk::STATUS_MITIGATED, 'deadline' => now()->subDays(5)->toDateString(),
        ]);
        $accepted = Risk::factory()->create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id,
            'status' => Risk::STATUS_ACCEPTED, 'deadline' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertFalse($mitigated->is_overdue);
        $this->assertFalse($accepted->is_overdue);
    }

    public function test_days_remaining_null_without_deadline(): void
    {
        $risk = Risk::factory()->create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id,
            'status' => Risk::STATUS_OPEN, 'deadline' => null,
        ]);

        $this->assertNull($risk->days_remaining);
        $this->assertFalse($risk->is_overdue);
    }

    public function test_web_risk_routes_render_and_mutate_with_flash(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin)->get('/admin/kepatuhan/risks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin-kepatuhan/risks', false)->has('risks.data')->has('matrix.by_level'));

        $this->actingAs($this->admin)->get('/risks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin-kepatuhan/risks', false)->has('risks.data'));

        $this->actingAs($this->admin)->from('/admin/kepatuhan/risks')->post('/admin/kepatuhan/risks', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_LOW,
            'pemilik_risiko' => 'Web Owner',
        ])->assertRedirect()->assertSessionHas('flash.type', 'success');

        $risk = Risk::latest('id')->first();

        $this->actingAs($this->admin)->from('/admin/kepatuhan/risks')->put("/admin/kepatuhan/risks/{$risk->id}", [
            'status' => Risk::STATUS_MITIGATED,
        ])->assertRedirect()->assertSessionHas('flash.type', 'success');
    }
}
