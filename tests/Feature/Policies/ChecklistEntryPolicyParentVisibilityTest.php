<?php

namespace Tests\Feature\Policies;

use App\Models\ChecklistEntry;
use App\Models\User;
use App\Models\WorkUnit;
use App\Policies\ChecklistEntryPolicy;
use Tests\TestCase;

class ChecklistEntryPolicyParentVisibilityTest extends TestCase
{
    public function test_parent_pic_can_view_child_entry_and_child_pic_owns(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);

        $parentPic = User::factory()->create(['role' => 'pic', 'unit_id' => $parent->id]);
        $childPic = User::factory()->create(['role' => 'pic', 'unit_id' => $child->id]);

        $entry = ChecklistEntry::factory()->create([
            'unit_id' => $child->id,
            'pic_id' => $childPic->id,
        ]);

        $this->assertTrue($parentPic->can('view', $entry));
        $this->assertTrue($childPic->can('view', $entry));
    }

    public function test_parent_view_any_includes_child_unit(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);

        $parentPic = User::factory()->create(['role' => 'pic', 'unit_id' => $parent->id]);

        $policy = new ChecklistEntryPolicy;

        $this->assertTrue($policy->viewAny($parentPic, $child->id));
    }

    public function test_sibling_cannot_view(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $childA = WorkUnit::create(['nama' => 'Child A', 'parent_id' => $parent->id]);
        $childB = WorkUnit::create(['nama' => 'Child B', 'parent_id' => $parent->id]);

        $picA = User::factory()->create(['role' => 'pic', 'unit_id' => $childA->id]);
        $picB = User::factory()->create(['role' => 'pic', 'unit_id' => $childB->id]);

        $entryB = ChecklistEntry::factory()->create([
            'unit_id' => $childB->id,
            'pic_id' => $picB->id,
        ]);

        $this->assertFalse($picA->can('view', $entryB));
    }
}
