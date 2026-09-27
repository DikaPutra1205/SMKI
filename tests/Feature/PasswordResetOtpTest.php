<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use App\Services\PasswordResetOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'pic@smki.test';

    private const OLD_PASSWORD = 'Secret12!';

    private function makeUser(): User
    {
        return User::factory()->create([
            'email' => self::EMAIL,
            'password' => Hash::make(self::OLD_PASSWORD),
        ]);
    }

    /**
     * Read the plaintext code off the most recent OTP notification — the only
     * place it ever exists, since the database stores nothing but a hash.
     *
     * NotificationFake treats the closure as a truth-test filter rather than a
     * hook, so it must return true, and the fake hands notifications over in
     * insertion order, so the last call observed is the newest one.
     */
    private function latestCode(User $user): string
    {
        $captured = null;

        Notification::assertSentTo(
            $user,
            PasswordResetOtpNotification::class,
            function (PasswordResetOtpNotification $notification) use (&$captured) {
                $captured = $notification->code;

                return true;
            }
        );

        $this->assertNotNull($captured, 'OTP notification was not sent.');

        return $captured;
    }

    private function storedToken(string $email = self::EMAIL): string
    {
        return (string) DB::table('password_reset_tokens')->where('email', $email)->value('token');
    }

    /**
     * Drive steps 1 and 2 and return the grant that step 3 requires.
     */
    private function grantFor(User $user): string
    {
        $code = $this->latestCode($user);

        $response = $this->post('/verify-otp', ['code' => $code]);

        $grant = (string) session(PasswordResetOtpService::SESSION_GRANT);
        $this->assertNotSame('', $grant, 'No grant was stored in the session.');

        // The grant travels in the URL *and* has to match the session copy.
        $response->assertRedirect(route('password.reset', ['token' => $grant, 'email' => self::EMAIL]));

        return $grant;
    }

    // ---------------------------------------------------------------- step 1

    public function test_forgot_password_issues_otp_and_redirects_to_verify(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL])
            ->assertRedirect(route('password.verify'));

        Notification::assertSentTo($user, PasswordResetOtpNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => self::EMAIL]);
    }

    public function test_forgot_password_requires_a_valid_email(): void
    {
        $this->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_forgot_password_rejects_missing_email(): void
    {
        $this->post('/forgot-password', [])
            ->assertSessionHasErrors('email');
    }

    /**
     * The whole point of the generic response: an unregistered address must be
     * indistinguishable from a registered one.
     */
    public function test_forgot_password_is_silent_for_an_unknown_address(): void
    {
        Notification::fake();
        $this->makeUser();

        $this->post('/forgot-password', ['email' => 'nobody@smki.test'])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nobody@smki.test']);
    }

    public function test_forgot_password_never_stores_the_plaintext_code(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);

        $code = $this->latestCode($user);
        $stored = $this->storedToken();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $stored);
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check($code, $stored));
    }

    public function test_issued_codes_are_six_digits_and_zero_padded(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->latestCode($user));
    }

    public function test_resend_is_ignored_within_the_cooldown_window(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $first = $this->latestCode($user);

        $this->post('/forgot-password', ['email' => self::EMAIL])
            ->assertRedirect(route('password.verify'));

        Notification::assertSentTimes(PasswordResetOtpNotification::class, 1);
        $this->assertSame($first, $this->latestCode($user));
    }

    public function test_resend_issues_a_new_code_once_the_cooldown_lapses(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $first = $this->latestCode($user);

        $this->travel(61)->seconds();

        $this->post('/forgot-password', ['email' => self::EMAIL]);

        Notification::assertSentTimes(PasswordResetOtpNotification::class, 2);
        $second = $this->latestCode($user);

        $this->assertNotSame($first, $second);
        $this->assertFalse(Hash::check($first, $this->storedToken()));
        $this->assertTrue(Hash::check($second, $this->storedToken()));
    }

    // ---------------------------------------------------------------- step 2

    public function test_verify_otp_page_redirects_when_no_code_was_requested(): void
    {
        $this->get('/verify-otp')->assertRedirect(route('password.request'));
    }

    public function test_verify_otp_page_renders_after_a_code_is_requested(): void
    {
        Notification::fake();
        $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);

        $this->get('/verify-otp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/verify-otp')
                ->where('email', self::EMAIL)
                ->has('expiresAt')
                ->has('resendAvailableAt')
            );
    }

    public function test_verify_otp_rejects_a_wrong_code(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $code = $this->latestCode($user);

        $this->post('/verify-otp', ['code' => $code === '000000' ? '111111' : '000000'])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHasErrors('code');

        $this->assertNull(session(PasswordResetOtpService::SESSION_GRANT));
    }

    public function test_verify_otp_rejects_a_code_past_its_lifetime(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $code = $this->latestCode($user);

        $this->travel(config('auth.passwords.users.otp_expire') + 1)->minutes();

        $this->post('/verify-otp', ['code' => $code])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHasErrors('code');
    }

    public function test_verify_otp_rejects_non_numeric_and_short_codes(): void
    {
        Notification::fake();
        $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);

        $this->post('/verify-otp', ['code' => 'abcdef'])->assertSessionHasErrors('code');
        $this->post('/verify-otp', ['code' => '123'])->assertSessionHasErrors('code');
        $this->post('/verify-otp', [])->assertSessionHasErrors('code');
    }

    public function test_verify_otp_is_throttled_to_five_attempts(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $code = $this->latestCode($user);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-otp', ['code' => $code])->assertRedirect();
        }

        $this->post('/verify-otp', ['code' => $code])->assertStatus(429);
    }

    /**
     * Regression: the built-in throttle signature is sha1(domain|ip), so inline
     * `throttle:5,1` made every guest route share one bucket and exhausting the
     * request-code budget locked out OTP verification entirely.
     */
    public function test_throttle_budgets_are_independent_per_step(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        // Burn the whole step-1 budget.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => self::EMAIL])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => self::EMAIL])->assertStatus(429);

        // Step 2 must still be usable.
        $code = $this->latestCode($user);
        $this->post('/verify-otp', ['code' => $code])->assertRedirect();
    }

    public function test_verify_otp_rejects_a_code_minted_for_another_account(): void
    {
        Notification::fake();
        $user = $this->makeUser();
        $other = User::factory()->create([
            'email' => 'other@smki.test',
            'password' => Hash::make(self::OLD_PASSWORD),
        ]);

        // A code issued to `other`, then a session pointed at `user`.
        $this->post('/forgot-password', ['email' => $other->email]);
        $othersCode = $this->latestCode($other);
        $this->post('/forgot-password', ['email' => self::EMAIL]);

        // The address is read from the session, so `other`'s code must not work
        // here no matter what the client posts.
        $this->post('/verify-otp', ['code' => $othersCode, 'email' => $other->email])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHasErrors('code');

        $this->assertNull(session(PasswordResetOtpService::SESSION_GRANT));
    }

    public function test_verify_otp_spends_the_code_when_it_succeeds(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $code = $this->latestCode($user);

        $grant = $this->grantFor($user);

        // The grant replaced the OTP row, so the mailed code is now worthless.
        $this->assertFalse(Hash::check($code, $this->storedToken()));
        $this->assertNotSame($code, $grant);
    }

    // ---------------------------------------------------------------- step 3

    public function test_reset_password_page_renders_with_a_grant(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        $this->get('/reset-password?token='.$grant.'&email='.urlencode(self::EMAIL))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/reset-password')
                ->where('email', self::EMAIL)
                ->where('token', $grant)
            );
    }

    public function test_reset_password_page_redirects_without_a_grant(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        // Same URL, but a browser that never completed step 2 holds no grant.
        $this->flushSession();

        $this->get('/reset-password?token='.$grant.'&email='.urlencode(self::EMAIL))
            ->assertRedirect(route('password.request'));
    }

    /**
     * The security-critical one. Password::reset() is not rate limited, and a
     * 6-digit OTP lives in the same column as a reset token, so without the
     * session grant an attacker could brute force codes straight at step 3.
     */
    public function test_reset_password_rejects_a_known_otp_used_as_a_token(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $code = $this->latestCode($user);

        $this->post('/reset-password', [
            'email' => self::EMAIL,
            'token' => $code,
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_reset_password_rejects_a_mismatched_session_grant(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        $this->post('/reset-password', [
            'email' => self::EMAIL,
            'token' => str_repeat('a', strlen($grant)),
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_reset_password_rejects_weak_passwords(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        foreach (['short1!', 'alllowercase1!', 'ALLUPPERCASE1!', 'NoDigitsHere!!', 'NoSymbols12345'] as $weak) {
            $this->post('/reset-password', [
                'email' => self::EMAIL,
                'token' => $grant,
                'password' => $weak,
                'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_reset_password_rejects_a_mismatched_confirmation(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        $this->post('/reset-password', [
            'email' => self::EMAIL,
            'token' => $grant,
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew2!',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_reset_password_updates_the_password_and_clears_the_token(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        $this->post('/reset-password', [
            'email' => self::EMAIL,
            'token' => $grant,
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertRedirect(route('login'));

        $fresh = $user->fresh();

        $this->assertTrue(Hash::check('BrandNew1!', $fresh->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $fresh->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => self::EMAIL]);
        $this->assertNull(session(PasswordResetOtpService::SESSION_GRANT));
    }

    public function test_reset_password_lets_the_new_password_log_in(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        $this->post('/reset-password', [
            'email' => self::EMAIL,
            'token' => $grant,
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertRedirect(route('login'));

        $this->post('/login', ['email' => self::EMAIL, 'password' => 'BrandNew1!'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_reset_password_invalidates_the_grant_after_a_single_use(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        $payload = [
            'email' => self::EMAIL,
            'token' => $grant,
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ];

        $this->post('/reset-password', $payload)->assertRedirect(route('login'));
        $this->post('/reset-password', $payload)->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check('BrandNew1!', $user->fresh()->password));
    }

    public function test_granted_token_cannot_be_reused_after_a_new_code_is_requested(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        $this->travel(config('auth.passwords.users.otp_resend') + 1)->seconds();
        $this->post('/forgot-password', ['email' => self::EMAIL]);

        $this->post('/reset-password', [
            'email' => self::EMAIL,
            'token' => $grant,
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_authenticated_users_are_bounced_off_the_reset_pages(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $grant = $this->grantFor($user);

        $this->actingAs($user)->get('/reset-password?token='.$grant.'&email='.urlencode(self::EMAIL))
            ->assertRedirect('/dashboard');
    }

    // ------------------------------------------------------------- rendering

    public function test_otp_email_template_renders_the_code_and_lifetime(): void
    {
        $html = view('emails.auth-reset-otp', [
            'recipientName' => 'Dika Putra',
            'code' => '481902',
            'count' => 5,
        ])->render();

        $this->assertStringContainsString('481902', $html);
        $this->assertStringContainsString('Dika Putra', $html);
        $this->assertStringContainsString('5 menit', $html);
    }

    public function test_login_page_receives_the_password_updated_status(): void
    {
        $this->withSession(['status' => 'password-updated'])
            ->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/login')->where('status', 'password-updated'));
    }
}
