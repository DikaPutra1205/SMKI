<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use App\Policies\ChecklistEntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ChecklistEntryPolicyTest extends TestCase
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

    public function test_view_any_admin_can_see_all(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $policy = new ChecklistEntryPolicy;
        $this->assertTrue($policy->viewAny($admin));
    }

    public function test_view_any_pic_scoped_to_own_unit(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $policy = new ChecklistEntryPolicy;

        $this->assertTrue($policy->viewAny($pic));
        $this->assertTrue($policy->viewAny($pic, $this->unitA->id));
        $this->assertFalse($policy->viewAny($pic, $this->unitB->id));
    }

    public function test_view_any_pic_can_see_child_unit(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $policy = new ChecklistEntryPolicy;
        $this->assertTrue($policy->viewAny($pic, $this->childOfA->id));
    }

    public function test_view_entry_own_pic_can_view(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $policy = new ChecklistEntryPolicy;

        $this->assertTrue($policy->view($pic, $entry));
    }

    public function test_view_entry_pic_can_view_child_unit_entry(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->childOfA->id, 'pic_id' => null,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $policy = new ChecklistEntryPolicy;

        $this->assertTrue($policy->view($pic, $entry));
    }

    public function test_create_pic_can_create_for_own_unit(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $policy = new ChecklistEntryPolicy;

        $this->assertTrue($policy->create($pic, $this->unitA->id));
        $this->assertFalse($policy->create($pic, $this->unitB->id));
    }

    public function test_create_pic_can_create_for_child_unit(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $policy = new ChecklistEntryPolicy;
        $this->assertTrue($policy->create($pic, $this->childOfA->id));
    }

    public function test_create_admin_can_create_any(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $policy = new ChecklistEntryPolicy;
        $this->assertTrue($admin->can('create', ChecklistEntry::class));
        $this->assertTrue($admin->can('create', ChecklistEntry::class, $this->unitB->id));
    }

    public function test_verify_pic_cannot_verify(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);
        $policy = new ChecklistEntryPolicy;

        $this->assertFalse($policy->verify($pic, $entry));
    }

    public function test_verify_admin_can_verify_own_unit(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN, 'unit_id' => $this->unitA->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => null,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);
        $policy = new ChecklistEntryPolicy;

        $this->assertTrue($policy->verify($admin, $entry));
    }

    public function test_upload_evidence_pic_can_upload_for_own_entry(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $policy = new ChecklistEntryPolicy;

        $this->assertTrue($policy->uploadEvidence($pic, $entry, $pic->id));
    }

    public function test_upload_evidence_pic_cannot_upload_for_other_user(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $otherPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $policy = new ChecklistEntryPolicy;

        $this->assertFalse($policy->uploadEvidence($pic, $entry, $otherPic->id));
    }

    public function test_upload_evidence_admin_can_upload_for_any(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => null,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $policy = new ChecklistEntryPolicy;

        $this->assertTrue($policy->uploadEvidence($admin, $entry));
    }

    public function test_non_pic_always_passes_view_update_delete(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => null,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->assertTrue($admin->can('view', $entry));
        $this->assertTrue($admin->can('update', $entry));
        $this->assertTrue($admin->can('delete', $entry));
    }

    public function test_auditor_can_view_and_update(): void
    {
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => null,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->assertTrue($auditor->can('view', $entry));
        $this->assertTrue($auditor->can('update', $entry));
        // Verify requires checklist.bulk-verify (read-only view for auditor/koordinator).
        $this->assertFalse($auditor->can('verify', $entry));
    }

    public function test_pic_other_unit_cannot_view_update_delete(): void
    {
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $picA->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $policy = new ChecklistEntryPolicy;

        $this->assertFalse($policy->view($picB, $entry));
        $this->assertFalse($policy->update($picB, $entry));
        $this->assertFalse($policy->delete($picB, $entry));
        $this->assertTrue($policy->delete($picA, $entry));
        $this->assertTrue($policy->restore($picA, $entry));
    }

    public function test_upload_evidence_cross_spoofed_null(): void
    {
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $picA->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->assertFalse(Gate::forUser($picB)->allows('uploadEvidence', [$entry, $picB->id]));
        $this->assertFalse(Gate::forUser($picA)->allows('uploadEvidence', [$entry, $picB->id]));
        $this->assertTrue(Gate::forUser($picA)->allows('uploadEvidence', [$entry, null]));
    }

    public function test_view_any_null_target_allowed_for_pic(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);

        $this->assertTrue(Gate::forUser($pic)->allows('viewAny', [ChecklistEntry::class, null]));
    }

    public function test_parent_pic_can_view_child_entry_and_sibling_denied(): void
    {
        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $childPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->childOfA->id]);
        $siblingUnit = WorkUnit::create(['nama' => 'Sibling', 'parent_id' => null]);
        $siblingPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $siblingUnit->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->childOfA->id, 'pic_id' => $childPic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->assertTrue($parentPic->can('view', $entry));
        $this->assertTrue($childPic->can('view', $entry));
        $this->assertFalse($siblingPic->can('view', $entry));
    }
}
