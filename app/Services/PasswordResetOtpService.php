<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetOtpService
{
    /**
     * Session key holding the email the OTP was last requested for.
     */
    public const SESSION_EMAIL = 'password_reset_email';

    /**
     * Session key holding the grant minted after a verified OTP. The same value
     * travels in the reset-password URL, and the controller requires both to
     * match, so a mailed 6-digit code can never be replayed against that step.
     */
    public const SESSION_GRANT = 'password_reset_grant';

    /**
     * Issue a fresh OTP for the given address, but only when that address
     * belongs to an account. Callers must respond identically either way so the
     * endpoint cannot be used to discover which emails are registered.
     */
    public function issueOtp(string $email): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return false;
        }

        $code = $this->generateCode();

        $this->storeCode($user, $code);

        Notification::send($user, new PasswordResetOtpNotification($code, $this->otpExpireMinutes()));

        return true;
    }

    /**
     * Verify a mailed OTP. On success it returns the freshly minted grant that
     * authorises the new-password step; minting replaces the OTP row, so the
     * mailed code is spent and cannot be replayed.
     */
    public function verifyOtp(string $email, string $code): ?string
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return null;
        }

        $record = $this->codeRecord($user);

        if (! $record || $this->isExpired($record) || ! Hash::check($code, $record->token)) {
            return null;
        }

        $grant = Password::broker()->createToken($user);

        return is_string($grant) && $grant !== '' ? $grant : null;
    }

    /**
     * Update the password behind a previously granted token.
     */
    public function updatePassword(string $email, string $token, string $password): bool
    {
        $status = Password::broker()->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === PasswordBroker::PASSWORD_RESET;
    }

    /**
     * Seconds a caller must still wait before a replacement OTP is allowed.
     * Zero means a resend may be issued right now.
     */
    public function resendCooldownRemaining(string $email): int
    {
        $expiresAt = $this->resendAvailableAt($email);

        if (! $expiresAt) {
            return 0;
        }

        return $expiresAt->isFuture()
            ? (int) ceil(Carbon::now()->diffInSeconds($expiresAt, false))
            : 0;
    }

    /**
     * When the currently held OTP stops being accepted, or null when the caller
     * has no live code to wait out.
     */
    public function otpExpiresAt(string $email): ?Carbon
    {
        $record = $this->recordForEmail($email);

        if (! $record) {
            return null;
        }

        $expiresAt = Carbon::parse($record->created_at)->addMinutes($this->otpExpireMinutes());

        return $expiresAt->isFuture() ? $expiresAt : null;
    }

    /**
     * Drop any half-finished reset so a stale OTP or grant cannot be resumed.
     */
    public function forget(): void
    {
        request()->session()->forget([self::SESSION_EMAIL, self::SESSION_GRANT]);
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function storeCode(User $user, string $code): void
    {
        $table = config('auth.passwords.users.table');
        $email = $user->getEmailForPasswordReset();

        DB::table($table)->where('email', $email)->delete();

        DB::table($table)->insert([
            'email' => $email,
            'token' => Hash::make($code),
            'created_at' => Carbon::now(),
        ]);
    }

    private function recordForEmail(string $email): ?object
    {
        return DB::table(config('auth.passwords.users.table'))
            ->where('email', $email)
            ->first();
    }

    private function codeRecord(User $user): ?object
    {
        return $this->recordForEmail($user->getEmailForPasswordReset());
    }

    private function isExpired(object $record): bool
    {
        return Carbon::parse($record->created_at)
            ->addMinutes($this->otpExpireMinutes())
            ->isPast();
    }

    private function resendAvailableAt(string $email): ?Carbon
    {
        $record = $this->recordForEmail($email);

        return $record
            ? Carbon::parse($record->created_at)->addSeconds($this->resendCooldownSeconds())
            : null;
    }

    private function otpExpireMinutes(): int
    {
        return (int) config('auth.passwords.users.otp_expire', 5);
    }

    private function resendCooldownSeconds(): int
    {
        return (int) config('auth.passwords.users.otp_resend', 60);
    }
}
