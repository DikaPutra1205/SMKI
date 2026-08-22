<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Routing\PageDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    // ponytail: TEMPORARY session-auth gate. Real auth (Sanctum/role policy)
    // replaces this — remove the guest/auth wrappers + this controller + auth
    // pages when that lands.

    public function showLogin()
    {
        return Inertia::render('auth/login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            $user = Auth::user();
            $dispatcher = app(PageDispatcher::class);
            $res = $dispatcher->resolve($user, '/');
            $map = [
                'superadmin/dashboard' => '/dashboard',
                'kepatuhan/dashboard' => '/dashboard',
                'auditor/dashboard' => '/dashboard',
                'pic/dashboard' => '/dashboard',
            ];
            $target = $map[$res->component] ?? '/dashboard';

            return redirect()->intended($target);
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgotPassword()
    {
        return Inertia::render('auth/forgot-password');
    }

    // ponytail: stub only — validates email, returns 200, sends no mail yet.
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        return response()->json(['status' => 'ok']);
    }
}
