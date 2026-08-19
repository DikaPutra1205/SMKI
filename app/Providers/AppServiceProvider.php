<?php

namespace App\Providers;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Finding;
use App\Models\Risk;
use App\Models\User;
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
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermissionTo($ability) ? true : null;
        });

        }
}
