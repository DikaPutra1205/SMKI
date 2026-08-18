<?php

namespace App\Providers;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Finding;
use App\Models\Risk;
use App\Models\User;
use App\Observers\SmkiObserver;
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

        // RBAC Gates
        Gate::define('view-audit-logs', function (User $user) {
            return in_array($user->role, ['superadmin', 'admin_kepatuhan', 'koordinator_smki', 'auditor']);
        });

        Gate::define('export-reports', function (User $user) {
            return in_array($user->role, ['superadmin', 'admin_kepatuhan', 'koordinator_smki', 'auditor']);
        });

        Gate::define('manage-compliance', function (User $user) {
            return in_array($user->role, ['superadmin', 'admin_kepatuhan']);
        });
    }
}
