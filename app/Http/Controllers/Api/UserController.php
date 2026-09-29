<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    use ApiResponse;

    /** List semua user (untuk dev panel switcher) */
    public function index(): JsonResponse
    {
        Gate::authorize('user.read');

        $users = User::select('id', 'name', 'email', 'role_id', 'unit_id')
            ->with(['role:id,name', 'unit:id,nama'])
            ->orderBy(Role::select('name')->whereColumn('roles.id', 'users.role_id'))
            ->get();

        return $this->success($users);
    }
}
