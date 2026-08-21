<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFrameworkRequest;
use App\Http\Requests\UpdateFrameworkRequest;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FrameworkController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('superadmin/dashboard', [
            'totalUsers' => User::count(),
            'totalFrameworks' => Framework::count(),
            'totalControls' => Control::count(),
            'frameworks' => Framework::withCount('controls')->orderBy('id')->get(),
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

    public function store(StoreFrameworkRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('file_dokumen')) {
            $file = $request->file('file_dokumen');
            $filename = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('', $filename, 'supabase-frameworks');
            $data['url_file'] = $path;
        }

        unset($data['file_dokumen']);

        Framework::create($data);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Framework berhasil ditambahkan.',
        ]);
    }

    public function update(UpdateFrameworkRequest $request, Framework $framework): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('file_dokumen')) {
            $file = $request->file('file_dokumen');
            $filename = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('', $filename, 'supabase-frameworks');

            $oldPath = $framework->getRawOriginal('url_file');
            if ($oldPath && ! filter_var($oldPath, FILTER_VALIDATE_URL)) {
                try {
                    Storage::disk('supabase-frameworks')->delete($oldPath);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $data['url_file'] = $path;
        }

        unset($data['file_dokumen']);

        $framework->update($data);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Framework berhasil diperbarui.',
        ]);
    }

    public function destroy(Framework $framework): RedirectResponse
    {
        $oldPath = $framework->getRawOriginal('url_file');
        if ($oldPath && ! filter_var($oldPath, FILTER_VALIDATE_URL)) {
            try {
                Storage::disk('supabase-frameworks')->delete($oldPath);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $framework->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Framework berhasil dihapus.',
        ]);
    }
}
