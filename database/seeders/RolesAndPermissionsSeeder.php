<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            'superadmin' => 'Super Admin',
            'admin_kepatuhan' => 'Admin Kepatuhan',
            'koordinator_smki' => 'Koordinator SMKI',
            'auditor' => 'Auditor',
            'pic' => 'PIC',
        ];

        $permissionIdsByKey = [];
        foreach (config('permissions.permissions') as $module => $keys) {
            foreach ($keys as $key) {
                $permissionIdsByKey[$key] = Permission::updateOrCreate(
                    ['key' => $key],
                    ['module' => $module]
                )->id;
            }
        }

        foreach (config('permissions.roles') as $name => $grantKeys) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['label' => $labels[$name] ?? ucfirst($name)]
            );

            $role->permissions()->sync(array_map(
                fn (string $key) => $permissionIdsByKey[$key],
                $grantKeys
            ));

            Role::flushPermissionsCache($role->id);
        }
    }
}
