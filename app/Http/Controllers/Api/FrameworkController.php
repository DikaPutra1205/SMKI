<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Framework;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrameworkController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $frameworks = Framework::withCount('controls')->get();
        return $this->success($frameworks);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama'     => 'required|string|max:255',
            'versi'    => 'required|string|max:50',
            'url_file' => 'nullable|url',
        ]);

        $framework = Framework::create($data);
        return $this->created($framework);
    }

    public function show(Framework $framework): JsonResponse
    {
        $framework->load('controls');
        return $this->success($framework);
    }

    public function update(Request $request, Framework $framework): JsonResponse
    {
        $data = $request->validate([
            'nama'     => 'sometimes|string|max:255',
            'versi'    => 'sometimes|string|max:50',
            'url_file' => 'nullable|url',
        ]);

        $framework->update($data);
        return $this->success($framework, 'Framework berhasil diperbarui');
    }

    public function destroy(Framework $framework): JsonResponse
    {
        $framework->delete();
        return $this->success(null, 'Framework berhasil dihapus');
    }
}
