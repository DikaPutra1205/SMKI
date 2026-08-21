<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('user.managementview');

        $users = User::select('id', 'name', 'email', 'role_id', 'unit_id')
            ->with(['role:id,name,label', 'unit:id,nama'])
            ->orderBy('name')
            ->get();

        $roles = Role::select('id', 'name', 'label')
            ->orderBy('label')
            ->get();

        $units = WorkUnit::select('id', 'nama')
            ->orderBy('nama')
            ->get();

        return Inertia::render('superadmin/users', [
            'users' => $users,
            'roles' => $roles,
            'units' => $units,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('user.create');

        $data = $request->validated();
        $data['password'] = Hash::make($request->input('password', 'password'));
        User::create($data);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'User berhasil ditambahkan.',
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('user.update');

        $user->update($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'User berhasil diperbarui.',
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('user.delete');

        if ($user->id === auth()->id()) {
            abort(422, 'Tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'User berhasil dihapus.',
        ]);
    }
}
