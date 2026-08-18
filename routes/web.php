<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ChecklistSessionController;
use App\Http\Controllers\Web\ComplianceController;
use App\Http\Controllers\Web\ControlController as AdminControlController;
use App\Http\Controllers\Web\FrameworkController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — Inertia pages & mutations
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

        // ── [DEV ONLY] Test halaman import/export — hapus sebelum production ──────────
        Route::get('/master-data', function () {
            return Inertia::render('admin-kepatuhan/master-data-test');
        })->name('master-data.test');

        // ── Controls CRUD (Inertia-style) ──────────────────────────────────────────
        Route::post('/controls', [AdminControlController::class, 'store'])->name('controls.store');
        Route::put('/controls/{control}', [AdminControlController::class, 'update'])->name('controls.update');
        Route::delete('/controls/{control}', [AdminControlController::class, 'destroy'])->name('controls.destroy');

        // ── Checklist Sessions (Inertia-style) ───────────────────────────────────
        Route::post('/checklist-sessions', [ChecklistSessionController::class, 'store'])->name('checklist-sessions.store');
        Route::put('/checklist-sessions/{checklistSession}', [ChecklistSessionController::class, 'update'])->name('checklist-sessions.update');
        Route::delete('/checklist-sessions/{checklistSession}', [ChecklistSessionController::class, 'destroy'])->name('checklist-sessions.destroy');
        Route::post('/checklist-sessions/{id}/restore', [ChecklistSessionController::class, 'restore'])->name('checklist-sessions.restore');

        // ── Master Data Export / Import (unified 2-sheet Excel) ───────────────────────
        Route::get('/master-data/export', [AdminControlController::class, 'exportMasterData'])->name('master-data.export');
        Route::post('/master-data/import/preview', [AdminControlController::class, 'previewMasterDataImport'])->name('master-data.import.preview');
        Route::post('/master-data/import', [AdminControlController::class, 'importMasterData'])->name('master-data.import');
    });

    Route::prefix('admin/superadmin')->name('admin.superadmin.')->group(function () {
        Route::get('/dashboard', [FrameworkController::class, 'dashboard'])->name('dashboard');
        Route::get('/frameworks', [FrameworkController::class, 'index'])->name('frameworks.index');
        Route::post('/frameworks', [FrameworkController::class, 'store'])->name('frameworks.store');
        Route::patch('/frameworks/{framework}', [FrameworkController::class, 'update'])->name('frameworks.update');
        Route::delete('/frameworks/{framework}', [FrameworkController::class, 'destroy'])->name('frameworks.destroy');
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
