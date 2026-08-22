<?php

namespace Tests\Unit;

use App\Models\User;
use App\Routing\PageDispatcher;
use Tests\TestCase;

class RouteDispatcherTest extends TestCase
{
    public function test_known_page_returns_required_permissions(): void
    {
        $d = new PageDispatcher;

        $this->assertNotEmpty($d->requiredPermissions('dashboard'));
        $this->assertSame([], $d->requiredPermissions('/'));
    }

    public function test_page_without_permission_is_denied_for_role(): void
    {
        $d = new PageDispatcher;
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->assertFalse($d->resolve($pic, 'frameworks')->allowed);
        $this->assertTrue($d->resolve($superadmin, 'frameworks')->allowed);
    }

    public function test_allowed_page_resolves_destination_by_role_id(): void
    {
        $d = new PageDispatcher;

        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $kepatuhan = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);

        $this->assertTrue($d->resolve($pic, 'dashboard')->allowed);
        $this->assertTrue($d->resolve($kepatuhan, 'dashboard')->allowed);
        $this->assertTrue($d->resolve($superadmin, 'dashboard')->allowed);
        $this->assertTrue($d->resolve($auditor, 'dashboard')->allowed);

        $this->assertSame('pic/dashboard', $d->resolve($pic, 'dashboard')->component);
        $this->assertSame('kepatuhan/dashboard', $d->resolve($kepatuhan, 'dashboard')->component);
        $this->assertSame('superadmin/dashboard', $d->resolve($superadmin, 'dashboard')->component);
        $this->assertSame('auditor/dashboard', $d->resolve($auditor, 'dashboard')->component);
    }

    public function test_unknown_page_is_denied_or_redirects_to_root(): void
    {
        $d = new PageDispatcher;
        $user = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $res = $d->resolve($user, 'does-not-exist');
        $this->assertFalse($res->allowed);
        $this->assertSame('/', $res->redirectTo);
    }

    public function test_root_always_allowed_and_dispatches_by_role_id(): void
    {
        $d = new PageDispatcher;

        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $kepatuhan = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        foreach ([$pic, $superadmin, $kepatuhan] as $u) {
            $this->assertTrue($d->resolve($u, '/')->allowed);
        }

        $this->assertSame('pic/dashboard', $d->resolve($pic, '/')->component);
        $this->assertSame('superadmin/dashboard', $d->resolve($superadmin, '/')->component);
        $this->assertSame('kepatuhan/dashboard', $d->resolve($kepatuhan, '/')->component);
    }
}
