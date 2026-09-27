<?php

namespace App\Providers;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use App\Observers\SmkiObserver;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('id');

        // Universal Email Rerouting for manual testing without altering database
        if (! $this->app->runningUnitTests() && $alwaysTo = config('mail.always_to')) {
            Mail::alwaysTo($alwaysTo);
        }

        // Daftarkan SmkiObserver ke semua model transaksi dan master data
        Framework::observe(SmkiObserver::class);
        Control::observe(SmkiObserver::class);
        User::observe(SmkiObserver::class);
        Role::observe(SmkiObserver::class);
        WorkUnit::observe(SmkiObserver::class);
        ChecklistSession::observe(SmkiObserver::class);
        ChecklistEntry::observe(SmkiObserver::class);
        ComplianceEvidence::observe(SmkiObserver::class);
        Finding::observe(SmkiObserver::class);
        Risk::observe(SmkiObserver::class);

        // RBAC: permission keys ARE Gate abilities, granted per role in the DB
        Gate::after(fn ($user, $ability) => $user->hasPermissionTo($ability));

        // Password reset throttles are declared as named limiters rather than
        // inline `throttle:n,1` because the default throttle signature is
        // sha1(domain|ip) — every guest route on the same host would otherwise
        // share a single bucket, letting a caller exhaust the OTP-attempt
        // budget just by spamming the request-code endpoint. Naming them keeps
        // each step's budget independent and self-documenting.
        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('verify-otp', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('reset-password', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Fail fast on N+1 of the new `role` relation in dev/test. The compat
        // accessor + $appends makes `$user->role` lazy-loadable; this surfaces any
        // controller that forgets `with('role')` during the suite.
        Model::preventLazyLoading(! app()->isProduction());
    }
}
