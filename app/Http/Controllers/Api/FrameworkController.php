<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFrameworkRequest;
use App\Http\Requests\UpdateFrameworkRequest;
use App\Models\Framework;
use App\Services\FrameworkDocumentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FrameworkController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $frameworks = Framework::withCount('controls')->get();

        return $this->success($frameworks);
    }

    /**
     * Uses StoreFrameworkRequest (same as Web) so unique-nama validation is
     * consistent across both surfaces — no more duplicate framework names via API.
     */
    public function store(StoreFrameworkRequest $request, FrameworkDocumentService $documents): JsonResponse
    {
        $data = $request->validated();
        unset($data['file_dokumen']);

        Gate::authorize('framework.create');

        if ($request->hasFile('file_dokumen')) {
            $data['url_file'] = $documents->store($request->file('file_dokumen'));
        }

        $framework = Framework::create($data);

        return $this->created($framework);
    }

    public function show(Framework $framework): JsonResponse
    {
        $framework->load('controls');

        return $this->success($framework);
    }

    /**
     * Uses UpdateFrameworkRequest (same as Web) so unique-nama validation
     * (with current-record ignore) is consistent across both surfaces.
     */
    public function update(UpdateFrameworkRequest $request, Framework $framework, FrameworkDocumentService $documents): JsonResponse
    {
        $data = $request->validated();
        unset($data['file_dokumen']);

        Gate::authorize('framework.update');

        if ($request->hasFile('file_dokumen')) {
            $documents->deleteExisting($framework->getRawOriginal('url_file'));
            $data['url_file'] = $documents->store($request->file('file_dokumen'));
        }

        $framework->update($data);

        return $this->success($framework, 'Framework berhasil diperbarui');
    }

    public function destroy(Framework $framework, FrameworkDocumentService $documents): JsonResponse
    {
        $documents->deleteExisting($framework->getRawOriginal('url_file'));

        Gate::authorize('framework.delete');

        $framework->delete();

        return $this->success(null, 'Framework berhasil dihapus');
    }
}
