<?php

namespace App\Providers;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Finding;
use App\Models\Risk;
use App\Observers\SmkiObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
        // Daftarkan SmkiObserver ke semua model transaksi
        ChecklistSession::observe(SmkiObserver::class);
        ChecklistEntry::observe(SmkiObserver::class);
        ComplianceEvidence::observe(SmkiObserver::class);
        Finding::observe(SmkiObserver::class);
        Risk::observe(SmkiObserver::class);

        // RBAC: permission keys ARE Gate abilities, granted per role in the DB
        Gate::after(fn ($user, $ability) => $user->hasPermissionTo($ability));

        // Fail fast on N+1 of the new `role` relation in dev/test. The compat
        // accessor + $appends makes `$user->role` lazy-loadable; this surfaces any
        // controller that forgets `with('role')` during the suite.
        Model::preventLazyLoading(! app()->isProduction());
    }
}
