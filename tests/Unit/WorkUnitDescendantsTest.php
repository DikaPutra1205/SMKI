<?php

namespace Tests\Unit;

use App\Models\WorkUnit;
use Tests\TestCase;

class WorkUnitDescendantsTest extends TestCase
{
    public function test_is_ancestor_of_direct_child(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);

        $this->assertTrue($parent->isAncestorOf($child));
        $this->assertFalse($child->isAncestorOf($parent));
    }

    public function test_is_ancestor_of_grandchild(): void
    {
        $grandparent = WorkUnit::create(['nama' => 'Grandparent']);
        $parent = WorkUnit::create(['nama' => 'Parent', 'parent_id' => $grandparent->id]);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);

        $this->assertTrue($grandparent->isAncestorOf($child));
    }

    public function test_is_not_ancestor_of_sibling(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $childA = WorkUnit::create(['nama' => 'Child A', 'parent_id' => $parent->id]);
        $childB = WorkUnit::create(['nama' => 'Child B', 'parent_id' => $parent->id]);

        $this->assertFalse($childA->isAncestorOf($childB));
    }

    public function test_get_descendant_ids_returns_all_descendants(): void
    {
        $grandparent = WorkUnit::create(['nama' => 'Grandparent']);
        $childA = WorkUnit::create(['nama' => 'Child A', 'parent_id' => $grandparent->id]);
        $childB = WorkUnit::create(['nama' => 'Child B', 'parent_id' => $grandparent->id]);

        $ids = $grandparent->getDescendantIds();

        $this->assertEqualsCanonicalizing([$childA->id, $childB->id], $ids);
    }
}
