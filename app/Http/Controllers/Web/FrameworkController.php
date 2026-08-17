<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFrameworkRequest;
use App\Http\Requests\UpdateFrameworkRequest;
use App\Models\Framework;
use Illuminate\Http\RedirectResponse;

class FrameworkController extends Controller
{
    public function store(StoreFrameworkRequest $request): RedirectResponse
    {
        $framework = Framework::create($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Framework '{$framework->nama} ({$framework->versi})' berhasil ditambahkan.",
        ]);
    }

    public function update(UpdateFrameworkRequest $request, Framework $framework): RedirectResponse
    {
        $framework->update($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Framework '{$framework->nama} ({$framework->versi})' berhasil diperbarui.",
        ]);
    }

    public function destroy(Framework $framework): RedirectResponse
    {
        $nama = $framework->nama;
        $versi = $framework->versi;
        $framework->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Framework '{$nama} ({$versi})' berhasil dihapus.",
        ]);
    }
}
