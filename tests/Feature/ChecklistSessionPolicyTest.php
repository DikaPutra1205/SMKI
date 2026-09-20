<?php

namespace Tests\Feature;

use App\Models\ChecklistSession;
use App\Models\User;
use App\Models\WorkUnit;
use App\Policies\ChecklistSessionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistSessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unitA = WorkUnit::create(['nama' => 'Unit A']);
        $this->unitB = WorkUnit::create(['nama' => 'Unit B']);
    }

    public function test_view_any_requires_permission(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $koor = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);
        $policy = new ChecklistSessionPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($koor));
    }

    public function test_view_any_pic_has_read_permission(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $policy = new ChecklistSessionPolicy;

        $this->assertTrue($pic->hasPermissionTo('checklist-session.read'));
        $this->assertTrue($policy->viewAny($pic));
    }

    public function test_view_superadmin_can_view_any_session(): void
    {
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitA->id,
        ]);
        $policy = new ChecklistSessionPolicy;

        $this->assertTrue($policy->view($sa, $session));
    }

    public function test_view_admin_can_view_any_session(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitA->id,
        ]);
        $policy = new ChecklistSessionPolicy;

        $this->assertTrue($policy->view($admin, $session));
    }

    public function test_view_pic_can_view_own_unit_session(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitA->id,
        ]);
        $policy = new ChecklistSessionPolicy;

        $this->assertTrue($policy->view($pic, $session));
    }

    public function test_view_pic_cannot_view_other_unit_session(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitB->id,
        ]);
        $policy = new ChecklistSessionPolicy;

        $this->assertFalse($policy->view($pic, $session));
    }

    public function test_update_requires_permission_and_unit_match(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN, 'unit_id' => $this->unitA->id]);
        $sessionOwn = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitA->id,
        ]);
        $sessionOther = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitB->id,
        ]);
        $policy = new ChecklistSessionPolicy;

        $this->assertTrue($policy->update($admin, $sessionOwn));
        $this->assertTrue($policy->update($admin, $sessionOther));
    }

    public function test_pic_can_update_own_unit_session(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $sessionOwn = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitA->id,
        ]);
        $sessionOther = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitB->id,
        ]);
        $policy = new ChecklistSessionPolicy;

        $this->assertTrue($policy->update($pic, $sessionOwn));
        $this->assertFalse($policy->update($pic, $sessionOther));
    }

    public function test_delete_requires_permission_and_unit_match(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN, 'unit_id' => $this->unitA->id]);
        $sessionOwn = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitA->id,
        ]);
        $sessionOther = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitB->id,
        ]);
        $policy = new ChecklistSessionPolicy;

        $this->assertFalse($policy->delete($pic, $sessionOwn));
        $this->assertTrue($policy->delete($admin, $sessionOwn));
        $this->assertTrue($policy->delete($admin, $sessionOther));
    }

    public function test_restore_requires_permission_and_unit_match(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN, 'unit_id' => $this->unitA->id]);
        $sessionOwn = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitA->id,
        ]);
        $policy = new ChecklistSessionPolicy;

        $this->assertFalse($policy->restore($pic, $sessionOwn));
        $this->assertTrue($policy->restore($admin, $sessionOwn));
    }

    public function test_koordinator_cannot_manage_sessions(): void
    {
        $koor = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitA->id,
        ]);
        $policy = new ChecklistSessionPolicy;

        $this->assertFalse($policy->update($koor, $session));
        $this->assertFalse($policy->delete($koor, $session));
        $this->assertFalse($policy->restore($koor, $session));
    }

    public function test_view_any_all_roles_with_read_perm(): void
    {
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $koor = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);

        $this->assertTrue($auditor->can('viewAny', ChecklistSession::class));
        $this->assertTrue($koor->can('viewAny', ChecklistSession::class));
        $this->assertTrue($pic->can('viewAny', ChecklistSession::class));
    }

    public function test_create_matrix(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $koor = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);

        $this->assertTrue($admin->can('create', ChecklistSession::class));
        $this->assertFalse($pic->can('create', ChecklistSession::class));
        $this->assertFalse($auditor->can('create', ChecklistSession::class));
        $this->assertFalse($koor->can('create', ChecklistSession::class));
    }

    public function test_update_delete_restore_role_matrix(): void
    {
        $sa = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unitA->id,
        ]);

        $this->assertTrue($sa->can('view', $session));
        $this->assertTrue($sa->can('update', $session));
        $this->assertTrue($sa->can('delete', $session));
        $this->assertTrue($sa->can('restore', $session));
        $this->assertTrue($admin->can('update', $session));
        $this->assertTrue($picA->can('update', $session));
        $this->assertFalse($picB->can('update', $session));
        $this->assertFalse($picA->can('delete', $session));
        $this->assertFalse($picB->can('delete', $session));
        $this->assertFalse($picA->can('restore', $session));
        $this->assertFalse($auditor->can('update', $session));
        $this->assertFalse($auditor->can('restore', $session));
    }

    public function test_parent_pic_can_view_child_session(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);
        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);
        $session = ChecklistSession::create(['konteks_penilaian' => 'Child', 'unit_id' => $child->id]);

        $this->assertTrue((new ChecklistSessionPolicy)->view($parentPic, $session));
    }
}
