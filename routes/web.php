<?php

use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\ControlController as AdminControlController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — API Health & Info Endpoint (auth required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'app' => 'Sistem Kepatuhan Digital SMKI — Backend API (ISO 27001 & 27701)',
            'status' => 'online',
            'version' => '1.0.0',
            'environment' => config('app.env'),
            'api_prefix' => '/api',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::get('/welcome', function () {
        return Inertia::render('welcome');
    })->name('welcome');

    Route::prefix('admin/kepatuhan')->name('admin.kepatuhan.')->group(function () {
        Route::get('/', function () {
            return redirect()->route('admin.kepatuhan.dashboard');
        });

        Route::get('/dashboard', [ComplianceController::class, 'dashboard'])->name('dashboard');
        Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance');

        // ── Controls CRUD (Inertia-style — mutations only, listing ada di compliance) ──
        Route::post('/controls', [AdminControlController::class, 'store'])->name('controls.store');
        Route::put('/controls/{control}', [AdminControlController::class, 'update'])->name('controls.update');
        Route::delete('/controls/{control}', [AdminControlController::class, 'destroy'])->name('controls.destroy');

        // ── Master Data Export / Import (unified 2-sheet Excel) ───────────────────────
        Route::get('/master-data/export', [AdminControlController::class, 'exportMasterData'])->name('master-data.export');
        Route::post('/master-data/import', [AdminControlController::class, 'importMasterData'])->name('master-data.import');
    });
});

/*
|--------------------------------------------------------------------------
| Auth Routes (guest only) — temporary gate
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
