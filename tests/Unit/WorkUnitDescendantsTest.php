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

    public function test_is_ancestor_self_returns_false(): void
    {
        $unit = WorkUnit::create(['nama' => 'Solo']);
        $this->assertFalse($unit->isAncestorOf($unit));
    }

    public function test_get_descendant_ids_leaf_returns_empty(): void
    {
        $leaf = WorkUnit::create(['nama' => 'Leaf']);
        $this->assertSame([], $leaf->getDescendantIds());
    }

    public function test_get_descendant_ids_deep_tree(): void
    {
        $root = WorkUnit::create(['nama' => 'Root']);
        $a = WorkUnit::create(['nama' => 'A', 'parent_id' => $root->id]);
        $b = WorkUnit::create(['nama' => 'B', 'parent_id' => $a->id]);
        $c = WorkUnit::create(['nama' => 'C', 'parent_id' => $b->id]);
        $d = WorkUnit::create(['nama' => 'D', 'parent_id' => $root->id]);

        $ids = $root->getDescendantIds();
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id, $d->id], $ids);
        $this->assertTrue($root->isAncestorOf($c));
        $this->assertFalse($c->isAncestorOf($root));
    }
}
