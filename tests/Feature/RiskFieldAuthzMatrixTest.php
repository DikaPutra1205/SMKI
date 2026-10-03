<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * US-R1 / US-R2 — §5 Risiko.
 *
 * US-R1: koordinator/admin mendaftarkan risiko (level_risiko, pemilik_risiko,
 *        rencana_mitigasi, catatan_admin opsional) tertaut ke >= 1 kontrol.
 * US-R2: PIC hanya boleh mengubah rencana_mitigasi + status pada risiko unit
 *        sendiri; level_risiko / pemilik_risiko / catatan_admin / unit_id
 *        (dan relasi kontrol) harus tidak tersentuh.
 */
class RiskFieldAuthzMatrixTest extends TestCase
{
    private const PUT_API = '/api/risks';

    private const PUT_V1 = '/api/v1/compliance-officer/risks';

    private User $admin;

    private User $koordinator;

    private User $picA;

    private User $picB;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private Control $controlA;

    private Control $controlB;

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
        $this->controlA = Control::factory()->create(['framework_id' => $framework->id, 'kode_klausul' => 'R.1.1']);
        $this->controlB = Control::factory()->create(['framework_id' => $framework->id, 'kode_klausul' => 'R.1.2']);
    }

    private function makeRisk(array $attrs = []): Risk
    {
        return Risk::factory()->withControl($this->controlA)->create(array_merge([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_HIGH,
            'pemilik_risiko' => 'Owner Asli',
            'rencana_mitigasi' => 'Rencana Asli',
            'status' => Risk::STATUS_OPEN,
            'catatan_admin' => 'Catatan Asli',
        ], $attrs));
    }

    // ------------------------------------------------------------------
    // US-R1: registrasi oleh koordinator/admin + tautan >= 1 kontrol
    // ------------------------------------------------------------------

    public function test_koordinator_can_register_risk_linked_to_multiple_controls(): void
    {
        $this->actingAs($this->koordinator)
            ->postJson('/api/v1/compliance-officer/risks', [
                'control_ids' => [$this->controlA->id, $this->controlB->id],
                'unit_id' => $this->unitA->id,
                'level_risiko' => Risk::LEVEL_CRITICAL,
                'pemilik_risiko' => 'Koordinator Keamanan',
                'rencana_mitigasi' => 'Terapkan MFA dan rotasi kredensial.',
                'catatan_admin' => 'Prioritas tinggi.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.level_risiko', Risk::LEVEL_CRITICAL)
            ->assertJsonPath('data.catatan_admin', 'Prioritas tinggi.');

        $risk = Risk::latest('id')->first();

        $this->assertDatabaseHas('control_risk', ['risk_id' => $risk->id, 'control_id' => $this->controlA->id]);
        $this->assertDatabaseHas('control_risk', ['risk_id' => $risk->id, 'control_id' => $this->controlB->id]);
    }

    public function test_catatan_admin_is_optional_on_registration(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/risks', [
                'control_ids' => [$this->controlA->id],
                'unit_id' => $this->unitA->id,
                'level_risiko' => Risk::LEVEL_MEDIUM,
                'pemilik_risiko' => 'Tanpa Catatan',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('risks', ['pemilik_risiko' => 'Tanpa Catatan', 'catatan_admin' => null]);
    }

    public function test_every_documented_level_and_status_is_accepted_on_registration(): void
    {
        foreach ([Risk::LEVEL_LOW, Risk::LEVEL_MEDIUM, Risk::LEVEL_HIGH, Risk::LEVEL_CRITICAL] as $level) {
            foreach ([Risk::STATUS_OPEN, Risk::STATUS_MITIGATED, Risk::STATUS_ACCEPTED] as $status) {
                $this->actingAs($this->admin)
                    ->postJson('/api/risks', [
                        'control_ids' => [$this->controlA->id],
                        'unit_id' => $this->unitA->id,
                        'level_risiko' => $level,
                        'pemilik_risiko' => "Owner {$level}/{$status}",
                        'status' => $status,
                    ])
                    ->assertCreated()
                    ->assertJsonPath('data.level_risiko', $level)
                    ->assertJsonPath('data.status', $status);
            }
        }

        $this->assertSame(12, Risk::count());
    }

    public function test_registration_rejects_invalid_status_and_level(): void
    {
        $payload = [
            'control_ids' => [$this->controlA->id],
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_LOW,
            'pemilik_risiko' => 'Owner',
        ];

        $this->actingAs($this->koordinator)
            ->postJson('/api/v1/compliance-officer/risks', array_merge($payload, ['status' => 'closed']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($this->koordinator)
            ->postJson('/api/v1/compliance-officer/risks', array_merge($payload, ['level_risiko' => 'lowest']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['level_risiko']);

        $this->assertSame(0, Risk::count());
    }

    public function test_registration_requires_at_least_one_control_on_both_write_surfaces(): void
    {
        foreach (['/api/risks', '/api/v1/compliance-officer/risks'] as $endpoint) {
            // kosong
            $this->actingAs($this->admin)->postJson($endpoint, [
                'control_ids' => [],
                'unit_id' => $this->unitA->id,
                'level_risiko' => Risk::LEVEL_LOW,
                'pemilik_risiko' => 'Owner',
            ])->assertUnprocessable()->assertJsonValidationErrors(['control_ids']);

            // tidak ada kontrol sama sekali
            $this->actingAs($this->admin)->postJson($endpoint, [
                'unit_id' => $this->unitA->id,
                'level_risiko' => Risk::LEVEL_LOW,
                'pemilik_risiko' => 'Owner',
            ])->assertUnprocessable()->assertJsonValidationErrors(['control_ids']);
        }

        $this->assertSame(0, Risk::count());
    }

    public function test_update_cannot_detach_all_controls(): void
    {
        $risk = $this->makeRisk();

        foreach ([self::PUT_API, self::PUT_V1] as $endpoint) {
            $this->actingAs($this->admin)
                ->putJson("{$endpoint}/{$risk->id}", ['control_ids' => []])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['control_ids']);
        }

        // relasi tetap utuh
        $this->assertSame(1, DB::table('control_risk')->where('risk_id', $risk->id)->count());
    }

    // ------------------------------------------------------------------
    // US-R2: matriks otorisasi tingkat-field
    // ------------------------------------------------------------------

    /**
     * US-R2 — PIC may only change rencana_mitigasi + status on own-unit risks.
     */
    public function test_pic_cannot_edit_locked_fields_on_own_unit_risk(): void
    {
        $lockedValues = [
            'level_risiko' => Risk::LEVEL_LOW,
            'pemilik_risiko' => 'Pemilik Baru',
            'catatan_admin' => 'Catatan Baru',
            'unit_id' => $this->unitB->id,
        ];

        foreach ($lockedValues as $field => $tampered) {
            foreach ([self::PUT_API, self::PUT_V1] as $endpoint) {
                $risk = $this->makeRisk();

                $this->actingAs($this->picA)
                    ->putJson("{$endpoint}/{$risk->id}", [
                        'status' => Risk::STATUS_MITIGATED,
                        'rencana_mitigasi' => 'Mitigasi oleh PIC',
                        $field => $tampered,
                    ])
                    ->assertOk();

                $fresh = $risk->fresh();
                $this->assertSame(Risk::STATUS_MITIGATED, $fresh->status, "status via {$endpoint}");
                $this->assertSame('Mitigasi oleh PIC', $fresh->rencana_mitigasi, "rencana_mitigasi via {$endpoint}");
                $this->assertSame(Risk::LEVEL_HIGH, $fresh->level_risiko, "level_risiko via {$endpoint}");
                $this->assertSame('Owner Asli', $fresh->pemilik_risiko, "pemilik_risiko via {$endpoint}");
                $this->assertSame('Catatan Asli', $fresh->catatan_admin, "catatan_admin via {$endpoint}");
                $this->assertSame($this->unitA->id, $fresh->unit_id, "unit_id via {$endpoint}");
            }
        }
    }

    public function test_pic_cannot_relink_risk_to_other_controls(): void
    {
        foreach ([self::PUT_API, self::PUT_V1] as $endpoint) {
            $risk = $this->makeRisk();

            $this->actingAs($this->picA)
                ->putJson("{$endpoint}/{$risk->id}", [
                    'status' => Risk::STATUS_MITIGATED,
                    'control_ids' => [$this->controlB->id],
                ])
                ->assertOk();

            $linked = DB::table('control_risk')->where('risk_id', $risk->id)->pluck('control_id')->all();

            $this->assertSame(
                [$this->controlA->id],
                $linked,
                "PIC berhasil menukar kontrol pada {$endpoint} —|US-R2 hanya rencana_mitigasi+status."
            );
        }
    }

    public function test_pic_cannot_attach_extra_control_without_detaching_original(): void
    {
        foreach ([self::PUT_API, self::PUT_V1] as $endpoint) {
            $risk = $this->makeRisk();

            $this->actingAs($this->picA)
                ->putJson("{$endpoint}/{$risk->id}", [
                    'control_ids' => [$this->controlA->id, $this->controlB->id],
                ])
                ->assertOk();

            $this->assertSame(
                1,
                DB::table('control_risk')->where('risk_id', $risk->id)->count(),
                "PIC menambah kontrol pada {$endpoint}"
            );
        }
    }

    public function test_pic_cannot_relink_risk_via_web_form_surface(): void
    {
        $risk = $this->makeRisk();

        $this->actingAs($this->picA)
            ->from('/risks')
            ->put("/risks/{$risk->id}", [
                'status' => Risk::STATUS_MITIGATED,
                'control_ids' => [$this->controlB->id],
                'level_risiko' => Risk::LEVEL_LOW,
                'catatan_admin' => 'Catatan Baru',
            ])
            ->assertRedirect();

        $linked = DB::table('control_risk')->where('risk_id', $risk->id)->pluck('control_id')->all();
        $this->assertSame([$this->controlA->id], $linked, 'PIC menukar kontrol lewat form web');

        $fresh = $risk->fresh();
        $this->assertSame(Risk::LEVEL_HIGH, $fresh->level_risiko);
        $this->assertSame('Catatan Asli', $fresh->catatan_admin);
    }

    public function test_admin_and_koordinator_may_edit_every_risk_field(): void
    {
        foreach ([$this->admin, $this->koordinator] as $actor) {
            $role = $actor->role;

            foreach ([self::PUT_API, self::PUT_V1] as $endpoint) {
                $risk = $this->makeRisk();

                $this->actingAs($actor)
                    ->putJson("{$endpoint}/{$risk->id}", [
                        'level_risiko' => Risk::LEVEL_LOW,
                        'pemilik_risiko' => 'Owner Baru',
                        'rencana_mitigasi' => 'Rencana Baru',
                        'status' => Risk::STATUS_ACCEPTED,
                        'catatan_admin' => 'Catatan Baru',
                        'control_ids' => [$this->controlB->id],
                    ])
                    ->assertOk();

                $fresh = $risk->fresh();
                $this->assertSame(Risk::LEVEL_LOW, $fresh->level_risiko, "{$role} level via {$endpoint}");
                $this->assertSame('Owner Baru', $fresh->pemilik_risiko, "{$role} owner via {$endpoint}");
                $this->assertSame('Rencana Baru', $fresh->rencana_mitigasi, "{$role} rencana via {$endpoint}");
                $this->assertSame(Risk::STATUS_ACCEPTED, $fresh->status, "{$role} status via {$endpoint}");
                $this->assertSame('Catatan Baru', $fresh->catatan_admin, "{$role} catatan via {$endpoint}");
                $this->assertSame(
                    [$this->controlB->id],
                    DB::table('control_risk')->where('risk_id', $risk->id)->pluck('control_id')->all(),
                    "{$role} kontrol via {$endpoint}"
                );
            }
        }
    }

    public function test_pic_cannot_edit_locked_fields_on_unassigned_risk(): void
    {
        $risk = $this->makeRisk(['unit_id' => null]);

        $this->actingAs($this->picA)
            ->putJson(self::PUT_V1.'/'.$risk->id, [
                'level_risiko' => Risk::LEVEL_LOW,
                'pemilik_risiko' => 'Pemilik Baru',
                'catatan_admin' => 'Catatan Baru',
                'unit_id' => $this->unitA->id,
            ])
            ->assertOk();

        $fresh = $risk->fresh();
        $this->assertSame(Risk::LEVEL_HIGH, $fresh->level_risiko);
        $this->assertSame('Owner Asli', $fresh->pemilik_risiko);
        $this->assertSame('Catatan Asli', $fresh->catatan_admin);
        $this->assertNull($fresh->unit_id);
    }

    public function test_pic_cannot_touch_other_unit_risk_through_any_surface(): void
    {
        $risk = $this->makeRisk(['unit_id' => $this->unitB->id]);

        foreach ([self::PUT_API, self::PUT_V1] as $endpoint) {
            $this->actingAs($this->picA)
                ->putJson("{$endpoint}/{$risk->id}", ['status' => Risk::STATUS_MITIGATED])
                ->assertForbidden();
        }

        $this->actingAs($this->picA)
            ->from('/risks')
            ->put("/risks/{$risk->id}", ['status' => Risk::STATUS_MITIGATED])
            ->assertForbidden();

        $this->assertSame(Risk::STATUS_OPEN, $risk->fresh()->status);
    }
}
