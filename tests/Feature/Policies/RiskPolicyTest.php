<?php

namespace Tests\Feature\Policies;

use App\Models\Control;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RiskPolicyTest extends TestCase
{
    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private Risk $riskA;

    private User $picA;

    private User $picB;

    private User $admin;

    private User $auditor;

    private User $koordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::create(['nama' => 'Unit A']);
        $this->unitB = WorkUnit::create(['nama' => 'Unit B']);

        $this->picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $this->picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $this->auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $this->koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);

        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Test', 'kategori' => 'teknologi',
        ]);

        $this->riskA = Risk::factory()->withControl($control)->create([
            'unit_id' => $this->unitA->id, 'level_risiko' => Risk::LEVEL_LOW,
            'status' => Risk::STATUS_OPEN,
        ]);
    }

    public function test_view_any_admin(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('viewAny', Risk::class));
    }

    public function test_view_any_auditor(): void
    {
        $this->assertTrue(Gate::forUser($this->auditor)->allows('viewAny', Risk::class));
    }

    public function test_view_any_koordinator(): void
    {
        $this->assertTrue(Gate::forUser($this->koordinator)->allows('viewAny', Risk::class));
    }

    public function test_view_any_pic_own_unit(): void
    {
        $this->assertTrue(Gate::forUser($this->picA)->allows('viewAny', [Risk::class, $this->unitA->id]));
    }

    public function test_view_any_pic_other_unit_denied(): void
    {
        $this->assertFalse(Gate::forUser($this->picA)->allows('viewAny', [Risk::class, $this->unitB->id]));
    }

    public function test_view_admin_any_risk(): void
    {
        $this->assertTrue($this->admin->can('view', $this->riskA));
    }

    public function test_view_auditor_any_risk(): void
    {
        $this->assertTrue($this->auditor->can('view', $this->riskA));
    }

    public function test_view_pic_own_unit(): void
    {
        $this->assertTrue($this->picA->can('view', $this->riskA));
    }

    public function test_view_pic_other_unit_denied(): void
    {
        $this->assertFalse($this->picB->can('view', $this->riskA));
    }

    public function test_create_pic_denied(): void
    {
        $this->assertFalse(Gate::forUser($this->picA)->allows('create', Risk::class));
    }

    public function test_create_admin_allowed(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', Risk::class));
    }

    public function test_create_koordinator_allowed(): void
    {
        $this->assertTrue(Gate::forUser($this->koordinator)->allows('create', Risk::class));
    }

    public function test_create_auditor_denied(): void
    {
        $this->assertFalse(Gate::forUser($this->auditor)->allows('create', Risk::class));
    }

    public function test_update_admin_any_risk(): void
    {
        $this->assertTrue($this->admin->can('update', $this->riskA));
    }

    public function test_update_koordinator_any_risk(): void
    {
        $this->assertTrue($this->koordinator->can('update', $this->riskA));
    }

    public function test_update_pic_own_unit(): void
    {
        $this->assertTrue($this->picA->can('update', $this->riskA));
    }

    public function test_update_pic_other_unit_denied(): void
    {
        $this->assertFalse($this->picB->can('update', $this->riskA));
    }

    public function test_update_auditor_denied(): void
    {
        $this->assertFalse($this->auditor->can('update', $this->riskA));
    }

    public function test_delete_pic_denied(): void
    {
        $this->assertFalse(Gate::forUser($this->picA)->allows('delete', $this->riskA));
    }

    public function test_delete_admin_allowed(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('delete', $this->riskA));
    }

    public function test_delete_koordinator_allowed(): void
    {
        $this->assertTrue(Gate::forUser($this->koordinator)->allows('delete', $this->riskA));
    }

    public function test_delete_auditor_denied(): void
    {
        $this->assertFalse(Gate::forUser($this->auditor)->allows('delete', $this->riskA));
    }

    public function test_delete_cross_unit_risk_allowed_for_koordinator_non_pic(): void
    {
        $riskB = Risk::factory()->withControl(Control::first())->create([
            'unit_id' => $this->unitB->id, 'level_risiko' => Risk::LEVEL_LOW,
            'status' => Risk::STATUS_OPEN,
        ]);
        $this->assertTrue(Gate::forUser($this->koordinator)->allows('delete', $riskB));
    }
}
