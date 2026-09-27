<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Routing\PageDispatcher;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(private readonly PasswordResetOtpService $otp) {}

    // ponytail: TEMPORARY session-auth gate. Real auth (Sanctum/role policy)
    // replaces this — remove the guest/auth wrappers + this controller + auth
    // pages when that lands.

    public function showLogin(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]);
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

    /**
     * Step 1 — mail a one-time code when the address belongs to an account.
     *
     * The response is identical whether or not the address is registered, so
     * this endpoint cannot be used to enumerate accounts.
     */
    public function forgotPassword(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();

        if ($this->otp->resendCooldownRemaining($email) === 0) {
            $this->otp->issueOtp($email);
        }

        $request->session()->put(PasswordResetOtpService::SESSION_EMAIL, $email);

        return redirect()->route('password.verify');
    }
}
