<?php

namespace App\Imports;

use App\Models\Control;
use App\Models\Framework;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SmkiMasterDataImport implements Import, SkipsUnknownSheets, WithMultipleSheets
{
    /** @var array<array{nama: string, versi: string, url_file: ?string}> */
    public array $frameworksCreatedDetail = [];

    /** @var array<array{nama: string, versi: string, changes: array<array{field: string, from: mixed, to: mixed}>}> */
    public array $frameworksUpdatedDetail = [];

    /** @var array<array{nama: string, versi: string}> */
    public array $frameworksDeleted = [];

    /** @var array<array{kode_klausul: string, judul: string, kategori: string, deskripsi: ?string, domain_peran: ?string, framework_nama: string, framework_versi: string}> */
    public array $controlsCreatedDetail = [];

    /** @var array<array{kode_klausul: string, judul: string, framework_nama: string, framework_versi: string, changes: array<array{field: string, from: mixed, to: mixed}>}> */
    public array $controlsUpdatedDetail = [];

    /** @var array<array{kode_klausul: string, judul: string, framework_nama: string, framework_versi: string}> */
    public array $controlsDeleted = [];

    public bool $dryRun;
    public bool $frameworksSheetSeen = false;
    public bool $controlsSheetSeen = false;

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

    public function onUnknownSheet(string|int $sheetName): void
    {
        // Gracefully ignore absent or extra sheet names
    }

    public function sheets(): array
    {
        $frameworksImporter = new SmkiFrameworksSheetImport($this);
        $controlsImporter = new SmkiControlsSheetImport($this, false);
        $fallbackImporter = new SmkiControlsSheetImport($this, true);

        return [
            'Frameworks' => $frameworksImporter,
            'Controls' => $controlsImporter,
            'Sheet1' => $fallbackImporter,
            'Sheet 1' => $fallbackImporter,
            'Master Data' => $fallbackImporter,
            'Daftar Kontrol' => $fallbackImporter,
            'Laporan' => $fallbackImporter,
            'ISO27001' => $fallbackImporter,
            'ISO 27001' => $fallbackImporter,
        ];
    }

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

class SmkiSingleSheetImport implements Import, SkipsEmptyRows, ToCollection
{
    public function __construct(private SmkiMasterDataImport $parent) {}

    public function collection(Collection $rows): void
    {
        $controlsImporter = new SmkiControlsSheetImport($this->parent, true);
        $controlsImporter->collection($rows);
    }
}

class SmkiFrameworksSheetImport implements Import, SkipsEmptyRows, ToCollection
{
    public function __construct(private SmkiMasterDataImport $parent) {}

    public function collection(Collection $rows): void
    {
        $this->parent->frameworksSheetSeen = true;

        foreach (Framework::all() as $fw) {
            $this->parent->frameworkCache["{$fw->nama}|{$fw->versi}"] = $fw;
        }

        $headerRowIdx = 0;
        $colMap = [];

        foreach ($rows as $idx => $row) {
            $rowArr = is_array($row) ? $row : $row->toArray();
            $rowText = strtolower(implode(' ', array_filter($rowArr)));
            if (str_contains($rowText, 'nama') && str_contains($rowText, 'versi')) {
                $headerRowIdx = $idx;
                foreach ($rowArr as $cKey => $cVal) {
                    $val = strtolower(trim((string) $cVal));
                    if ($val === 'nama' || str_contains($val, 'nama')) {
                        $colMap['nama'] = $cKey;
                    } elseif ($val === 'versi' || str_contains($val, 'versi')) {
                        $colMap['versi'] = $cKey;
                    } elseif (str_contains($val, 'url')) {
                        $colMap['url_file'] = $cKey;
                    }
                }
                break;
            }
        }

        if (! isset($colMap['nama'], $colMap['versi'])) {
            $colMap = ['nama' => 0, 'versi' => 1, 'url_file' => 2];
        }

        foreach ($rows as $idx => $row) {
            if ($idx <= $headerRowIdx && isset($colMap['nama'])) {
                continue;
            }

            $rowArr = is_array($row) ? $row : $row->toArray();
            $nama = trim((string) ($rowArr[$colMap['nama']] ?? ''));
            $versi = trim((string) ($rowArr[$colMap['versi']] ?? ''));

            if ($nama === '' || $versi === '') {
                continue;
            }

            $cacheKey = "{$nama}|{$versi}";
            $this->parent->seenFrameworkKeys[] = $cacheKey;

            $urlCol = $colMap['url_file'] ?? null;
            $urlFile = $urlCol !== null && isset($rowArr[$urlCol]) && trim((string) $rowArr[$urlCol]) !== ''
                ? trim((string) $rowArr[$urlCol])
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

        if (! empty($this->parent->seenFrameworkKeys)) {
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
    }
}

class SmkiControlsSheetImport implements Import, SkipsEmptyRows, ToCollection
{
    public function __construct(
        protected SmkiMasterDataImport $parent,
        protected bool $isFallbackSheet = false
    ) {}

    public function collection(Collection $rows): void
    {
        if ($this->isFallbackSheet && $this->parent->controlsSheetSeen) {
            return;
        }
        if (! $this->isFallbackSheet) {
            $this->parent->controlsSheetSeen = true;
        }

        if (empty($this->parent->frameworkCache)) {
            foreach (Framework::withTrashed()->get() as $fw) {
                $this->parent->frameworkCache["{$fw->nama}|{$fw->versi}"] = $fw;
            }
        }

        // Header & column detection
        $headerRowIdx = null;
        $colMap = [];
        $topText = '';

        foreach ($rows as $idx => $row) {
            if ($idx > 12) {
                break;
            }
            $rowArr = is_array($row) ? $row : $row->toArray();
            $rowText = strtolower(implode(' ', array_filter($rowArr)));
            $topText .= ' '.$rowText;

            if ($headerRowIdx === null && (str_contains($rowText, 'klausul') || str_contains($rowText, 'kode') || str_contains($rowText, 'judul') || str_contains($rowText, 'nama'))) {
                $headerRowIdx = $idx;
                foreach ($rowArr as $cKey => $cVal) {
                    $name = strtolower(trim((string) $cVal));
                    if ($name === 'framework_nama' || str_contains($name, 'framework_nama') || ($name === 'framework')) {
                        $colMap['framework_nama'] = $cKey;
                    } elseif ($name === 'framework_versi' || str_contains($name, 'framework_versi')) {
                        $colMap['framework_versi'] = $cKey;
                    } elseif (str_contains($name, 'peran')) {
                        $colMap['domain_peran'] = $cKey;
                    } elseif (str_contains($name, 'klausul') || str_contains($name, 'kode')) {
                        $colMap['kode_klausul'] = $cKey;
                    } elseif ($name === 'judul' || str_contains($name, 'judul') || str_contains($name, 'nama')) {
                        $colMap['judul'] = $cKey;
                    } elseif (str_contains($name, 'kategori') || str_contains($name, 'domain')) {
                        $colMap['kategori'] = $cKey;
                    } elseif (str_contains($name, 'deskripsi') || str_contains($name, 'catatan')) {
                        $colMap['deskripsi'] = $cKey;
                    } elseif ($name === 'versi') {
                        $colMap['framework_versi'] = $cKey;
                    }
                }
            }
        }

        if ($headerRowIdx === null) {
            $headerRowIdx = -1;
            $colMap = [
                'kode_klausul' => 0,
                'judul' => 1,
                'kategori' => 2,
                'deskripsi' => 3,
            ];
        }

        $detectedFramework = null;
        if (str_contains($topText, '27701')) {
            $detectedFramework = $this->parent->frameworkCache['ISO/IEC 27701|2025']
                ?? $this->parent->frameworkCache['ISO 27701|2025']
                ?? Framework::where('nama', 'LIKE', '%27701%')->first();
            if (! $detectedFramework && ! $this->parent->dryRun) {
                $detectedFramework = Framework::firstOrCreate(['nama' => 'ISO/IEC 27701', 'versi' => '2025']);
            }
        } elseif (str_contains($topText, '27001')) {
            $detectedFramework = $this->parent->frameworkCache['ISO/IEC 27001|2022']
                ?? $this->parent->frameworkCache['ISO 27001|2022']
                ?? Framework::where('nama', 'LIKE', '%27001%')->first();
            if (! $detectedFramework && ! $this->parent->dryRun) {
                $detectedFramework = Framework::firstOrCreate(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
            }
        }

        $defaultFramework = null;
        $getDefaultFramework = function () use (&$defaultFramework, $detectedFramework) {
            if ($defaultFramework !== null) {
                return $defaultFramework;
            }

            $defaultFramework = $detectedFramework
                ?? $this->parent->frameworkCache['ISO/IEC 27001|2022']
                ?? $this->parent->frameworkCache['ISO 27001|2022']
                ?? Framework::first();

            if (! $defaultFramework) {
                if (! $this->parent->dryRun) {
                    $defaultFramework = Framework::firstOrCreate(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
                    $this->parent->frameworkCache['ISO/IEC 27001|2022'] = $defaultFramework;
                } else {
                    $defaultFramework = new Framework(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
                    $defaultFramework->id = 1;
                }
            }

            return $defaultFramework;
        };

        $validRows = [];
        foreach ($rows as $idx => $row) {
            if ($idx <= $headerRowIdx) {
                continue;
            }

            $rowArr = is_array($row) ? $row : $row->toArray();

            $kodeKlausul = isset($colMap['kode_klausul'])
                ? trim((string) ($rowArr[$colMap['kode_klausul']] ?? ''))
                : '';

            $judul = isset($colMap['judul'])
                ? trim((string) ($rowArr[$colMap['judul']] ?? ''))
                : '';

            if ($kodeKlausul === '' || $judul === '' || strtolower($kodeKlausul) === 'klausul' || strtolower($kodeKlausul) === 'kode') {
                continue;
            }

            $rawKategori = isset($colMap['kategori'])
                ? strtolower(trim((string) ($rowArr[$colMap['kategori']] ?? '')))
                : '';
            $rawKategori = (string) preg_replace('/\s*\(.*\)\s*/', '', $rawKategori);
            $kategori = match ($rawKategori) {
                'organisasional', 'organisational' => 'organisasional',
                'orang', 'people' => 'orang',
                'fisik', 'physical' => 'fisik',
                'teknologi', 'technology' => 'teknologi',
                'pii controller' => 'organisasional',
                'pii processor' => 'teknologi',
                default => $rawKategori,
            };

            if (! in_array($kategori, Control::KATEGORIS, true)) {
                continue;
            }

            $framework = null;
            if (isset($colMap['framework_nama'], $colMap['framework_versi'])) {
                $frameworkNama = trim((string) ($rowArr[$colMap['framework_nama']] ?? ''));
                $frameworkVersi = trim((string) ($rowArr[$colMap['framework_versi']] ?? ''));
                if ($frameworkNama !== '' && $frameworkVersi !== '') {
                    $fwCacheKey = "{$frameworkNama}|{$frameworkVersi}";
                    $framework = $this->parent->frameworkCache[$fwCacheKey] ?? null;
                }
            } else {
                $framework = $getDefaultFramework();
            }

            if (! $framework || $framework->trashed()) {
                continue;
            }

            $deskripsi = isset($colMap['deskripsi']) && isset($rowArr[$colMap['deskripsi']]) && trim((string) $rowArr[$colMap['deskripsi']]) !== ''
                ? trim((string) $rowArr[$colMap['deskripsi']])
                : null;

            $rawPeran = isset($colMap['domain_peran']) && isset($rowArr[$colMap['domain_peran']])
                ? strtolower(trim((string) $rowArr[$colMap['domain_peran']]))
                : '';
            $domainPeran = match ($rawPeran) {
                '', '-' => null,
                'controller', 'pii controller' => 'controller',
                'processor', 'pii processor' => 'processor',
                default => null,
            };

            $controlKey = "{$framework->id}|{$kodeKlausul}";
            $this->parent->seenControlKeys[] = $controlKey;

            $validRows[] = [
                'framework_id' => $framework->id,
                'framework_nama' => $framework->nama,
                'framework_versi' => $framework->versi,
                'kode_klausul' => $kodeKlausul,
                'judul' => $judul,
                'kategori' => $kategori,
                'deskripsi' => $deskripsi,
                'domain_peran' => $domainPeran,
            ];
        }

        $existingControls = Control::withoutTrashed()
            ->with('framework:id,nama,versi')
            ->get()
            ->keyBy(fn (Control $c) => "{$c->framework_id}|{$c->kode_klausul}");

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
                        'from' => Control::kategoriLabel($existing->kategori),
                        'to' => Control::kategoriLabel($data['kategori']),
                    ];
                }

                if (($existing->domain_peran ?? null) !== ($data['domain_peran'] ?? null)) {
                    $changes[] = [
                        'field' => 'Domain Peran',
                        'from' => $existing->domain_peran ?? '(kosong)',
                        'to' => $data['domain_peran'] ?? '(kosong)',
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
                            'domain_peran' => $data['domain_peran'],
                        ]);
                    }
                }
            } else {
                $this->parent->controlsCreatedDetail[] = [
                    'kode_klausul' => $data['kode_klausul'],
                    'judul' => $data['judul'],
                    'kategori' => $data['kategori'],
                    'deskripsi' => $data['deskripsi'],
                    'domain_peran' => $data['domain_peran'],
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
                        'domain_peran' => $data['domain_peran'],
                    ]);
                }
            }
        }

        if ($this->parent->frameworksSheetSeen && ! empty($this->parent->seenControlKeys)) {
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
    }
}
