<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $picRole = Role::where('name', 'pic')->first();
        if ($picRole) {
            $permissionIds = Permission::whereIn('key', ['risk.create', 'risk.delete'])->pluck('id');
            $picRole->permissions()->detach($permissionIds);
            Role::flushPermissionsCache($picRole->id);
        }
    }

    public function down(): void
    {
        $picRole = Role::where('name', 'pic')->first();
        if ($picRole) {
            $permissionIds = Permission::whereIn('key', ['risk.create', 'risk.delete'])->pluck('id');
            $picRole->permissions()->syncWithoutDetaching($permissionIds);
            Role::flushPermissionsCache($picRole->id);
        }
    }
};
