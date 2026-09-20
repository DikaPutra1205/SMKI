<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use App\Policies\FindingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private WorkUnit $childOfA;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unitA = WorkUnit::create(['nama' => 'Unit A']);
        $this->unitB = WorkUnit::create(['nama' => 'Unit B']);
        $this->childOfA = WorkUnit::create(['nama' => 'Child A', 'parent_id' => $this->unitA->id]);
        $fw = Framework::create(['nama' => 'Test FW', 'singkatan' => 'TFW', 'versi' => '1.0']);
        $this->control = Control::create([
            'framework_id' => $fw->id,
            'kode_klausul' => 'C.1',
            'judul' => 'Control 1',
            'kategori' => 'organizational',
        ]);
    }

    private function makeFinding(array $overrides = []): Finding
    {
        return Finding::create(array_merge([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id])->id,
            'status' => Finding::STATUS_OPEN,
        ], $overrides));
    }

    public function test_view_any_admin_can_see_all(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $policy = new FindingPolicy;
        $this->assertTrue($policy->viewAny($admin));
    }

    public function test_view_any_pic_scoped_to_own_unit(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $policy = new FindingPolicy;

        $this->assertTrue($policy->viewAny($pic));
        $this->assertTrue($policy->viewAny($pic, $this->unitA->id));
        $this->assertFalse($policy->viewAny($pic, $this->unitB->id));
    }

    public function test_view_any_pic_can_see_child_unit_findings(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $policy = new FindingPolicy;
        $this->assertTrue($policy->viewAny($pic, $this->childOfA->id));
    }

    public function test_view_pic_can_view_own_unit_finding(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $finding = $this->makeFinding(['unit_id' => $this->unitA->id, 'pic_id' => $pic->id]);
        $policy = new FindingPolicy;

        $this->assertTrue($policy->view($pic, $finding));
    }

    public function test_view_pic_cannot_view_other_unit_finding(): void
    {
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);
        $finding = $this->makeFinding(['unit_id' => $this->unitB->id, 'pic_id' => $picB->id]);
        $policy = new FindingPolicy;

        $this->assertFalse($policy->view($picA, $finding));
    }

    public function test_create_only_admin_can_create(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $policy = new FindingPolicy;

        $this->assertFalse($policy->create($pic));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->create($sa));
        $this->assertFalse($policy->create($auditor));
    }

    public function test_update_admin_can_update_any(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $finding = $this->makeFinding();
        $policy = new FindingPolicy;

        $this->assertTrue($policy->update($admin, $finding));
    }

    public function test_update_pic_can_update_own_unit_finding(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $finding = $this->makeFinding(['unit_id' => $this->unitA->id, 'pic_id' => $pic->id]);
        $policy = new FindingPolicy;

        $this->assertTrue($policy->update($pic, $finding));
    }

    public function test_update_pic_cannot_update_other_unit_finding(): void
    {
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);
        $finding = $this->makeFinding(['unit_id' => $this->unitB->id, 'pic_id' => $picB->id]);
        $policy = new FindingPolicy;

        $this->assertFalse($policy->update($picA, $finding));
    }

    public function test_update_status_admin_can_update(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $finding = $this->makeFinding();
        $policy = new FindingPolicy;

        $this->assertTrue($policy->updateStatus($admin, $finding));
    }

    public function test_delete_only_admin_can_delete(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $finding = $this->makeFinding(['pic_id' => $pic->id]);
        $policy = new FindingPolicy;

        $this->assertFalse($policy->delete($pic, $finding));
        $this->assertTrue($policy->delete($admin, $finding));
        $this->assertTrue($policy->delete($sa, $finding));
    }

    public function test_restore_delegates_to_delete(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $finding = $this->makeFinding(['pic_id' => $pic->id]);
        $policy = new FindingPolicy;

        $this->assertTrue($policy->restore($admin, $finding));
        $this->assertFalse($policy->restore($pic, $finding));
    }

    public function test_auditor_koordinator_superadmin_matrix(): void
    {
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $finding = $this->makeFinding();

        $this->assertTrue($auditor->can('view', $finding));
        $this->assertFalse($auditor->can('create', Finding::class));
        $this->assertFalse($auditor->can('delete', $finding));
        $this->assertFalse($koordinator->can('create', Finding::class));
        $this->assertTrue($sa->can('create', Finding::class));
        $this->assertTrue($sa->can('delete', $finding));
    }

    public function test_update_status_pic_scoped(): void
    {
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);
        $finding = $this->makeFinding(['unit_id' => $this->unitA->id, 'pic_id' => $picA->id]);
        $policy = new FindingPolicy;

        $this->assertTrue($policy->updateStatus($picA, $finding));
        $this->assertFalse($policy->updateStatus($picB, $finding));
    }

    public function test_parent_pic_can_view_child_finding_and_sibling_denied(): void
    {
        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $childPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->childOfA->id]);
        $siblingPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);
        $finding = $this->makeFinding(['unit_id' => $this->childOfA->id, 'pic_id' => $childPic->id]);
        $policy = new FindingPolicy;

        $this->assertTrue($policy->view($parentPic, $finding));
        $this->assertTrue($policy->view($childPic, $finding));
        $this->assertTrue($policy->viewAny($parentPic, $this->childOfA->id));
        $this->assertFalse($policy->view($siblingPic, $finding));
    }
}
