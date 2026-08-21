<?php

use App\Http\Controllers\Api\AuditLogApiController;
use App\Http\Controllers\Api\ChecklistEntryController;
use App\Http\Controllers\Api\ChecklistSessionController;
use App\Http\Controllers\Api\ComplianceEvidenceController;
use App\Http\Controllers\Api\ComplianceOfficerApiController;
use App\Http\Controllers\Api\ControlController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\FindingController;
use App\Http\Controllers\Api\FrameworkController;
use App\Http\Controllers\Api\ReportExportApiController;
use App\Http\Controllers\Api\RiskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkUnitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| SMKI Backend A — API Routes (temporary auth gate)
|--------------------------------------------------------------------------
*/

// ── Public Auth API (for API Clients / Thunder Client) ──────────────────────
Route::post('v1/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        $user = Auth::user();

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'unit_id' => $user->unit_id,
            ],
        ]);
    }

    return response()->json([
        'status' => 'error',
        'message' => 'Email atau password salah.',
    ], 401);
});

Route::post('v1/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json([
        'status' => 'success',
        'message' => 'Logout berhasil.',
    ]);
});

// ponytail: TEMPORARY — wraps all API routes in session auth so anonymous
// callers can't read PII / mutate data. Replace with token auth when real
// auth lands.
Route::middleware('auth')->group(function () {
    // ── Users ───────────────────────────────────────────────────────────────────
    Route::get('users', [UserController::class, 'index']);

    // ── Master Data ─────────────────────────────────────────────────────────────
    Route::apiResource('frameworks', FrameworkController::class);
    Route::apiResource('controls', ControlController::class);
    Route::get('frameworks/{framework}/controls', [ControlController::class, 'byFramework']);
    Route::apiResource('work-units', WorkUnitController::class);
    Route::get('work-units-tree', [WorkUnitController::class, 'tree']);

    // ── Checklist & Evidences ───────────────────────────────────────────────────
    Route::post('checklist-sessions/{id}/restore', [ChecklistSessionController::class, 'restore']);
    Route::apiResource('checklist-sessions', ChecklistSessionController::class);

    Route::post('checklist-entries/generate-monthly', [ChecklistEntryController::class, 'generateMonthly']);
    Route::post('checklist-entries/{id}/restore', [ChecklistEntryController::class, 'restore']);
    Route::apiResource('checklist-entries', ChecklistEntryController::class);
    Route::patch('checklist-entries/{checklistEntry}/verify', [ChecklistEntryController::class, 'verify']);

    // Evidences
    Route::get('checklist-entries/{checklistEntry}/evidences', [ComplianceEvidenceController::class, 'index']);
    Route::post('checklist-entries/{checklistEntry}/evidences', [ComplianceEvidenceController::class, 'store']);
    Route::get('evidences/{id}/download', [ComplianceEvidenceController::class, 'download']);
    Route::delete('evidences/{complianceEvidence}', [ComplianceEvidenceController::class, 'destroy']);
    Route::post('evidences/{id}/restore', [ComplianceEvidenceController::class, 'restore']);

    // ── Temuan (Findings) ───────────────────────────────────────────────────────
    Route::apiResource('findings', FindingController::class);
    Route::patch('findings/{finding}/status', [FindingController::class, 'updateStatus']);

    // ── Risiko (Risks) ──────────────────────────────────────────────────────────
    Route::apiResource('risks', RiskController::class);

    // ── Dashboard Analytics (All Roles) ───────────────────────────────────────────
    Route::prefix('v1/dashboard')->group(function () {
        Route::get('summary', [DashboardApiController::class, 'summary']);
        Route::get('trends', [DashboardApiController::class, 'trends']);
        Route::get('unit-comparison', [DashboardApiController::class, 'unitComparison']);
        Route::get('recent-activities', [DashboardApiController::class, 'recentActivities']);
    });

    // ── Compliance Officer (Findings, Risk Management & Bulk Verification) ───────
    Route::prefix('v1/compliance-officer')->group(function () {
        Route::get('findings', [ComplianceOfficerApiController::class, 'indexFindings']);
        Route::get('findings/{id}', [ComplianceOfficerApiController::class, 'showFinding']);
        Route::put('findings/{id}', [ComplianceOfficerApiController::class, 'updateFinding']);
        Route::get('risks', [ComplianceOfficerApiController::class, 'indexRisks']);
        Route::get('risks/matrix', [ComplianceOfficerApiController::class, 'riskMatrix']);
        Route::get('risks/{id}', [ComplianceOfficerApiController::class, 'showRisk']);
        Route::put('risks/{id}', [ComplianceOfficerApiController::class, 'updateRisk']);
        Route::post('bulk-verify', [ComplianceOfficerApiController::class, 'bulkVerify']);
    });

    // ── Audit Trail (Pair B) ───────────────────────────────────────────────────
    Route::prefix('v1/audit-logs')->group(function () {
        Route::get('/', [AuditLogApiController::class, 'index']);
        Route::get('/stats', [AuditLogApiController::class, 'stats']);
    });

    // ── Report Generator & Export (Audit Reports) ──────────────────────────────
    Route::prefix('v1/reports')->group(function () {
        Route::get('/compliance-summary', [ReportExportApiController::class, 'complianceSummary']);
        Route::get('/export-pdf', [ReportExportApiController::class, 'exportPdf']);
        Route::get('/export-csv', [ReportExportApiController::class, 'exportCsv']);
    });

    // ── Test Upload ─────────────────────────────────────────────────────────────
    Route::post('/test-upload', function (Request $request) {
        $request->validate(['bukti_file' => 'required|file']);
        $path = Storage::disk('supabase')->put('testing', $request->file('bukti_file'));
        if (! $path) {
            return response()->json(['status' => 'error', 'message' => 'Gagal mengunggah file ke Supabase.'], 500);
        }

        return response()->json(['status' => 'success', 'message' => 'File berhasil diunggah.', 'path' => $path], 200);
    });
});
