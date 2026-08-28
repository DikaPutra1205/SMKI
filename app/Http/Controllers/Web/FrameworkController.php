<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFrameworkRequest;
use App\Http\Requests\UpdateFrameworkRequest;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\FrameworkDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FrameworkController extends Controller
{
    public function __construct(
        protected ?DashboardAnalyticsService $analyticsService = null
    ) {
        $this->analyticsService = $analyticsService ?? app(DashboardAnalyticsService::class);
    }

    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('superadmin/dashboard', [
            'totalUsers' => User::count(),
            'totalFrameworks' => Framework::count(),
            'totalControls' => Control::count(),
            'frameworks' => Framework::withCount('controls')->orderBy('id')->get(),
            'summary' => $user ? $this->analyticsService->getSummary($user) : null,
            'recent_activities' => $user ? $this->analyticsService->getRecentActivities($user, 6) : [],
            'trends' => $user ? $this->analyticsService->getTrends($user) : [],
        ]);
    }

    public function index(Request $request): Response
    {
        $query = Framework::withCount('controls');

        if (! empty($request->search)) {
            $search = trim($request->search);
            $driver = \DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('nama', $likeOperator, "%{$search}%")
                    ->orWhere('versi', $likeOperator, "%{$search}%");
            });
        }

        $frameworks = $query->orderBy('id', 'asc')->get()->map(fn (Framework $fw) => [
            'id' => $fw->id,
            'nama' => $fw->nama,
            'versi' => $fw->versi,
            'url_file' => $fw->url_file,
            'nama_file' => $fw->nama_file,
            'controls_count' => $fw->controls_count,
        ])->toArray();

        return Inertia::render('superadmin/frameworks', [
            'frameworks' => $frameworks,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(StoreFrameworkRequest $request, FrameworkDocumentService $documents): RedirectResponse
    {
        $data = $request->validated();
        unset($data['file_dokumen']);

        Gate::authorize('framework.create');

        if ($request->hasFile('file_dokumen')) {
            $data['url_file'] = $documents->store($request->file('file_dokumen'));
        }

        Framework::create($data);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Framework berhasil ditambahkan.',
        ]);
    }

    public function update(UpdateFrameworkRequest $request, Framework $framework, FrameworkDocumentService $documents): RedirectResponse
    {
        $data = $request->validated();
        unset($data['file_dokumen']);

        Gate::authorize('framework.update');

        if ($request->hasFile('file_dokumen')) {
            $documents->deleteExisting($framework->getRawOriginal('url_file'));
            $data['url_file'] = $documents->store($request->file('file_dokumen'));
        }

        $framework->update($data);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Framework berhasil diperbarui.',
        ]);
    }

    public function destroy(Framework $framework, FrameworkDocumentService $documents): RedirectResponse
    {
        $documents->deleteExisting($framework->getRawOriginal('url_file'));

        Gate::authorize('framework.delete');

        $framework->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Framework berhasil dihapus.',
        ]);
    }
}
