<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Control;
use App\Models\Framework;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            $query->where(function ($q) use ($request) {
                $q->where('kode_klausul', 'ilike', "%{$request->search}%")
                    ->orWhere('judul', 'ilike', "%{$request->search}%");
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'framework_id' => 'required|exists:frameworks,id',
            'kode_klausul' => 'required|string|max:20',
            'judul' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'required|in:annex_a,klausul_4_10',
        ]);

        $control = Control::create($data);

        return $this->created($control->load('framework'));
    }

    public function show(Control $control): JsonResponse
    {
        return $this->success($control->load('framework'));
    }

    public function update(Request $request, Control $control): JsonResponse
    {
        $data = $request->validate([
            'kode_klausul' => 'sometimes|string|max:20',
            'judul' => 'sometimes|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'sometimes|in:annex_a,klausul_4_10',
        ]);

        $control->update($data);

        return $this->success($control, 'Kontrol berhasil diperbarui');
    }

    public function destroy(Control $control): JsonResponse
    {
        $control->delete();

        return $this->success(null, 'Kontrol berhasil dihapus');
    }
}
