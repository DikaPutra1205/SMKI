<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $koordinatorRole = Role::where('name', 'koordinator_smki')->first();
        if ($koordinatorRole) {
            $permissionIds = Permission::whereIn('key', ['risk.create', 'risk.update', 'risk.delete'])->pluck('id');
            $koordinatorRole->permissions()->syncWithoutDetaching($permissionIds);
            Role::flushPermissionsCache($koordinatorRole->id);
        }
    }

    public function down(): void
    {
        $koordinatorRole = Role::where('name', 'koordinator_smki')->first();
        if ($koordinatorRole) {
            $permissionIds = Permission::whereIn('key', ['risk.create', 'risk.update', 'risk.delete'])->pluck('id');
            $koordinatorRole->permissions()->detach($permissionIds);
            Role::flushPermissionsCache($koordinatorRole->id);
        }
    }
};
