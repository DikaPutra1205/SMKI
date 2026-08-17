<?php

use App\Http\Controllers\Admin\ChecklistEntryController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\ComplianceEvidenceController;
use App\Http\Controllers\Admin\ControlController as AdminControlController;
use App\Http\Controllers\Admin\FindingController;
use App\Http\Controllers\Admin\FrameworkController;
use App\Http\Controllers\Admin\RiskController;
use App\Http\Controllers\Admin\WorkUnitController;
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

        // ── [DEV ONLY] Test halaman import/export — hapus sebelum production ──────────
        Route::get('/master-data', function () {
            return Inertia::render('admin-kepatuhan/master-data-test');
        })->name('master-data.test');

        // ── Controls CRUD (Inertia-style) ──────────────────────────────────────────
        Route::post('/controls', [AdminControlController::class, 'store'])->name('controls.store');
        Route::put('/controls/{control}', [AdminControlController::class, 'update'])->name('controls.update');
        Route::delete('/controls/{control}', [AdminControlController::class, 'destroy'])->name('controls.destroy');

        // ── Frameworks CRUD (Inertia-style) ─────────────────────────────────────────
        Route::post('/frameworks', [FrameworkController::class, 'store'])->name('frameworks.store');
        Route::put('/frameworks/{framework}', [FrameworkController::class, 'update'])->name('frameworks.update');
        Route::delete('/frameworks/{framework}', [FrameworkController::class, 'destroy'])->name('frameworks.destroy');

        // ── Work Units CRUD (Inertia-style) ─────────────────────────────────────────
        Route::post('/work-units', [WorkUnitController::class, 'store'])->name('work-units.store');
        Route::put('/work-units/{workUnit}', [WorkUnitController::class, 'update'])->name('work-units.update');
        Route::delete('/work-units/{workUnit}', [WorkUnitController::class, 'destroy'])->name('work-units.destroy');

        // ── Findings (Temuan) (Inertia-style) ───────────────────────────────────────
        Route::post('/findings', [FindingController::class, 'store'])->name('findings.store');
        Route::put('/findings/{finding}', [FindingController::class, 'update'])->name('findings.update');
        Route::patch('/findings/{finding}/status', [FindingController::class, 'updateStatus'])->name('findings.status');
        Route::delete('/findings/{finding}', [FindingController::class, 'destroy'])->name('findings.destroy');

        // ── Risks (Risiko) (Inertia-style) ──────────────────────────────────────────
        Route::post('/risks', [RiskController::class, 'store'])->name('risks.store');
        Route::put('/risks/{risk}', [RiskController::class, 'update'])->name('risks.update');
        Route::delete('/risks/{risk}', [RiskController::class, 'destroy'])->name('risks.destroy');

        // ── Checklist & Evidences (Inertia-style) ───────────────────────────────────
        Route::post('/checklist-entries/generate-monthly', [ChecklistEntryController::class, 'generateMonthly'])->name('checklist.generate-monthly');
        Route::put('/checklist-entries/{checklistEntry}', [ChecklistEntryController::class, 'update'])->name('checklist.update');
        Route::patch('/checklist-entries/{checklistEntry}/verify', [ChecklistEntryController::class, 'verify'])->name('checklist.verify');
        Route::post('/checklist-entries/{id}/restore', [ChecklistEntryController::class, 'restore'])->name('checklist.restore');

        // Evidences
        Route::post('/checklist-entries/{checklistEntry}/evidences', [ComplianceEvidenceController::class, 'store'])->name('evidences.store');
        Route::delete('/evidences/{complianceEvidence}', [ComplianceEvidenceController::class, 'destroy'])->name('evidences.destroy');
        Route::post('/evidences/{id}/restore', [ComplianceEvidenceController::class, 'restore'])->name('evidences.restore');

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
