<?php

namespace App\Imports;

use App\Models\Control;
use App\Models\Framework;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SmkiMasterDataImport implements WithMultipleSheets
{
    public int $frameworksCreated = 0;

    public int $frameworksUpdated = 0;

    public int $controlsCreated = 0;

    public int $controlsUpdated = 0;

    /** @var array<string, int> Cache of (nama|versi) → framework_id */
    private array $frameworkCache = [];

    public function sheets(): array
    {
        return [
            'Frameworks' => new class($this) implements SkipsEmptyRows, ToCollection, WithHeadingRow
            {
                public function __construct(private SmkiMasterDataImport $parent) {}

                public function collection(Collection $rows): void
                {
                    foreach ($rows as $row) {
                        $nama = trim((string) ($row['nama'] ?? ''));
                        $versi = trim((string) ($row['versi'] ?? ''));

                        if ($nama === '' || $versi === '') {
                            continue;
                        }

                        $existing = Framework::where('nama', $nama)
                            ->where('versi', $versi)
                            ->first();

                        $urlFile = isset($row['url_file']) && $row['url_file'] !== '' ? trim((string) $row['url_file']) : null;

                        if ($existing) {
                            $existing->update(['url_file' => $urlFile]);
                            $this->parent->frameworksUpdated++;
                        } else {
                            Framework::create([
                                'nama' => $nama,
                                'versi' => $versi,
                                'url_file' => $urlFile,
                            ]);
                            $this->parent->frameworksCreated++;
                        }

                        // Rebuild cache key
                        $cacheKey = $nama.'|'.$versi;
                        $fw = Framework::where('nama', $nama)->where('versi', $versi)->first();
                        if ($fw) {
                            $this->parent->frameworkCache[$cacheKey] = $fw->id;
                        }
                    }
                }
            },

            'Controls' => new class($this) implements SkipsEmptyRows, ToCollection, WithHeadingRow
            {
                public function __construct(private SmkiMasterDataImport $parent) {}

                public function collection(Collection $rows): void
                {
                    // Reload framework cache in case Frameworks sheet ran first
                    if (empty($this->parent->frameworkCache)) {
                        foreach (Framework::all() as $fw) {
                            $this->parent->frameworkCache["{$fw->nama}|{$fw->versi}"] = $fw->id;
                        }
                    }

                    foreach ($rows as $row) {
                        $frameworkNama = trim((string) ($row['framework_nama'] ?? ''));
                        $frameworkVersi = trim((string) ($row['framework_versi'] ?? ''));
                        $kodeKlausul = trim((string) ($row['kode_klausul'] ?? ''));
                        $judul = trim((string) ($row['judul'] ?? ''));
                        $kategori = trim((string) ($row['kategori'] ?? ''));

                        if ($kodeKlausul === '' || $judul === '') {
                            continue;
                        }

                        // Validate kategori
                        if (! in_array($kategori, ['annex_a', 'klausul_4_10'], true)) {
                            continue;
                        }

                        $cacheKey = "{$frameworkNama}|{$frameworkVersi}";
                        $frameworkId = $this->parent->frameworkCache[$cacheKey] ?? null;

                        if ($frameworkId === null) {
                            continue; // Framework tidak ditemukan — skip baris ini
                        }

                        $deskripsi = isset($row['deskripsi']) && $row['deskripsi'] !== '' ? trim((string) $row['deskripsi']) : null;

                        $existing = Control::where('framework_id', $frameworkId)
                            ->where('kode_klausul', $kodeKlausul)
                            ->first();

                        if ($existing) {
                            $existing->update([
                                'judul' => $judul,
                                'kategori' => $kategori,
                                'deskripsi' => $deskripsi,
                            ]);
                            $this->parent->controlsUpdated++;
                        } else {
                            Control::create([
                                'framework_id' => $frameworkId,
                                'kode_klausul' => $kodeKlausul,
                                'judul' => $judul,
                                'kategori' => $kategori,
                                'deskripsi' => $deskripsi,
                            ]);
                            $this->parent->controlsCreated++;
                        }
                    }
                }
            },
        ];
    }
}
