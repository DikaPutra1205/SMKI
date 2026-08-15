<?php

namespace App\Providers;

use App\Models\ChecklistEntry;
use App\Models\ComplianceEvidence;
use App\Models\Finding;
use App\Models\Risk;
use App\Observers\SmkiObserver;
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
        ChecklistEntry::observe(SmkiObserver::class);
        ComplianceEvidence::observe(SmkiObserver::class);
        Finding::observe(SmkiObserver::class);
        Risk::observe(SmkiObserver::class);
    }
}
