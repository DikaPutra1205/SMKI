<?php

namespace App\Imports;

use App\Models\Control;
use App\Models\Framework;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SmkiMasterDataImport implements Import, WithMultipleSheets
{
    // ── Counters ──────────────────────────────────────────────────────────────

    public int $frameworksCreated = 0;

    public int $frameworksUpdated = 0;

    public int $controlsCreated = 0;

    public int $controlsUpdated = 0;

    /** @var array<array{nama: string, versi: string}> */
    public array $frameworksDeleted = [];

    /** @var array<array{kode_klausul: string, judul: string, framework_nama: string, framework_versi: string}> */
    public array $controlsDeleted = [];

    // ── Options ───────────────────────────────────────────────────────────────

    /**
     * If true, reads data but does NOT persist any changes.
     * Used by the preview/dry-run endpoint.
     */
    public bool $dryRun;

    /** @var array<string, int> (nama|versi) → framework_id */
    public array $frameworkCache = [];

    /** @var array<string> Keys seen in the Excel file: "nama|versi" */
    public array $seenFrameworkKeys = [];

    /** @var array<string> Keys seen in Controls sheet: "framework_id|kode_klausul" */
    public array $seenControlKeys = [];

    public function __construct(bool $dryRun = false)
    {
        $this->dryRun = $dryRun;
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function sheets(): array
    {
        return [
            'Frameworks' => new class($this) implements Import, SkipsEmptyRows, ToCollection, WithHeadingRow
            {
                public function __construct(private SmkiMasterDataImport $parent) {}

                public function collection(Collection $rows): void
                {
                    // Pre-load all existing frameworks into cache
                    foreach (Framework::all() as $fw) {
                        $this->parent->frameworkCache["{$fw->nama}|{$fw->versi}"] = $fw->id;
                    }

                    foreach ($rows as $row) {
                        $nama = trim((string) ($row['nama'] ?? ''));
                        $versi = trim((string) ($row['versi'] ?? ''));

                        if ($nama === '' || $versi === '') {
                            continue;
                        }

                        $cacheKey = "{$nama}|{$versi}";
                        $this->parent->seenFrameworkKeys[] = $cacheKey;

                        $urlFile = isset($row['url_file']) && $row['url_file'] !== ''
                            ? trim((string) $row['url_file'])
                            : null;

                        if (isset($this->parent->frameworkCache[$cacheKey])) {
                            // Existing — update
                            if (! $this->parent->dryRun) {
                                Framework::find($this->parent->frameworkCache[$cacheKey])
                                    ?->update(['url_file' => $urlFile]);
                            }
                            $this->parent->frameworksUpdated++;
                        } else {
                            // New
                            if (! $this->parent->dryRun) {
                                $fw = Framework::create([
                                    'nama' => $nama,
                                    'versi' => $versi,
                                    'url_file' => $urlFile,
                                ]);
                                $this->parent->frameworkCache[$cacheKey] = $fw->id;
                            }
                            $this->parent->frameworksCreated++;
                        }
                    }

                    // Detect frameworks in DB but NOT in Excel → mark for deletion
                    foreach ($this->parent->frameworkCache as $key => $id) {
                        if (! in_array($key, $this->parent->seenFrameworkKeys, true)) {
                            [$nama, $versi] = explode('|', $key, 2);
                            $this->parent->frameworksDeleted[] = [
                                'nama' => $nama,
                                'versi' => $versi,
                            ];

                            if (! $this->parent->dryRun) {
                                Framework::find($id)?->delete();
                            }
                        }
                    }
                }
            },

            'Controls' => new class($this) implements Import, SkipsEmptyRows, ToCollection, WithHeadingRow
            {
                public function __construct(private SmkiMasterDataImport $parent) {}

                public function collection(Collection $rows): void
                {
                    // Ensure framework cache is populated
                    if (empty($this->parent->frameworkCache)) {
                        foreach (Framework::all() as $fw) {
                            $this->parent->frameworkCache["{$fw->nama}|{$fw->versi}"] = $fw->id;
                        }
                    }

                    // Collect all (framework_id, kode_klausul) pairs seen in Excel
                    $validRows = [];

                    foreach ($rows as $row) {
                        $frameworkNama = trim((string) ($row['framework_nama'] ?? ''));
                        $frameworkVersi = trim((string) ($row['framework_versi'] ?? ''));
                        $kodeKlausul = trim((string) ($row['kode_klausul'] ?? ''));
                        $judul = trim((string) ($row['judul'] ?? ''));
                        $kategori = trim((string) ($row['kategori'] ?? ''));

                        if ($kodeKlausul === '' || $judul === '') {
                            continue;
                        }

                        if (! in_array($kategori, ['annex_a', 'klausul_4_10'], true)) {
                            continue;
                        }

                        $fwCacheKey = "{$frameworkNama}|{$frameworkVersi}";
                        $frameworkId = $this->parent->frameworkCache[$fwCacheKey] ?? null;

                        if ($frameworkId === null) {
                            continue;
                        }

                        $deskripsi = isset($row['deskripsi']) && $row['deskripsi'] !== ''
                            ? trim((string) $row['deskripsi'])
                            : null;

                        $controlKey = "{$frameworkId}|{$kodeKlausul}";
                        $this->parent->seenControlKeys[] = $controlKey;

                        $validRows[] = [
                            'framework_id' => $frameworkId,
                            'framework_nama' => $frameworkNama,
                            'framework_versi' => $frameworkVersi,
                            'kode_klausul' => $kodeKlausul,
                            'judul' => $judul,
                            'kategori' => $kategori,
                            'deskripsi' => $deskripsi,
                        ];
                    }

                    // Eager-load all existing controls in ONE single query for instant O(1) in-memory lookup
                    $existingControls = Control::with('framework:id,nama,versi')
                        ->get()
                        ->keyBy(fn (Control $c) => "{$c->framework_id}|{$c->kode_klausul}");

                    // Upsert valid rows in-memory
                    foreach ($validRows as $data) {
                        $controlKey = "{$data['framework_id']}|{$data['kode_klausul']}";
                        $existing = $existingControls->get($controlKey);

                        if ($existing) {
                            if (! $this->parent->dryRun) {
                                $existing->update([
                                    'judul' => $data['judul'],
                                    'kategori' => $data['kategori'],
                                    'deskripsi' => $data['deskripsi'],
                                ]);
                            }
                            $this->parent->controlsUpdated++;
                        } else {
                            if (! $this->parent->dryRun) {
                                Control::create([
                                    'framework_id' => $data['framework_id'],
                                    'kode_klausul' => $data['kode_klausul'],
                                    'judul' => $data['judul'],
                                    'kategori' => $data['kategori'],
                                    'deskripsi' => $data['deskripsi'],
                                ]);
                            }
                            $this->parent->controlsCreated++;
                        }
                    }

                    // Detect controls in DB (for frameworks seen in Excel) but NOT in Excel → soft delete
                    $seenFwIds = array_unique(array_map(
                        fn ($k) => (int) explode('|', $k, 2)[0],
                        $this->parent->seenControlKeys,
                    ));

                    if (! empty($seenFwIds)) {
                        foreach ($existingControls as $ctrl) {
                            if (! in_array($ctrl->framework_id, $seenFwIds, true)) {
                                continue;
                            }

                            $controlKey = "{$ctrl->framework_id}|{$ctrl->kode_klausul}";

                            if (! in_array($controlKey, $this->parent->seenControlKeys, true)) {
                                $this->parent->controlsDeleted[] = [
                                    'kode_klausul' => $ctrl->kode_klausul,
                                    'judul' => $ctrl->judul,
                                    'framework_nama' => $ctrl->framework?->nama ?? '',
                                    'framework_versi' => $ctrl->framework?->versi ?? '',
                                ];

                                if (! $this->parent->dryRun) {
                                    $ctrl->delete();
                                }
                            }
                        }
                    }
                }
            },
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a structured summary array suitable for JSON or flash messages.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'frameworks' => [
                'created' => $this->frameworksCreated,
                'updated' => $this->frameworksUpdated,
                'deleted' => count($this->frameworksDeleted),
                'deleted_detail' => $this->frameworksDeleted,
            ],
            'controls' => [
                'created' => $this->controlsCreated,
                'updated' => $this->controlsUpdated,
                'deleted' => count($this->controlsDeleted),
                'deleted_detail' => $this->controlsDeleted,
            ],
        ];
    }

    /**
     * Generate a human-readable flash message from the import results.
     */
    public function flashMessage(): string
    {
        $parts = [];

        if ($this->frameworksCreated > 0) {
            $parts[] = "{$this->frameworksCreated} framework baru";
        }

        if ($this->frameworksUpdated > 0) {
            $parts[] = "{$this->frameworksUpdated} framework diperbarui";
        }

        if (! empty($this->frameworksDeleted)) {
            $parts[] = count($this->frameworksDeleted).' framework dihapus';
        }

        if ($this->controlsCreated > 0) {
            $parts[] = "{$this->controlsCreated} kontrol baru";
        }

        if ($this->controlsUpdated > 0) {
            $parts[] = "{$this->controlsUpdated} kontrol diperbarui";
        }

        if (! empty($this->controlsDeleted)) {
            $parts[] = count($this->controlsDeleted).' kontrol dihapus';
        }

        return empty($parts)
            ? 'Tidak ada perubahan yang terdeteksi.'
            : 'Import selesai: '.implode(', ', $parts).'.';
    }
}
