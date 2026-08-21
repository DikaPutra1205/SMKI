<?php

use App\Http\Controllers\Api\ComplianceEvidenceController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuditorDashboardController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ChecklistEntryController;
use App\Http\Controllers\Web\ChecklistSessionController;
use App\Http\Controllers\Web\ComplianceController;
use App\Http\Controllers\Web\ComplianceOfficerController;
use App\Http\Controllers\Web\ControlController as AdminControlController;
use App\Http\Controllers\Web\FrameworkController;
use App\Http\Controllers\Web\ReportExportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — Inertia pages & mutations
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/evidences/{id}/download', [ComplianceEvidenceController::class, 'download'])->name('evidences.download');

    Route::get('/', function () {
        $target = match (auth()->user()->role) {
            'superadmin' => '/admin/superadmin/dashboard',
            'pic' => '/admin/pic/assessments',
            default => '/admin/kepatuhan/dashboard',
        };

        return redirect($target);
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
        Route::get('/sessions', [ComplianceController::class, 'sessions'])->name('sessions');

        // ── Checklist Sessions (Inertia-style) ───────────────────────────────────
        Route::post('/checklist-sessions', [ChecklistSessionController::class, 'store'])->name('checklist-sessions.store');
        Route::put('/checklist-sessions/{checklistSession}', [ChecklistSessionController::class, 'update'])->name('checklist-sessions.update');
        Route::delete('/checklist-sessions/{checklistSession}', [ChecklistSessionController::class, 'destroy'])->name('checklist-sessions.destroy');
        Route::post('/checklist-sessions/{id}/restore', [ChecklistSessionController::class, 'restore'])->name('checklist-sessions.restore');

        // ── Controls CRUD (Inertia-style) ──────────────────────────────────────────
        Route::post('/controls', [AdminControlController::class, 'store'])->name('controls.store');
        Route::put('/controls/{control}', [AdminControlController::class, 'update'])->name('controls.update');
        Route::delete('/controls/{control}', [AdminControlController::class, 'destroy'])->name('controls.destroy');

        // ── Master Data Export / Import (unified 2-sheet Excel) ───────────────────────
        Route::get('/master-data/export', [AdminControlController::class, 'exportMasterData'])->name('master-data.export');
        Route::post('/master-data/import/preview', [AdminControlController::class, 'previewMasterDataImport'])->name('master-data.import.preview');
        Route::post('/master-data/import', [AdminControlController::class, 'importMasterData'])->name('master-data.import');

        // ── Compliance Officer (Findings, Risks & Verification) ────────────────────
        Route::get('/findings', [ComplianceOfficerController::class, 'findings'])->name('findings.index');
        Route::put('/findings/{finding}', [ComplianceOfficerController::class, 'updateFinding'])->name('findings.update');
        Route::get('/risks', [ComplianceOfficerController::class, 'risks'])->name('risks.index');
        Route::put('/risks/{risk}', [ComplianceOfficerController::class, 'updateRisk'])->name('risks.update');
        Route::get('/checklist/bulk-verify', [ComplianceOfficerController::class, 'bulkVerifyPage'])->name('checklist.bulk-verify');
        Route::post('/bulk-verify', [ComplianceOfficerController::class, 'bulkVerify'])->name('bulk-verify');
        Route::get('/checklist/verify', [ComplianceOfficerController::class, 'verifyPage'])->name('checklist.verify');
        Route::post('/checklist/verify/{entry}', [ComplianceOfficerController::class, 'verifySingle'])->name('checklist.verify.single');

        // ── Audit Trail (Pair B) ───────────────────────────────────────────────────
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        // ── Report Generator (Audit Reports) ──────────────────────────────────────────
        Route::get('/reports/export', [ReportExportController::class, 'exportPdf'])->name('reports.export');
        Route::get('/reports/export-pdf', [ReportExportController::class, 'exportPdf'])->name('reports.export-pdf');
    });

    Route::prefix('admin/superadmin')->name('admin.superadmin.')->group(function () {
        Route::get('/dashboard', [FrameworkController::class, 'dashboard'])->name('dashboard');
        Route::get('/frameworks', [FrameworkController::class, 'index'])->name('frameworks.index');
        Route::post('/frameworks', [FrameworkController::class, 'store'])->name('frameworks.store');
        Route::patch('/frameworks/{framework}', [FrameworkController::class, 'update'])->name('frameworks.update');
        Route::delete('/frameworks/{framework}', [FrameworkController::class, 'destroy'])->name('frameworks.destroy');
    });

    Route::prefix('admin/auditor')->name('admin.auditor.')->group(function () {
        Route::get('/dashboard', [AuditorDashboardController::class, 'index'])->name('dashboard');
    });

    Route::prefix('admin/pic')->name('admin.pic.')->group(function () {
        Route::get('/assessments', [ChecklistSessionController::class, 'index'])->name('assessments');
        Route::post('/assessments', [ChecklistSessionController::class, 'store'])->name('assessments.store');
        Route::get('/assessments/{checklistSession}', [ChecklistSessionController::class, 'show'])->name('assessments.show');
        Route::get('/assessments/{checklistSession}/checklist-page', [ChecklistSessionController::class, 'checklistPage'])->name('assessments.checklist-page');
        Route::patch('/assessments/{checklistSession}', [ChecklistSessionController::class, 'update'])->name('assessments.update');
        Route::get('/assessments/{checklistSession}/summary', [ChecklistSessionController::class, 'summary'])->name('assessments.summary');
        Route::post('/assessments/{checklistSession}/submit', [ChecklistSessionController::class, 'submitAssessment'])->name('assessments.submit');

        Route::patch('/checklist-entries/{id}', [ChecklistEntryController::class, 'update'])->name('entries.update');
        Route::post('/checklist-entries/batch', [ChecklistEntryController::class, 'batchUpdate'])->name('entries.batch');
        Route::post('/checklist-entries/{id}/evidence', [ChecklistEntryController::class, 'uploadEvidence'])->name('entries.evidence');
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

Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');
