<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use App\Services\DashboardAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskVeryLowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private WorkUnit $unitA;

    private Control $control;

    private DashboardAnalyticsService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Pusat Ekosistem SDM']);

        $this->admin = User::factory()->create([
            'role' => 'admin_kepatuhan',
            'unit_id' => $this->unitA->id,
        ]);

        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->control = Control::factory()->create(['framework_id' => $framework->id, 'kode_klausul' => 'A.5.1']);

        $this->dashboardService = app(DashboardAnalyticsService::class);
    }

    public function test_store_accepts_very_low_through_level_risiko_alias(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/risks', [
            'control_ids' => [$this->control->id],
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_VERY_LOW,
            'pemilik_risiko' => 'Tim IT',
        ]);

        $response->assertCreated()->assertJsonPath('data.level_risiko', Risk::LEVEL_VERY_LOW);

        $this->assertDatabaseHas('risks', [
            'level_risiko' => Risk::LEVEL_VERY_LOW,
            'pemilik_risiko' => 'Tim IT',
        ]);
    }

    public function test_store_accepts_very_low_through_risk_level_alias(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/risks', [
            'control_ids' => [$this->control->id],
            'unit_id' => $this->unitA->id,
            'risk_level' => Risk::LEVEL_VERY_LOW,
            'risk_owner' => 'Tim Audit',
        ]);

        $response->assertCreated()->assertJsonPath('data.level_risiko', Risk::LEVEL_VERY_LOW);

        $this->assertDatabaseHas('risks', [
            'level_risiko' => Risk::LEVEL_VERY_LOW,
            'pemilik_risiko' => 'Tim Audit',
        ]);
    }

    public function test_store_rejects_unknown_level(): void
    {
        $this->actingAs($this->admin)->postJson('/api/risks', [
            'control_ids' => [$this->control->id],
            'unit_id' => $this->unitA->id,
            'level_risiko' => 'sangat-kritis',
            'pemilik_risiko' => 'Tim IT',
        ])->assertStatus(422)->assertJsonValidationErrors('level_risiko');

        $this->actingAs($this->admin)->postJson('/api/risks', [
            'control_ids' => [$this->control->id],
            'unit_id' => $this->unitA->id,
            'risk_level' => 'sangat-kritis',
        ])->assertStatus(422)->assertJsonValidationErrors('risk_level');

        $this->assertDatabaseCount('risks', 0);
    }

    public function test_update_accepts_very_low_and_persists_it(): void
    {
        $risk = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_LOW,
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/compliance-officer/risks/{$risk->id}", [
                'risk_level' => Risk::LEVEL_VERY_LOW,
            ])
            ->assertOk()
            ->assertJsonPath('data.risk_level', Risk::LEVEL_VERY_LOW);

        $this->assertDatabaseHas('risks', [
            'id' => $risk->id,
            'level_risiko' => Risk::LEVEL_VERY_LOW,
        ]);
    }

    public function test_update_rejects_unknown_level(): void
    {
        $risk = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_LOW,
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/compliance-officer/risks/{$risk->id}", [
                'risk_level' => 'sangat-kritis',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('risk_level');
    }

    public function test_risk_matrix_counts_very_low_separately_from_low(): void
    {
        Risk::factory()->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_VERY_LOW,
            'status' => Risk::STATUS_OPEN,
        ]);
        Risk::factory()->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_LOW,
            'status' => Risk::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/risks/matrix')
            ->assertOk()
            ->assertJsonPath('data.by_level.very_low', 1)
            ->assertJsonPath('data.by_level.low', 1)
            ->assertJsonPath('data.total_risks', 2);
    }

    public function test_risks_listing_can_be_scoped_to_very_low(): void
    {
        $veryLow = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_VERY_LOW,
            'status' => Risk::STATUS_OPEN,
        ]);
        Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_LOW,
            'status' => Risk::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/risks?risk_level=very_low');

        $response->assertOk();
        $risks = $response->json('data.data');

        $this->assertCount(1, $risks);
        $this->assertEquals($veryLow->id, $risks[0]['id']);
        $this->assertEquals(Risk::LEVEL_VERY_LOW, $risks[0]['risk_level']);
    }

    public function test_dashboard_summary_reports_very_low_bucket(): void
    {
        Risk::factory()->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_VERY_LOW,
            'status' => Risk::STATUS_OPEN,
        ]);
        Risk::factory()->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_VERY_LOW,
            'status' => Risk::STATUS_MITIGATED,
        ]);
        // accepted stays out of total_active and of every level bucket.
        Risk::factory()->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_VERY_LOW,
            'status' => Risk::STATUS_ACCEPTED,
        ]);
        Risk::factory()->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_LOW,
            'status' => Risk::STATUS_OPEN,
        ]);

        $risks = $this->dashboardService->getSummary($this->admin)['risks_summary'];

        $this->assertSame(2, $risks['very_low']);
        $this->assertSame(1, $risks['low']);
        $this->assertSame(3, $risks['total_active']);
        $this->assertSame(
            $risks['total_active'],
            $risks['very_low'] + $risks['low'] + $risks['medium'] + $risks['high'] + $risks['critical']
        );
    }
}
