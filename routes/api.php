<?php

use App\Http\Controllers\Api\ChecklistEntryController;
use App\Http\Controllers\Api\ComplianceEvidenceController;
use App\Http\Controllers\Api\ControlController;
use App\Http\Controllers\Api\FindingController;
use App\Http\Controllers\Api\FrameworkController;
use App\Http\Controllers\Api\RiskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkUnitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| SMKI Backend A — API Routes (temporary auth gate)
|--------------------------------------------------------------------------
*/

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
    Route::post('checklist-entries/generate-monthly', [ChecklistEntryController::class, 'generateMonthly']);
    Route::post('checklist-entries/{id}/restore', [ChecklistEntryController::class, 'restore']);
    Route::apiResource('checklist-entries', ChecklistEntryController::class);
    Route::patch('checklist-entries/{checklistEntry}/verify', [ChecklistEntryController::class, 'verify']);

    // Evidences
    Route::get('checklist-entries/{checklistEntry}/evidences', [ComplianceEvidenceController::class, 'index']);
    Route::post('checklist-entries/{checklistEntry}/evidences', [ComplianceEvidenceController::class, 'store']);
    Route::delete('evidences/{complianceEvidence}', [ComplianceEvidenceController::class, 'destroy']);
    Route::post('evidences/{id}/restore', [ComplianceEvidenceController::class, 'restore']);

    // ── Temuan (Findings) ───────────────────────────────────────────────────────
    Route::apiResource('findings', FindingController::class);
    Route::patch('findings/{finding}/status', [FindingController::class, 'updateStatus']);

    // ── Risiko (Risks) ──────────────────────────────────────────────────────────
    Route::apiResource('risks', RiskController::class);

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
