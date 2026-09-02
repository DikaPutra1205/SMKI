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
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PicDashboardController;
use App\Http\Controllers\Web\ReportExportController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\WorkUnitController;
use App\Models\ChecklistSession;
use App\Models\Finding;
use App\Routing\PageDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — Inertia pages & mutations
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/evidences/{id}/download', [ComplianceEvidenceController::class, 'download'])->name('evidences.download');

    Route::get('/', function (PageDispatcher $dispatcher) {
        $res = $dispatcher->resolve(auth()->user(), '/');
        $map = [
            'superadmin/dashboard' => '/dashboard',
            'kepatuhan/dashboard' => '/dashboard',
            'auditor/dashboard' => '/dashboard',
            'pic/dashboard' => '/dashboard',
        ];

        return redirect($map[$res->component] ?? '/dashboard');
    });

    Route::get('/welcome', function () {
        return Inertia::render('welcome');
    })->name('welcome');

    // ── Flat page routes (permission-gated, role_id-dispatched) ─────────────
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('/frameworks', [PageController::class, 'frameworks'])->name('frameworks.index');
    Route::get('/users', [PageController::class, 'users'])->name('users.index');
    Route::get('/roles', [PageController::class, 'roles'])->name('roles.index');
    Route::get('/checklist', [PageController::class, 'checklist'])->name('checklist');
    Route::get('/checklist/{checklistSession}', [ChecklistSessionController::class, 'show'])->name('checklist.show.alias');
    Route::get('/pic/checklist/{checklistSession}', [ChecklistSessionController::class, 'show'])->name('pic.checklist.show.alias');
    Route::get('/compliance', [PageController::class, 'compliance'])->name('compliance');
    Route::get('/temuan', [PageController::class, 'temuan'])->name('temuan.index');
    Route::get('/admin/pic/temuan', function () {
        return redirect('/temuan'.(request()->getQueryString() ? '?'.request()->getQueryString() : ''));
    })->name('admin.pic.temuan.alias');
    Route::post('/temuan', [ComplianceOfficerController::class, 'storeFinding'])->name('temuan.store.direct');
    Route::put('/temuan/{finding}', [ComplianceOfficerController::class, 'updateFinding'])->name('temuan.update.direct');
    Route::get('/risks', [PageController::class, 'risks'])->name('risks.index');
    Route::get('/audit-logs', [PageController::class, 'auditLogs'])->name('audit-logs.index');

    Route::prefix('admin/kepatuhan')->name('admin.kepatuhan.')->group(function () {
        Route::get('/', function () {
            return redirect()->route('admin.kepatuhan.dashboard');
        });

        Route::get('/dashboard', [ComplianceController::class, 'dashboard'])->name('dashboard');
        Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance');
        Route::get('/sessions', [ComplianceController::class, 'sessions'])->name('sessions');

        // ── Checklist Sessions (Inertia-style) ───────────────────────────────────
        Route::post('/checklist-sessions', [ChecklistSessionController::class, 'generate'])->name('checklist-sessions.store');
        Route::post('/generate-monthly', [ChecklistSessionController::class, 'generateMonthly'])->name('checklist-sessions.generate-monthly');
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

        // ── Compliance Officer (Temuan, Risks & Verification) ────────────────────
        Route::get('/temuan', [ComplianceOfficerController::class, 'temuan'])->name('temuan.index');
        Route::post('/temuan', [ComplianceOfficerController::class, 'storeFinding'])->name('temuan.store');
        Route::put('/temuan/{finding}', [ComplianceOfficerController::class, 'updateFinding'])->name('temuan.update');
        Route::get('/risks', [ComplianceOfficerController::class, 'risks'])->name('risks.index');
        Route::post('/risks', [ComplianceOfficerController::class, 'storeRisk'])->name('risks.store');
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

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/units', [WorkUnitController::class, 'index'])->name('units.index');
        Route::post('/units', [WorkUnitController::class, 'store'])->name('units.store');
        Route::patch('/units/{workUnit}', [WorkUnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{workUnit}', [WorkUnitController::class, 'destroy'])->name('units.destroy');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::prefix('admin/auditor')->name('admin.auditor.')->group(function () {
        Route::get('/dashboard', [AuditorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::prefix('admin/pic')->name('admin.pic.')->group(function () {
        Route::get('/dashboard', [PicDashboardController::class, 'index'])->name('dashboard');
        Route::get('/checklist', [ChecklistSessionController::class, 'index'])->name('checklist');
        Route::post('/checklist', [ChecklistSessionController::class, 'store'])->name('checklist.store');
        Route::get('/checklist/{checklistSession}', [ChecklistSessionController::class, 'show'])->name('checklist.show');
        Route::get('/checklist/{checklistSession}/checklist-page', [ChecklistSessionController::class, 'checklistPage'])->name('checklist.checklist-page');
        Route::patch('/checklist/{checklistSession}', [ChecklistSessionController::class, 'update'])->name('checklist.update');
        Route::get('/checklist/{checklistSession}/summary', [ChecklistSessionController::class, 'summary'])->name('checklist.summary');
        Route::post('/checklist/{checklistSession}/submit', [ChecklistSessionController::class, 'submitAssessment'])->name('checklist.submit');

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

/*
|--------------------------------------------------------------------------
| Email Preview Routes (Local Development Only)
|--------------------------------------------------------------------------
*/
if (app()->isLocal()) {
    Route::prefix('email-preview')->group(function () {
        // Main Interactive Gallery Hub
        Route::get('/', function () {
            return view('emails.preview-hub');
        });

        // 1.1 Temuan Baru Diterbitkan Admin -> PIC
        Route::get('/1-1-finding-created', function () {
            $findingId = Finding::value('id') ?? 8;

            return view('emails.finding-created', [
                'recipientName' => 'Dika Putra (PIC Unit TI)',
                'kodeKlausul' => 'A.5.1',
                'judulKontrol' => 'Kebijakan Keamanan Informasi',
                'kategori' => 'major',
                'kategoriLabel' => 'Mayor',
                'deadlineStr' => now()->addDays(14)->format('d M Y'),
                'actorName' => 'Admin Kepatuhan',
                'catatan' => 'Dokumen kebijakan keamanan informasi belum ditandatangani oleh pimpinan manajemen puncak sesuai persyaratan klausul 5.1 ISO 27001.',
                'actionUrl' => url("/temuan?id={$findingId}"),
            ]);
        });
        Route::get('/finding-created', fn () => redirect('/email-preview/1-1-finding-created'));

        // 1.2.a PIC Memperbarui Status (In Progress / Resolved) -> Admin Kepatuhan
        Route::get('/1-2-finding-pic-to-admin-update', function () {
            $findingId = Finding::value('id') ?? 8;

            return view('emails.finding-status-pic-to-admin', [
                'recipientName' => 'Admin Kepatuhan',
                'kodeKlausul' => 'A.8.8',
                'judulKontrol' => 'Pengelolaan Kerentanan Teknis',
                'unitName' => 'Pusat Teknologi Informasi (TI)',
                'fromStatus' => 'in_progress',
                'fromLabel' => 'Dalam Penanganan (In Progress)',
                'toLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                'toStatus' => 'resolved',
                'actorName' => 'Dika Putra (PIC TI)',
                'catatan' => 'Patch keamanan kernel Ubuntu telah diaplikasikan ke seluruh cluster server production dan laporan vulnerability scanning terbaru sudah diunggah.',
                'actionUrl' => url("/temuan?id={$findingId}"),
            ]);
        });
        Route::get('/1-2-finding-pic-to-admin', fn () => redirect('/email-preview/1-2-finding-pic-to-admin-update'));

        // 1.2.b PIC Menutup Temuan (Closed) -> Admin Kepatuhan
        Route::get('/1-2-finding-pic-to-admin-closed', function () {
            $findingId = Finding::value('id') ?? 8;

            return view('emails.finding-status-pic-to-admin', [
                'recipientName' => 'Admin Kepatuhan',
                'kodeKlausul' => 'A.8.8',
                'judulKontrol' => 'Pengelolaan Kerentanan Teknis',
                'unitName' => 'Pusat Teknologi Informasi (TI)',
                'fromStatus' => 'resolved',
                'fromLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                'toLabel' => 'Ditutup oleh PIC (Closed)',
                'toStatus' => 'closed',
                'actorName' => 'Dika Putra (PIC TI)',
                'catatan' => 'Seluruh rekomendasi audit telah diselesaikan dan sistem telah beroperasi normal tanpa celah keamanan.',
                'actionUrl' => url("/temuan?id={$findingId}"),
            ]);
        });

        // 1.2.c PIC Mengembalikan Status (Resolved -> In Progress) -> Admin Kepatuhan
        Route::get('/1-2-finding-pic-to-admin-reverted', function () {
            $findingId = Finding::value('id') ?? 8;

            return view('emails.finding-status-pic-to-admin', [
                'recipientName' => 'Admin Kepatuhan',
                'kodeKlausul' => 'A.8.8',
                'judulKontrol' => 'Pengelolaan Kerentanan Teknis',
                'unitName' => 'Pusat Teknologi Informasi (TI)',
                'fromStatus' => 'resolved',
                'fromLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                'toLabel' => 'Dalam Penanganan (In Progress)',
                'toStatus' => 'in_progress',
                'actorName' => 'Dika Putra (PIC TI)',
                'catatan' => 'Ditemukan anomali konfigurasi tambahan saat testing ulang, sehingga status kami kembalikan ke Dalam Penanganan untuk perbaikan komprehensif.',
                'actionUrl' => url("/temuan?id={$findingId}"),
            ]);
        });

        // 1.3.a Admin Mengembalikan Status (Perlu Revisi) -> PIC
        Route::get('/1-3-finding-admin-to-pic-revision', function () {
            $findingId = Finding::value('id') ?? 8;

            return view('emails.finding-status-admin-to-pic', [
                'recipientName' => 'Dika Putra (PIC Unit TI)',
                'kodeKlausul' => 'A.8.8',
                'judulKontrol' => 'Pengelolaan Kerentanan Teknis',
                'fromStatus' => 'resolved',
                'fromLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                'toLabel' => 'Dalam Penanganan / Perlu Revisi (In Progress)',
                'toStatus' => 'in_progress',
                'actorName' => 'Admin Kepatuhan',
                'catatan' => 'Laporan scanning kerentanan belum mencakup port database internal. Mohon lakukan scanning ulang dan lampirkan buktinya.',
                'actionUrl' => url("/temuan?id={$findingId}"),
            ]);
        });

        // 1.3.b Admin Mengubah/Verifikasi Status (Closed) -> PIC
        Route::get('/1-3-finding-admin-to-pic-closed', function () {
            $findingId = Finding::value('id') ?? 8;

            return view('emails.finding-status-admin-to-pic', [
                'recipientName' => 'Dika Putra (PIC Unit TI)',
                'kodeKlausul' => 'A.8.8',
                'judulKontrol' => 'Pengelolaan Kerentanan Teknis',
                'fromStatus' => 'resolved',
                'fromLabel' => 'Selesai Ditindaklanjuti (Resolved)',
                'toLabel' => 'Ditutup & Terverifikasi (Closed)',
                'toStatus' => 'closed',
                'actorName' => 'Admin Kepatuhan',
                'catatan' => 'Bukti laporan scanning kerentanan dan log patching telah diperiksa dan dinyatakan memenuhi standar kepatuhan ISO 27001. Temuan resmi ditutup.',
                'actionUrl' => url("/temuan?id={$findingId}"),
            ]);
        });

        // 1.3.c Admin Memperbarui Progres (In Progress / Resolved) -> PIC
        Route::get('/1-3-finding-admin-to-pic-progress', function () {
            $findingId = Finding::value('id') ?? 8;

            return view('emails.finding-status-admin-to-pic', [
                'recipientName' => 'Dika Putra (PIC Unit TI)',
                'kodeKlausul' => 'A.8.8',
                'judulKontrol' => 'Pengelolaan Kerentanan Teknis',
                'fromStatus' => 'open',
                'fromLabel' => 'Terbuka (Open)',
                'toLabel' => 'Dalam Penanganan (In Progress)',
                'toStatus' => 'in_progress',
                'actorName' => 'Admin Kepatuhan',
                'catatan' => 'Admin telah memulai asistensi teknis dan mengarahkan rencana penanganan mitigasi untuk unit Anda.',
                'actionUrl' => url("/temuan?id={$findingId}"),
            ]);
        });
        Route::get('/1-3-finding-admin-to-pic', fn () => redirect('/email-preview/1-3-finding-admin-to-pic-closed'));
        Route::get('/finding-status-changed', fn () => redirect('/email-preview/1-3-finding-admin-to-pic-closed'));

        // 2.1 Entri Checklist Ditolak / Tidak Patuh -> PIC
        Route::get('/2-1-checklist-rejected', function () {
            $sessionId = ChecklistSession::value('id') ?? 1;

            return view('emails.checklist-rejected', [
                'recipientName' => 'Dika Putra (PIC Unit TI)',
                'kodeKlausul' => 'A.8.1',
                'judulKontrol' => 'Inventarisasi Aset Pengguna & Informasi',
                'catatanAdmin' => 'Berkas daftar inventaris aset yang diunggah belum mencantumkan klasifikasi informasi (Confidential/Internal/Public) dan penanggung jawab aset.',
                'actorName' => 'Admin Kepatuhan',
                'actionUrl' => url("/admin/pic/checklist/{$sessionId}"),
            ]);
        });
        Route::get('/checklist-rejected', fn () => redirect('/email-preview/2-1-checklist-rejected'));

        // 4.1 Permintaan Reset Kata Sandi Akun
        Route::get('/4-1-auth-reset-password', function () {
            return view('emails.auth-reset-password', [
                'recipientName' => 'Dika Putra',
                'resetUrl' => url('/reset-password?token=sample-secure-token-12345&email=dika.putra12@gmail.com'),
                'count' => 60,
            ]);
        });

        // Interactive Send Test Email Endpoint
        Route::post('/send-test', function (Request $request) {
            $email = $request->input('email');
            $template = $request->input('template', '1-1');

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['success' => false, 'message' => 'Alamat email tidak valid.'], 422);
            }

            try {
                Artisan::call('smki:test-email', [
                    '--to' => $email,
                    '--template' => $template,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Email uji coba template [{$template}] berhasil dikirim ke {$email}!",
                ]);
            } catch (Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengirim email: '.$e->getMessage(),
                ], 500);
            }
        });
    });
}
