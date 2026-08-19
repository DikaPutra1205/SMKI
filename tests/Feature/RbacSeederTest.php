<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    public function test_seeder_creates_roles_permissions_and_grants_matching_config(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(5, Role::count());
        $this->assertSame(59, Permission::count());

        foreach (config('permissions.roles') as $roleName => $grants) {
            $role = Role::where('name', $roleName)->firstOrFail();
            $dbKeys = $role->permissions()->orderBy('key')->pluck('key')->all();
            $this->assertSame($this->sorted($grants), $dbKeys, "Grant mismatch for role {$roleName}");
        }

        $this->assertSame(
            59,
            Role::where('name', 'superadmin')->first()->permissions()->count()
        );
    }

    private function sorted(array $keys): array
    {
        sort($keys);

        return array_values($keys);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(5, Role::count());
        $this->assertSame(59, Permission::count());
        $this->assertSame(50, Role::where('name', 'admin_kepatuhan')->first()->permissions()->count());
    }

    public function test_seeder_flushes_permission_cache(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $roleKey = Role::permissionsCacheKey(Role::firstOrFail()->id);

        Cache::put($roleKey, ['stale']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertFalse(Cache::has($roleKey));
    }
}
