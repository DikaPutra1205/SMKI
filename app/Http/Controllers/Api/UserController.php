<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    use ApiResponse;

    /** List semua user (untuk dev panel switcher) */
    public function index(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'role', 'unit_id')
                     ->with('unit:id,nama')
                     ->orderBy('role')
                     ->get();
        return $this->success($users);
    }
}
