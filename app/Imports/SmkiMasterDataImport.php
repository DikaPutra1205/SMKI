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
    // ── Details Arrays ────────────────────────────────────────────────────────

    /** @var array<array{nama: string, versi: string, url_file: ?string}> */
    public array $frameworksCreatedDetail = [];

    /** @var array<array{nama: string, versi: string, changes: array<array{field: string, from: mixed, to: mixed}>}> */
    public array $frameworksUpdatedDetail = [];

    /** @var array<array{nama: string, versi: string}> */
    public array $frameworksDeleted = [];

    /** @var array<array{kode_klausul: string, judul: string, kategori: string, deskripsi: ?string, framework_nama: string, framework_versi: string}> */
    public array $controlsCreatedDetail = [];

    /** @var array<array{kode_klausul: string, judul: string, framework_nama: string, framework_versi: string, changes: array<array{field: string, from: mixed, to: mixed}>}> */
    public array $controlsUpdatedDetail = [];

    /** @var array<array{kode_klausul: string, judul: string, framework_nama: string, framework_versi: string}> */
    public array $controlsDeleted = [];

    // ── Options ───────────────────────────────────────────────────────────────

    /**
     * If true, reads data but does NOT persist any changes.
     * Used by the preview/dry-run endpoint.
     */
    public bool $dryRun;

    /** @var array<string, Framework> (nama|versi) → Framework model */
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
                    // Pre-load all existing frameworks
                    foreach (Framework::all() as $fw) {
                        $this->parent->frameworkCache["{$fw->nama}|{$fw->versi}"] = $fw;
                    }

                    foreach ($rows as $row) {
                        $nama = trim((string) ($row['nama'] ?? ''));
                        $versi = trim((string) ($row['versi'] ?? ''));

                        if ($nama === '' || $versi === '') {
                            continue;
                        }

                        $cacheKey = "{$nama}|{$versi}";
                        $this->parent->seenFrameworkKeys[] = $cacheKey;

                        $urlFile = isset($row['url_file']) && trim((string) $row['url_file']) !== ''
                            ? trim((string) $row['url_file'])
                            : null;

                        if (isset($this->parent->frameworkCache[$cacheKey])) {
                            $existing = $this->parent->frameworkCache[$cacheKey];
                            $changes = [];

                            if (($existing->url_file ?? null) !== $urlFile) {
                                $changes[] = [
                                    'field' => 'url_file',
                                    'from' => $existing->url_file ?? '(kosong)',
                                    'to' => $urlFile ?? '(kosong)',
                                ];
                            }

                            if (! empty($changes)) {
                                $this->parent->frameworksUpdatedDetail[] = [
                                    'nama' => $nama,
                                    'versi' => $versi,
                                    'changes' => $changes,
                                ];

                                if (! $this->parent->dryRun) {
                                    $existing->update(['url_file' => $urlFile]);
                                }
                            }
                        } else {
                            $this->parent->frameworksCreatedDetail[] = [
                                'nama' => $nama,
                                'versi' => $versi,
                                'url_file' => $urlFile,
                            ];

                            if (! $this->parent->dryRun) {
                                $fw = Framework::create([
                                    'nama' => $nama,
                                    'versi' => $versi,
                                    'url_file' => $urlFile,
                                ]);
                                $this->parent->frameworkCache[$cacheKey] = $fw;
                            }
                        }
                    }

                    // Detect frameworks in DB but NOT in Excel → mark for soft deletion
                    foreach ($this->parent->frameworkCache as $key => $fw) {
                        if (! in_array($key, $this->parent->seenFrameworkKeys, true)) {
                            $this->parent->frameworksDeleted[] = [
                                'nama' => $fw->nama,
                                'versi' => $fw->versi,
                            ];

                            if (! $this->parent->dryRun) {
                                $fw->delete();
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
                            $this->parent->frameworkCache["{$fw->nama}|{$fw->versi}"] = $fw;
                        }
                    }

                    // Collect valid rows from Excel
                    $validRows = [];
                    foreach ($rows as $row) {
                        $frameworkNama = trim((string) ($row['framework_nama'] ?? ''));
                        $frameworkVersi = trim((string) ($row['framework_versi'] ?? ''));
                        $kodeKlausul = trim((string) ($row['kode_klausul'] ?? ''));
                        $judul = trim((string) ($row['judul'] ?? ''));
                        $rawKategori = strtolower(trim((string) ($row['kategori'] ?? '')));
                        $kategori = match ($rawKategori) {
                            'annex a', 'annex_a', 'annex-a' => 'annex_a',
                            'klausul 4-10', 'klausul_4_10', 'klausul-4-10', 'klausul 4 10' => 'klausul_4_10',
                            default => $rawKategori,
                        };

                        if ($kodeKlausul === '' || $judul === '') {
                            continue;
                        }

                        if (! in_array($kategori, ['annex_a', 'klausul_4_10'], true)) {
                            continue;
                        }

                        $fwCacheKey = "{$frameworkNama}|{$frameworkVersi}";
                        $framework = $this->parent->frameworkCache[$fwCacheKey] ?? null;

                        if (! $framework) {
                            continue;
                        }

                        $deskripsi = isset($row['deskripsi']) && trim((string) $row['deskripsi']) !== ''
                            ? trim((string) $row['deskripsi'])
                            : null;

                        $controlKey = "{$framework->id}|{$kodeKlausul}";
                        $this->parent->seenControlKeys[] = $controlKey;

                        $validRows[] = [
                            'framework_id' => $framework->id,
                            'framework_nama' => $frameworkNama,
                            'framework_versi' => $frameworkVersi,
                            'kode_klausul' => $kodeKlausul,
                            'judul' => $judul,
                            'kategori' => $kategori,
                            'deskripsi' => $deskripsi,
                        ];
                    }

                    // Eager-load all existing controls in memory
                    $existingControls = Control::with('framework:id,nama,versi')
                        ->get()
                        ->keyBy(fn (Control $c) => "{$c->framework_id}|{$c->kode_klausul}");

                    // Check for creations and updates
                    foreach ($validRows as $data) {
                        $controlKey = "{$data['framework_id']}|{$data['kode_klausul']}";
                        $existing = $existingControls->get($controlKey);

                        if ($existing) {
                            $changes = [];

                            $cleanExistingJudul = str_replace(["\r\n", "\r"], "\n", trim((string) $existing->judul));
                            $cleanNewJudul = str_replace(["\r\n", "\r"], "\n", trim((string) $data['judul']));

                            if ($cleanExistingJudul !== $cleanNewJudul) {
                                $changes[] = [
                                    'field' => 'Judul',
                                    'from' => $existing->judul,
                                    'to' => $data['judul'],
                                ];
                            }

                            if ($existing->kategori !== $data['kategori']) {
                                $changes[] = [
                                    'field' => 'Kategori',
                                    'from' => $existing->kategori === 'annex_a' ? 'Annex A' : 'Klausul 4-10',
                                    'to' => $data['kategori'] === 'annex_a' ? 'Annex A' : 'Klausul 4-10',
                                ];
                            }

                            $cleanExistingDesc = str_replace(["\r\n", "\r"], "\n", trim((string) ($existing->deskripsi ?? '')));
                            $cleanNewDesc = str_replace(["\r\n", "\r"], "\n", trim((string) ($data['deskripsi'] ?? '')));

                            if ($cleanExistingDesc !== $cleanNewDesc) {
                                $changes[] = [
                                    'field' => 'Deskripsi',
                                    'from' => $cleanExistingDesc ?: '(kosong)',
                                    'to' => $cleanNewDesc ?: '(kosong)',
                                ];
                            }

                            // Only record and execute update if there are actual field changes
                            if (! empty($changes)) {
                                $this->parent->controlsUpdatedDetail[] = [
                                    'kode_klausul' => $data['kode_klausul'],
                                    'judul' => $data['judul'],
                                    'framework_nama' => $data['framework_nama'],
                                    'framework_versi' => $data['framework_versi'],
                                    'changes' => $changes,
                                ];

                                if (! $this->parent->dryRun) {
                                    $existing->update([
                                        'judul' => $data['judul'],
                                        'kategori' => $data['kategori'],
                                        'deskripsi' => $data['deskripsi'],
                                    ]);
                                }
                            }
                        } else {
                            $this->parent->controlsCreatedDetail[] = [
                                'kode_klausul' => $data['kode_klausul'],
                                'judul' => $data['judul'],
                                'kategori' => $data['kategori'],
                                'deskripsi' => $data['deskripsi'],
                                'framework_nama' => $data['framework_nama'],
                                'framework_versi' => $data['framework_versi'],
                            ];

                            if (! $this->parent->dryRun) {
                                Control::create([
                                    'framework_id' => $data['framework_id'],
                                    'kode_klausul' => $data['kode_klausul'],
                                    'judul' => $data['judul'],
                                    'kategori' => $data['kategori'],
                                    'deskripsi' => $data['deskripsi'],
                                ]);
                            }
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
                'created' => count($this->frameworksCreatedDetail),
                'created_detail' => $this->frameworksCreatedDetail,
                'updated' => count($this->frameworksUpdatedDetail),
                'updated_detail' => $this->frameworksUpdatedDetail,
                'deleted' => count($this->frameworksDeleted),
                'deleted_detail' => $this->frameworksDeleted,
            ],
            'controls' => [
                'created' => count($this->controlsCreatedDetail),
                'created_detail' => $this->controlsCreatedDetail,
                'updated' => count($this->controlsUpdatedDetail),
                'updated_detail' => $this->controlsUpdatedDetail,
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
        $createdFw = count($this->frameworksCreatedDetail);
        $updatedFw = count($this->frameworksUpdatedDetail);
        $deletedFw = count($this->frameworksDeleted);

        $createdCtrl = count($this->controlsCreatedDetail);
        $updatedCtrl = count($this->controlsUpdatedDetail);
        $deletedCtrl = count($this->controlsDeleted);

        $parts = [];

        if ($createdFw > 0) {
            $parts[] = "{$createdFw} framework baru";
        }
        if ($updatedFw > 0) {
            $parts[] = "{$updatedFw} framework diperbarui";
        }
        if ($deletedFw > 0) {
            $parts[] = "{$deletedFw} framework dihapus";
        }

        if ($createdCtrl > 0) {
            $parts[] = "{$createdCtrl} kontrol baru";
        }
        if ($updatedCtrl > 0) {
            $parts[] = "{$updatedCtrl} kontrol diperbarui";
        }
        if ($deletedCtrl > 0) {
            $parts[] = "{$deletedCtrl} kontrol dihapus";
        }

        return empty($parts)
            ? 'Tidak ada perubahan yang terdeteksi (data di Excel sama dengan database).'
            : 'Import selesai: '.implode(', ', $parts).'.';
    }
}
