<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkVerifyChecklistRequest;
use App\Http\Requests\StoreFindingRequest;
use App\Http\Requests\StoreRiskRequest;
use App\Http\Requests\UpdateFindingRequest;
use App\Http\Requests\UpdateRiskRequest;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Risk;
use App\Models\User;
use App\Notifications\ChecklistEntryRejectedNotification;
use App\Services\ComplianceOfficerService;
use App\Services\ComplianceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceOfficerController extends Controller
{
    public function __construct(
        protected ComplianceOfficerService $complianceOfficerService,
        protected ComplianceService $complianceService
    ) {}

    /**
     * Temuan SLA Tracker Page.
     */
    public function temuan(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['status', 'category', 'kategori', 'unit_id', 'is_overdue', 'search', 'id', 'finding_id']);
        $findings = $this->complianceOfficerService->getFindings($user, $filters, 20);
        $workUnits = $this->complianceService->getWorkUnits();

        $controls = Control::with('framework:id,nama,versi')
            ->select('id', 'framework_id', 'kode_klausul', 'judul')
            ->orderBy('kode_klausul')
            ->get();

        $pics = User::whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
            ->select('id', 'name', 'unit_id')
            ->orderBy('name')
            ->get();

        $targetFindingId = $request->query('id') ?? $request->query('finding_id');
        $initialFinding = null;
        if ($targetFindingId) {
            try {
                // Support loading active findings as well as soft-deleted findings (for archive & notification references)
                $initialFinding = $this->complianceOfficerService->getFinding($user, (int) $targetFindingId, true);
                if ($initialFinding->trashed()) {
                    session()->flash('flash', [
                        'type' => 'warning',
                        'message' => 'Temuan audit ini telah dihapus oleh Admin (arsip).',
                    ]);
                }
            } catch (AuthorizationException $e) {
                session()->flash('flash', [
                    'type' => 'error',
                    'message' => 'Anda tidak memiliki hak akses untuk melihat rincian temuan unit lain.',
                ]);
            } catch (\Throwable $e) {
                session()->flash('flash', [
                    'type' => 'warning',
                    'message' => 'Temuan audit yang dituju tidak ditemukan atau telah dihapus secara permanen.',
                ]);
            }
        }

        return Inertia::render('admin-kepatuhan/temuan', [
            'findings' => $findings,
            'workUnits' => $workUnits,
            'controls' => $controls,
            'pics' => $pics,
            'filters' => $filters,
            'initialFinding' => $initialFinding,
        ]);
    }

    /**
     * Store new finding (Compliance Admin / Superadmin only).
     */
    public function storeFinding(StoreFindingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->complianceOfficerService->storeFinding($user, $request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Temuan audit baru berhasil diterbitkan.',
        ]);
    }

    /**
     * Update finding status / deadline / notes.
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
     * Delete finding.
     */
    public function destroyFinding(Request $request, Finding $finding): RedirectResponse
    {
        Gate::authorize('delete', $finding);

        $this->complianceOfficerService->deleteFinding($request->user(), $finding);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Temuan audit berhasil dihapus.',
        ]);
    }

    /**
     * Restore soft-deleted finding (Admin / Superadmin only).
     */
    public function restoreFinding(Request $request, int $id): RedirectResponse
    {
        $finding = Finding::onlyTrashed()->findOrFail($id);
        Gate::authorize('restore', $finding);

        $finding->restore();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Temuan audit berhasil dipulihkan.',
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
        $controls = Control::with('framework:id,nama,versi')
            ->select('id', 'framework_id', 'kode_klausul', 'judul')
            ->orderBy('kode_klausul')
            ->get();

        return Inertia::render('admin-kepatuhan/risks', [
            'risks' => $risks,
            'matrix' => $matrix,
            'workUnits' => $workUnits,
            'controls' => $controls,
            'filters' => $filters,
        ]);
    }

    /**
     * Store new risk item.
     */
    public function storeRisk(StoreRiskRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->complianceOfficerService->storeRisk($user, $request->validated());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Register risiko baru berhasil ditambahkan.',
        ]);
    }

    /**
     * Update risk mitigation plan, status, and notes.
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
        $decision = $request->input('decision');
        $adminNotes = $request->input('admin_notes');

        $verifiedCount = $this->complianceOfficerService->bulkVerifyChecklistEntries($user, $entryIds, $decision, $adminNotes);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Berhasil memverifikasi {$verifiedCount} entri checklist secara massal.",
        ]);
    }

    /**
     * Read-only roles (koordinator, auditor) may view the review queue
     * without verify rights. POST actions keep the bulk-verify gate.
     *
     * Compliance Officer Checklist Review / Bulk Verify page.
     *
     * - No session_id  → render the session-card landing grid so the user picks a session first.
     * - session_id set → render the per-session entry table/filter view with unified verification (single + bulk).
     */
    private function ensureCanViewReviewQueue(User $user): void
    {
        if ($user->hasPermissionTo('checklist.bulk-verify')) {
            return;
        }

        if ($user->hasPermissionTo('checklist.view') && $user->hasPermissionTo('audit-log.view')) {
            return;
        }

        abort(403);
    }

    /**
     * Single and unified checklist verify page.
     * When no session_id is given, lists assessment sessions grouped by unit.
     * When session_id is provided, shows review table with single & bulk verification capability.
     */
    public function verifyPage(Request $request): Response
    {
        $user = $request->user();

        $this->ensureCanViewReviewQueue($user);

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
     * Accepts: decision ('approve'|'reject'), admin_notes (nullable string).
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
        Gate::authorize('verify', $entry);

        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
            'status' => 'prohibited',
            'admin_notes' => 'required_if:decision,reject|nullable|string|max:2000',
            'level_maturity' => 'sometimes|nullable|integer|min:0|max:5',
        ]);

        $decision = $validated['decision'];
        $isReject = $decision === 'reject';
        $isNa = $entry->status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU;

        $adminNotes = $isReject ? trim($validated['admin_notes']) : null;

        $updatePayload = [
            'status' => $isReject ? ChecklistEntry::WORKFLOW_DALAM_PROSES : ($isNa ? ChecklistEntry::WORKFLOW_TIDAK_BERLAKU : ChecklistEntry::WORKFLOW_SELESAI),
            'catatan_admin' => $adminNotes,
            'tanggal_verifikasi' => $isReject ? null : now(),
            'admin_id' => $user->id,
        ];

        if (array_key_exists('level_maturity', $validated)) {
            $updatePayload['level_maturity'] = $validated['level_maturity'];
        }

        $entry->update($updatePayload);

        if ($isReject) {
            $targetPic = $entry->pic ?? User::where('unit_id', $entry->unit_id)->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))->first();
            if ($targetPic && $targetPic->id !== $user->id) {
                $targetPic->notify(new ChecklistEntryRejectedNotification($entry->fresh(['control', 'session']), $user, $adminNotes));
            }
        }

        $statusLabel = $isReject ? 'Dikembalikan ke PIC' : ($isNa ? 'Tidak Berlaku (terverifikasi)' : 'Selesai Diterapkan');

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Entri #{$entry->id} berhasil diverifikasi sebagai {$statusLabel}.",
        ]);
    }
}
