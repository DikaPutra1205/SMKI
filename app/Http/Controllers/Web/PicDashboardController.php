<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PicDashboardController extends Controller
{
    public function __construct(protected DashboardAnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        Gate::authorize('dashboard.read');
        $user = $request->user();
        abort_unless($user->isPic(), 403);
        $summary = $this->analytics->getSummary($user, null, null);

        $recent = ChecklistSession::with(['framework:id,nama'])
            ->where('unit_id', $user->unit_id)
            ->withCount([
                'entries as total_entries',
                'entries as completed_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_COMPLIANT)
                    ->orWhere(fn ($q2) => $q2->whereIn('status', [ChecklistEntry::STATUS_PARTIAL, ChecklistEntry::STATUS_NON_COMPLIANT, ChecklistEntry::STATUS_NA])->where('catatan', '!=', '')->whereNotNull('catatan')),
            ])
            ->orderByDesc('id')->limit(5)->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'konteks_penilaian' => $s->konteks_penilaian,
                'periode' => $s->periode,
                'framework' => $s->framework?->nama ?? '-',
                'total_entries' => $s->total_entries,
                'completed_entries' => $s->completed_entries,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return Inertia::render('pic/dashboard', [
            'summary' => $summary,
            'trends' => $this->analytics->getTrends($user),
            'recent_sessions' => $recent,
        ]);
    }
}
