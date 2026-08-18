<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFrameworkRequest;
use App\Http\Requests\UpdateFrameworkRequest;
use App\Models\Framework;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

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
    public function store(StoreFrameworkRequest $request): JsonResponse
    {
        $framework = Framework::create($request->validated());

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
    public function update(UpdateFrameworkRequest $request, Framework $framework): JsonResponse
    {
        $framework->update($request->validated());

        return $this->success($framework, 'Framework berhasil diperbarui');
    }

    public function destroy(Framework $framework): JsonResponse
    {
        $framework->delete();

        return $this->success(null, 'Framework berhasil dihapus');
    }
}
