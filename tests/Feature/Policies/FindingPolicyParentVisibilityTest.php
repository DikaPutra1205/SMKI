<?php

namespace Tests\Feature\Policies;

use App\Models\Finding;
use App\Models\User;
use App\Models\WorkUnit;
use App\Policies\FindingPolicy;
use Tests\TestCase;

class FindingPolicyParentVisibilityTest extends TestCase
{
    public function test_parent_pic_can_view_child_finding_and_child_pic_owns(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);

        $parentPic = User::factory()->create(['role' => 'pic', 'unit_id' => $parent->id]);
        $childPic = User::factory()->create(['role' => 'pic', 'unit_id' => $child->id]);

        $finding = Finding::factory()->create([
            'unit_id' => $child->id,
            'pic_id' => $childPic->id,
        ]);

        $this->assertTrue($parentPic->can('view', $finding));
        $this->assertTrue($childPic->can('view', $finding));
    }

    public function test_parent_view_any_includes_child_unit(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);

        $parentPic = User::factory()->create(['role' => 'pic', 'unit_id' => $parent->id]);

        $policy = new FindingPolicy;

        $this->assertTrue($policy->viewAny($parentPic, $child->id));
    }
}
