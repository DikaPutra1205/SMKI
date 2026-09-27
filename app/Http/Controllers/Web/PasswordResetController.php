<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetOtpService $otp) {}

    /**
     * Step 2 — ask for the code that was mailed out.
     */
    public function showVerifyOtp(Request $request): Response|RedirectResponse
    {
        $email = (string) $request->session()->get(PasswordResetOtpService::SESSION_EMAIL, '');

        // A visitor who never asked for a code starts over. A visitor whose
        // code has simply lapsed stays put: the page renders an expired state
        // with a resend button, which beats making them retype their address.
        if ($email === '') {
            return redirect()->route('password.request');
        }

        return Inertia::render('auth/verify-otp', $this->verifyProps($email));
    }

    /**
     * Step 2 — exchange a valid code for a grant.
     */
    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $email = (string) $request->session()->get(PasswordResetOtpService::SESSION_EMAIL, '');
        $grant = $email === '' ? null : $this->otp->verifyOtp($email, $request->string('code')->toString());

        if (! $grant) {
            return redirect()
                ->route('password.verify')
                ->withErrors(['code' => 'Kode OTP salah atau sudah kedaluwarsa.']);
        }

        $request->session()->put(PasswordResetOtpService::SESSION_GRANT, $grant);

        return redirect()->route('password.reset', [
            'token' => $grant,
            'email' => $email,
        ]);
    }

    /**
     * Step 3 — ask for the new password.
     */
    public function showResetPassword(Request $request): Response|RedirectResponse
    {
        $email = (string) $request->query('email', '');
        $token = (string) $request->query('token', '');

        if (! $this->grantIsValid($request, $email, $token)) {
            return $this->restartFlow();
        }

        return Inertia::render('auth/reset-password', [
            'email' => $email,
            'token' => $token,
        ]);
    }

    /**
     * Step 3 — store the new password.
     */
    public function updatePassword(ResetPasswordRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();
        $token = $request->string('token')->toString();

        if (! $this->grantIsValid($request, $email, $token)) {
            return $this->restartFlow();
        }

        if (! $this->otp->updatePassword($email, $token, $request->string('password')->toString())) {
            return $this->restartFlow();
        }

        $this->otp->forget();

        return redirect()
            ->route('login')
            ->with('status', 'password-updated');
    }

    /**
     * Props backing the countdown and the resend cooldown.
     *
     * @return array<string, mixed>
     */
    private function verifyProps(string $email): array
    {
        return [
            'email' => $email,
            'expiresAt' => $this->otp->otpExpiresAt($email)?->toIso8601String(),
            'resendAvailableAt' => $this->resendAvailableAt($email),
        ];
    }

    /**
     * A mailed 6-digit code must never be usable as the step-3 token. Requiring
     * the session grant to match closes the brute-force path straight onto
     * Password::reset(), which is not rate limited on its own.
     */
    private function grantIsValid(Request $request, string $email, string $token): bool
    {
        if ($email === '' || $token === '') {
            return false;
        }

        $grant = (string) $request->session()->get(PasswordResetOtpService::SESSION_GRANT, '');

        return $grant !== '' && hash_equals($grant, $token);
    }

    private function resendAvailableAt(string $email): ?string
    {
        $remaining = $this->otp->resendCooldownRemaining($email);

        return $remaining > 0
            ? Carbon::now()->addSeconds($remaining)->toIso8601String()
            : null;
    }

    private function restartFlow(): RedirectResponse
    {
        $this->otp->forget();

        return redirect()
            ->route('password.request')
            ->withErrors(['email' => 'Sesi pengaturan ulang tidak valid atau sudah berakhir. Silakan mulai lagi.']);
    }
}
