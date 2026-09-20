<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_pic_parent_sees_own_subtree_ids(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);

        $this->assertEqualsCanonicalizing([$parent->id, $child->id], $pic->accessibleUnitIds());
    }

    public function test_superadmin_returns_null_scope(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->assertNull($admin->accessibleUnitIds());
    }
}
