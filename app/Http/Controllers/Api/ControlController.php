<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreControlRequest;
use App\Http\Requests\UpdateControlRequest;
use App\Models\Control;
use App\Models\Framework;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ControlController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Control::with('framework');

        if ($request->filled('framework_id')) {
            $query->where('framework_id', $request->framework_id);
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        if ($request->filled('search')) {
            $like = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($request, $like) {
                $q->where('kode_klausul', $like, "%{$request->search}%")
                    ->orWhere('judul', $like, "%{$request->search}%");
            });
        }

        $controls = $query->orderBy('kode_klausul')->get();

        return $this->success($controls);
    }

    public function byFramework(Framework $framework): JsonResponse
    {
        $controls = $framework->controls()->orderBy('kode_klausul')->get();

        return $this->success($controls);
    }

    public function store(StoreControlRequest $request): JsonResponse
    {
        Gate::authorize('control.create');

        $control = Control::create($request->validated());

        return $this->created($control->load('framework'));
    }

    public function show(Control $control): JsonResponse
    {
        return $this->success($control->load('framework'));
    }

    public function update(UpdateControlRequest $request, Control $control): JsonResponse
    {
        Gate::authorize('control.update');

        $control->update($request->validated());

        return $this->success($control, 'Kontrol berhasil diperbarui');
    }

    public function destroy(Control $control): JsonResponse
    {
        Gate::authorize('control.delete');

        $control->delete();

        return $this->success(null, 'Kontrol berhasil dihapus');
    }
}
