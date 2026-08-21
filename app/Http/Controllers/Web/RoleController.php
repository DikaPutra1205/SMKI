<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('role.managementview');

        $roles = Role::with('permissions:id,key,module')->orderBy('label')->get([
            'id', 'name', 'label', 'description',
        ]);

        return Inertia::render('superadmin/roles', [
            'roles' => $roles,
            'permissionCatalog' => config('permissions.permissions'),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('role.create');

        $role = Role::create($request->validated());

        if ($request->has('permissions')) {
            $ids = Permission::whereIn('key', $request->input('permissions'))->pluck('id');
            $role->permissions()->sync($ids);
            Role::flushPermissionsCache($role->id);
        }

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Role berhasil ditambahkan.',
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('role.update');

        $role->update($request->only('label'));

        if ($request->has('permissions')) {
            $ids = Permission::whereIn('key', $request->input('permissions', []))->pluck('id');
            $role->permissions()->sync($ids);
            Role::flushPermissionsCache($role->id);
        }

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Role berhasil diperbarui.',
        ]);
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('role.delete');

        if ($role->users()->exists()) {
            abort(422, 'Tidak dapat menghapus role yang masih digunakan user.');
        }

        $role->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Role berhasil dihapus.',
        ]);
    }
}
