<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkVerifyChecklistRequest;
use App\Http\Requests\UpdateFindingRequest;
use App\Http\Requests\UpdateRiskRequest;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Finding;
use App\Models\Risk;
use App\Services\ComplianceOfficerService;
use App\Services\ComplianceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceOfficerController extends Controller
{
    public function __construct(
        protected ComplianceOfficerService $complianceOfficerService,
        protected ComplianceService $complianceService
    ) {}

    /**
     * Findings / Temuan SLA Tracker Page.
     */
    public function findings(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['status', 'category', 'kategori', 'unit_id', 'is_overdue', 'search']);
        $findings = $this->complianceOfficerService->getFindings($user, $filters, 20);
        $workUnits = $this->complianceService->getWorkUnits();

        return Inertia::render('admin-kepatuhan/findings', [
            'findings' => $findings,
            'workUnits' => $workUnits,
            'filters' => $filters,
        ]);
    }

    /**
     * Update finding status / deadline.
     */
    public function updateFinding(UpdateFindingRequest $request, Finding $finding): RedirectResponse
    {
        $user = $request->user();
        $this->complianceOfficerService->updateFinding($user, $finding, $request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Temuan audit berhasil diperbarui.',
        ]);
    }

    /**
     * Risk Register & Matrix Page.
     */
    public function risks(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['risk_level', 'level_risiko', 'status', 'unit_id', 'search']);
        $risks = $this->complianceOfficerService->getRisks($user, $filters, 20);
        $matrix = $this->complianceOfficerService->getRiskMatrix($user);
        $workUnits = $this->complianceService->getWorkUnits();

        return Inertia::render('admin-kepatuhan/risks', [
            'risks' => $risks,
            'matrix' => $matrix,
            'workUnits' => $workUnits,
            'filters' => $filters,
        ]);
    }

    /**
     * Update risk mitigation plan and status.
     */
    public function updateRisk(UpdateRiskRequest $request, Risk $risk): RedirectResponse
    {
        $user = $request->user();
        $this->complianceOfficerService->updateRisk($user, $risk, $request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Register risiko berhasil diperbarui.',
        ]);
    }

    /**
     * Bulk verify checklist entries.
     */
    public function bulkVerify(BulkVerifyChecklistRequest $request): RedirectResponse
    {
        $user = $request->user();
        $entryIds = $request->input('entry_ids', []);
        $status = $request->input('status');
        $adminNotes = $request->input('admin_notes');

        $entries = ChecklistEntry::whereIn('id', $entryIds)->get();
        $hasStatusChange = $entries->contains(fn ($entry) => $entry->status !== $status);

        if ($hasStatusChange && empty(trim($adminNotes ?? ''))) {
            return back()->withErrors([
                'admin_notes' => 'Catatan verifikasi admin wajib diisi karena terdapat perubahan status kepatuhan pada entri terpilih.',
            ])->with('flash', [
                'type' => 'error',
                'message' => 'Catatan verifikasi admin wajib diisi karena terdapat perubahan status kepatuhan pada entri terpilih.',
            ]);
        }

        $verifiedCount = $this->complianceOfficerService->bulkVerifyChecklistEntries($user, $entryIds, $status, $adminNotes);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Berhasil memverifikasi {$verifiedCount} entri checklist secara massal.",
        ]);
    }

    /**
     * Compliance Officer Checklist Review / Bulk Verify page.
     *
     * - No session_id  → render the session-card landing grid so the user picks a session first.
     * - session_id set → render the per-session entry table/filter view with unified verification (single + bulk).
     */
    public function bulkVerifyPage(Request $request): Response
    {
        $user = $request->user();

        if (! $user->hasPermissionTo('checklist.bulk-verify')) {
            abort(403);
        }

        $sessionId = $request->filled('session_id') ? (int) $request->input('session_id') : null;

        // ── Landing: no session selected yet → show session card grid ──────────
        if ($sessionId === null) {
            $filters = $request->only(['search', 'unit_id', 'framework_id', 'periode']);

            $sessions = $this->complianceService->getAdminSessions($filters);

            return Inertia::render('admin-kepatuhan/checklist/bulk-verify-landing', [
                'sessions' => $sessions,
                'workUnits' => $this->complianceService->getWorkUnits(),
                'frameworks' => $this->complianceService->getFrameworkSummaries(),
                'periodeOptions' => $this->complianceService->getSessionPeriodeOptions(),
                'filters' => $filters,
            ]);
        }

        // ── Detail: session selected → show per-session entry review table ─────
        $filters = $request->only(['status', 'unit_id', 'framework_id', 'session_id', 'is_verified', 'search']);

        $entries = $this->complianceOfficerService->getReviewQueueEntries($user, $filters, 20);
        $selectedSession = ChecklistSession::with(['unit:id,nama', 'framework:id,nama,versi'])->find($sessionId);

        return Inertia::render('admin-kepatuhan/checklist/bulk-verify', [
            'entries' => $entries,
            'session' => $selectedSession,
            'workUnits' => $this->complianceService->getWorkUnits(),
            'filters' => $filters,
        ]);
    }

    /**
     * Single and unified checklist verify page.
     * When no session_id is given, lists assessment sessions grouped by unit.
     * When session_id is provided, shows review table with single & bulk verification capability.
     */
    public function verifyPage(Request $request): Response
    {
        $user = $request->user();

        if (! $user->hasPermissionTo('checklist.bulk-verify')) {
            abort(403);
        }

        $sessionId = $request->filled('session_id') ? (int) $request->input('session_id') : null;

        if ($sessionId === null) {
            $filters = $request->only(['search', 'unit_id', 'framework_id', 'periode']);
            $sessions = $this->complianceService->getAdminSessions($filters);

            return Inertia::render('admin-kepatuhan/checklist/bulk-verify-landing', [
                'sessions' => $sessions,
                'workUnits' => $this->complianceService->getWorkUnits(),
                'frameworks' => $this->complianceService->getFrameworkSummaries(),
                'periodeOptions' => $this->complianceService->getSessionPeriodeOptions(),
                'filters' => $filters,
            ]);
        }

        $filters = $request->only(['status', 'unit_id', 'framework_id', 'session_id', 'is_verified', 'search']);

        // Default to showing all or filter as provided
        $entries = $this->complianceOfficerService->getReviewQueueEntries($user, $filters, 20);
        $selectedSession = ChecklistSession::with(['unit:id,nama', 'framework:id,nama,versi'])->find($sessionId);

        return Inertia::render('admin-kepatuhan/checklist/verify', [
            'entries' => $entries,
            'session' => $selectedSession,
            'workUnits' => $this->complianceService->getWorkUnits(),
            'filters' => $filters,
        ]);
    }

    /**
     * Single-entry verify POST action.
     *
     * Accepts: status ('compliant'|'non_compliant'), admin_notes (nullable string).
     * Sets tanggal_verifikasi and admin_id on the entry.
     * Requires 'checklist.bulk-verify' permission (same as bulk-verify).
     *
     * Backend gap note: this method is NEW — it does not exist elsewhere.
     * The existing ChecklistEntryController::update() is PIC-scoped (pic_id gate)
     * and does not write tanggal_verifikasi/admin_id from the admin side.
     */
    public function verifySingle(Request $request, ChecklistEntry $entry): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasPermissionTo('checklist.bulk-verify')) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:compliant,non_compliant',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        if ($entry->status !== $validated['status'] && empty(trim($validated['admin_notes'] ?? ''))) {
            return back()->withErrors([
                'admin_notes' => 'Catatan verifikasi admin wajib diisi jika mengubah status kepatuhan.',
            ])->with('flash', [
                'type' => 'error',
                'message' => 'Catatan verifikasi admin wajib diisi jika mengubah status kepatuhan.',
            ]);
        }

        $entry->update([
            'status' => $validated['status'],
            'catatan_admin' => ! empty(trim($validated['admin_notes'] ?? '')) ? $validated['admin_notes'] : $entry->catatan_admin,
            'tanggal_verifikasi' => now(),
            'admin_id' => $user->id,
        ]);

        $statusLabel = $validated['status'] === 'compliant' ? 'Patuh' : 'Tidak Patuh';

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Entri #{$entry->id} berhasil diverifikasi sebagai {$statusLabel}.",
        ]);
    }
}
