<?php

namespace App\Services;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * Export real assessment data into the official
 * "Template Manajemen Kontrol ISO27001-27701" workbook (.xlsx).
 *
 * Strategy: load the pristine template with PhpSpreadsheet (charts included)
 * and fill ONLY the editable columns (G–J, L–S). Reference columns (A–F),
 * the Progres formula column (K), the Panduan sheet and the whole Dashboard
 * sheet (formulas + charts) are left untouched so the workbook stays
 * byte-identical in structure to the template.
 *
 * Column mapping (header row 4, data from row 5):
 *  G Berlaku (Ya/Tidak)      <- entry status tidak_berlaku ?
 *  H Justifikasi              <- entry.catatan_admin (only when N/A)
 *  I Status Implementasi      <- workflow mapped to template labels
 *  J Level Maturity (0-5)     <- entry.level_maturity
 *  K Progres (%)              <- NEVER written (template formula)
 *  L PIC                      <- pic name (+ unit), same as PDF builder
 *  M Bukti                    <- active evidence file name
 *  N Target Tanggal           <- latest open/in-progress finding deadline
 *  O Tanggal Selesai          <- entry.tanggal_verifikasi (when Diterapkan)
 *  P Prioritas Risiko         <- latest open finding kategori (major/minor/observasi)
 *  Q Review Terakhir          <- entry.updated_at
 *  R Review Berikutnya        <- no source in DB, left empty
 *  S Catatan                  <- entry.catatan
 *
 * The workbook carries one control sheet per framework found in the database
 * (ISO/IEC 27001 + ISO/IEC 27701), named after the framework records. The
 * three legacy 27701 scope sheets are merged into the single 27701 sheet with
 * a scope column (PII Controller / PII Processor / Shared), and the Dashboard
 * formulas are rewritten to match (COUNTIFS per scope). Charts only read
 * Dashboard cells, so they keep working untouched.
 *
 * Every sheet strictly follows the database catalogue — empty when there is
 * nothing to show. Surplus template rows are blanked and the Dashboard totals
 * (C6..C9) are synced to the real row counts.
 *
 * If the requested date range contains no checklist session, the latest
 * available sessions are used instead (Excel only; the PDF path is untouched)
 * and the fallback is recorded in the workbook document properties.
 *
 * Notes & known gaps:
 * - DB workflow 'dalam_tinjauan' has no counterpart in the template, it is
 *   exported as 'Dalam Proses' (matches the template IF formula weights).
 */
class ControlWorkbookExportService
{
    public const TEMPLATE_RELATIVE_PATH = 'templates/Manajemen_Kontrol_ISO27001-27701_v2.xlsx';

    public const TEMPLATE_SHEET_27001 = 'ISO27001-2022';

    public const TEMPLATE_SHEET_27701_BASE = 'ISO27701-A1_Controller';

    public const LEGACY_SCOPE_SHEETS = [
        'ISO27701-A2_Processor',
        'ISO27701-A3_Shared',
    ];

    public const HEADER_ROW = 4;

    public const DATA_FIRST_ROW = 5;

    public const STATUS_BELUM = 'Belum Dimulai';

    public const STATUS_PROSES = 'Dalam Proses';

    public const STATUS_DITERAPKAN = 'Diterapkan';

    public const STATUS_NA = 'Tidak Berlaku (N/A)';

    /**
     * Scope labels for the merged 27701 sheet (column C). 'shared' matches
     * controls with empty domain_peran inside the 27701 framework.
     */
    public const SCOPE_LABELS = [
        'controller' => 'PII Controller',
        'processor' => 'PII Processor',
        'shared' => 'Shared (Controller & Processor)',
    ];

    /**
     * 27001 domain terms used by column C and the Dashboard domain table.
     */
    public const DOMAIN_27001 = [
        'organisasional' => 'Organisasi',
        'orang' => 'SDM',
        'fisik' => 'Fisik',
        'teknologi' => 'Teknologi',
    ];

    protected Collection $lastDataPeriods;

    protected bool $lastFallback = false;

    public function __construct(
        protected ?ReportGeneratorService $reportService = null
    ) {
        $this->reportService = $reportService ?? app(ReportGeneratorService::class);
        $this->lastDataPeriods = collect();
    }

    /**
     * Export workbook(s). One unit_id => single .xlsx, null => ZIP with one .xlsx per unit.
     *
     * @throws AuthorizationException
     */
    public function export(
        User $user,
        ?int $unitId = null,
        ?string $periode = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): Response {
        $this->authorize($user);

        if ($unitId !== null) {
            return $this->exportSingleUnit($user, $unitId, $periode, $startDate, $endDate);
        }

        return $this->exportAllUnitsZip($user, $periode, $startDate, $endDate);
    }

    /**
     * @throws AuthorizationException
     */
    protected function authorize(User $user): void
    {
        if (Gate::forUser($user)->denies('report.export')) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengekspor laporan kepatuhan.');
        }

        if (! in_array($user->role, ['superadmin', 'admin_kepatuhan', 'koordinator_smki', 'auditor'], true)) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengekspor workbook manajemen kontrol.');
        }
    }

    protected function exportSingleUnit(
        User $user,
        int $unitId,
        ?string $periode,
        ?string $startDate,
        ?string $endDate
    ): Response {
        $this->logAudit('manajemen_kontrol_excel', $user, $unitId);

        $path = $this->buildWorkbookFile($unitId, $periode, $startDate, $endDate, $user);
        $filename = $this->singleFilename($unitId, $periode, $startDate, $endDate);

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    protected function exportAllUnitsZip(
        User $user,
        ?string $periode,
        ?string $startDate,
        ?string $endDate
    ): Response {
        $units = WorkUnit::orderBy('id')->get(['id', 'nama']);

        if ($units->isEmpty()) {
            abort(404, 'Tidak ada unit kerja yang dapat diekspor.');
        }

        $this->logAudit('manajemen_kontrol_excel_zip', $user, null);

        $tmpFiles = [];

        try {
            $zipPath = tempnam(sys_get_temp_dir(), 'smki_xlsx_zip_');
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                abort(500, 'Gagal membuat arsip ZIP untuk workbook manajemen kontrol.');
            }

            $num = 0;
            foreach ($units as $unit) {
                $num++;
                $filePath = $this->buildWorkbookFile($unit->id, $periode, $startDate, $endDate, $user);
                $tmpFiles[] = $filePath;
                $prefix = sprintf('%02d', $num);
                $zip->addFile($filePath, $prefix.'_'.$this->singleFilename($unit->id, $periode, $startDate, $endDate));
            }

            $zip->close();

            $zipFilename = $this->zipFilename($periode, $startDate, $endDate);

            return response()->download(
                $zipPath,
                $zipFilename,
                ['Content-Type' => 'application/zip']
            )->deleteFileAfterSend(true);
        } finally {
            foreach ($tmpFiles as $f) {
                @unlink($f);
            }
        }
    }

    /**
     * Build a filled workbook for one unit and return the temp file path.
     */
    public function buildWorkbookFile(
        int $unitId,
        ?string $periode = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?User $exportedBy = null
    ): string {
        $templatePath = resource_path(self::TEMPLATE_RELATIVE_PATH);

        if (! is_file($templatePath)) {
            abort(500, 'Template workbook manajemen kontrol tidak ditemukan.');
        }

        $spreadsheet = $this->loadFilledSpreadsheet($unitId, $periode, $startDate, $endDate, $exportedBy);

        $unit = WorkUnit::find($unitId);
        $description = sprintf(
            'Diekspor %s. Unit: %s. Periode data: %s.',
            now()->isoFormat('D MMMM Y HH:mm'),
            $unit?->nama ?? "Unit #{$unitId}",
            $this->lastDataPeriods->isNotEmpty() ? $this->lastDataPeriods->join(', ') : 'tidak ada sesi penilaian'
        );

        if ($this->lastFallback) {
            $description .= ' (rentang yang diminta tidak memiliki sesi; memakai sesi terakhir yang tersedia).';
        }

        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('Manajemen Kontrol SMKI - '.($unit?->nama ?? "Unit #{$unitId}"))
            ->setDescription($description);

        $tmpPath = tempnam(sys_get_temp_dir(), 'smki_xlsx_').'.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setIncludeCharts(true);
        $writer->save($tmpPath);
        $spreadsheet->disconnectWorksheets();

        return $tmpPath;
    }

    /**
     * Fill one control sheet by matching column B (ID Kontrol) against
     * checklist data keyed by kode_klausul. Blank-ID rows (cleared surplus
     * template rows) are skipped; other unmatched rows are reset to honest
     * defaults so no template sample data leaks into the export.
     */
    protected function fillControlSheet(Worksheet $sheet, array $rowsByKode): void
    {
        $lastRow = (int) $sheet->getHighestDataRow();

        for ($row = self::DATA_FIRST_ROW; $row <= $lastRow; $row++) {
            $kode = trim((string) $sheet->getCell("B{$row}")->getValue());

            if ($kode === '') {
                continue;
            }

            $data = $rowsByKode[$kode] ?? null;

            if ($data !== null) {
                $this->writeDataRow($sheet, $row, $data);

                continue;
            }

            $this->writeDefaultRow($sheet, $row);
        }
    }

    /**
     * Rewrite a sheet's reference columns (A–F) from rows of
     * [kode, colC, colD, judul, deskripsi]. Surplus template rows beyond the
     * catalogue are blanked (A–J, L–S; the K formula is kept so manual edits
     * still compute) so every sheet strictly follows the database — empty
     * when there is nothing to show.
     */
    protected function rewriteReferenceColumns(Worksheet $sheet, array $rows): void
    {
        $row = self::DATA_FIRST_ROW;

        foreach ($rows as $index => [$kode, $colC, $colD, $judul, $deskripsi]) {
            $this->ensureDataRow($sheet, $row);
            $sheet->setCellValueExplicit("A{$row}", $index + 1, DataType::TYPE_NUMERIC);
            $sheet->setCellValue("B{$row}", $kode);
            $sheet->setCellValue("C{$row}", $colC);
            $sheet->setCellValue("D{$row}", $colD);
            $sheet->setCellValue("E{$row}", $judul);
            $sheet->setCellValue("F{$row}", $deskripsi);
            $row++;
        }

        $lastRow = (int) $sheet->getHighestDataRow();

        for (; $row <= $lastRow; $row++) {
            foreach (array_merge(range('A', 'J'), range('L', 'S')) as $col) {
                $sheet->setCellValue("{$col}{$row}", null);
            }
        }
    }

    /**
     * Append catalogue controls whose kode is missing from the sheet (custom
     * controls added after the template was made). Template rows stay intact.
     */
    protected function appendMissingRows(Worksheet $sheet, array $rows): void
    {
        $present = [];
        $lastRow = (int) $sheet->getHighestDataRow();

        for ($row = self::DATA_FIRST_ROW; $row <= $lastRow; $row++) {
            $kode = trim((string) $sheet->getCell("B{$row}")->getValue());

            if ($kode !== '') {
                $present[$kode] = true;
            }
        }

        $next = $lastRow + 1;

        foreach ($rows as [$kode, $colC, $colD, $judul, $deskripsi]) {
            if (isset($present[$kode])) {
                continue;
            }

            $this->ensureDataRow($sheet, $next);
            $sheet->setCellValueExplicit("A{$next}", $this->countVisibleRows($sheet) + 1, DataType::TYPE_NUMERIC);
            $sheet->setCellValue("B{$next}", $kode);
            $sheet->setCellValue("C{$next}", $colC);
            $sheet->setCellValue("D{$next}", $colD);
            $sheet->setCellValue("E{$next}", $judul);
            $sheet->setCellValue("F{$next}", $deskripsi);
            $present[$kode] = true;
            $next++;
        }
    }

    /**
     * Count visible data rows (non-blank column B) on a control sheet.
     */
    protected function countVisibleRows(Worksheet $sheet): int
    {
        $count = 0;
        $lastRow = (int) $sheet->getHighestDataRow();

        for ($row = self::DATA_FIRST_ROW; $row <= $lastRow; $row++) {
            if (trim((string) $sheet->getCell("B{$row}")->getValue()) !== '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Grow the sheet when the catalogue outgrows the template rows, cloning
     * the style and Progres formula pattern of the last template data row.
     */
    protected function ensureDataRow(Worksheet $sheet, int $row): void
    {
        $lastRow = (int) $sheet->getHighestDataRow();

        if ($row <= $lastRow) {
            return;
        }

        $sheet->insertNewRowBefore($lastRow + 1, $row - $lastRow);

        for ($r = $lastRow + 1; $r <= $row; $r++) {
            foreach (range('A', 'S') as $col) {
                $sheet->duplicateStyle($sheet->getStyle("{$col}{$lastRow}"), "{$col}{$r}");
            }
            $sheet->setCellValue(
                "K{$r}",
                "=IF(OR(I{$r}=\"Diterapkan\",I{$r}=\"Tidak Berlaku (N/A)\"),1,IF(I{$r}=\"Dalam Proses\",0.5,0))"
            );
        }
    }

    protected function frameworkByPattern(string $pattern): ?Framework
    {
        return Framework::where('nama', 'like', "%{$pattern}%")->orderBy('id')->first();
    }

    /**
     * Excel-safe sheet title derived from the framework record.
     */
    protected function sheetNameFor(?Framework $framework, string $fallback, array $taken = []): string
    {
        $name = trim((string) ($framework?->nama ?? $fallback));
        $name = str_replace(['/', '\\', '?', '*', '[', ']', ':'], ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = mb_substr(trim($name), 0, 31) ?: $fallback;

        $candidate = $name;
        $suffix = 2;

        while (in_array($candidate, $taken, true)) {
            $candidate = mb_substr($name, 0, 28)." ({$suffix})";
            $suffix++;
        }

        return $candidate;
    }

    protected function catalogue27001(?int $frameworkId): Collection
    {
        if ($frameworkId === null) {
            return collect();
        }

        return Control::where('framework_id', $frameworkId)
            ->whereNull('deleted_at')
            ->orderBy('kode_klausul')
            ->get(['id', 'kode_klausul', 'judul', 'kategori', 'deskripsi']);
    }

    /**
     * 27701 catalogue grouped by scope (controller, processor, shared) in
     * dashboard order. 'shared' matches empty domain_peran.
     */
    protected function catalogue27701(?int $frameworkId): array
    {
        $grouped = ['controller' => collect(), 'processor' => collect(), 'shared' => collect()];

        if ($frameworkId === null) {
            return $grouped;
        }

        $controls = Control::where('framework_id', $frameworkId)
            ->whereNull('deleted_at')
            ->orderBy('kode_klausul')
            ->get(['id', 'kode_klausul', 'judul', 'kategori', 'deskripsi', 'domain_peran']);

        foreach ($controls as $control) {
            $scope = match ($control->domain_peran) {
                'controller' => 'controller',
                'processor' => 'processor',
                default => 'shared',
            };
            $grouped[$scope]->push($control);
        }

        return $grouped;
    }

    protected function writeDataRow(Worksheet $sheet, int $row, array $d): void
    {
        $sheet->setCellValue("G{$row}", $d['berlaku']);
        $sheet->setCellValue("H{$row}", $d['justifikasi']);
        $sheet->setCellValue("I{$row}", $d['status_label']);

        if ($d['maturity'] === null) {
            $sheet->setCellValue("J{$row}", null);
        } else {
            $sheet->setCellValueExplicit("J{$row}", (int) $d['maturity'], DataType::TYPE_NUMERIC);
        }

        // Column K (Progres %) intentionally untouched: template formula derives it from column I.
        $sheet->setCellValue("L{$row}", $d['pic']);
        $sheet->setCellValue("M{$row}", $d['bukti']);
        $sheet->setCellValue("N{$row}", $this->toExcelDate($d['target_tanggal']));
        $sheet->setCellValue("O{$row}", $this->toExcelDate($d['tanggal_selesai']));
        $sheet->setCellValue("P{$row}", $d['prioritas']);
        $sheet->setCellValue("Q{$row}", $this->toExcelDate($d['review_terakhir']));
        $sheet->setCellValue("R{$row}", null);
        $sheet->setCellValue("S{$row}", $d['catatan']);
    }

    protected function writeDefaultRow(Worksheet $sheet, int $row): void
    {
        $sheet->setCellValue("G{$row}", 'Ya');
        $sheet->setCellValue("H{$row}", null);
        $sheet->setCellValue("I{$row}", self::STATUS_BELUM);
        $sheet->setCellValue("J{$row}", null);
        // Column K untouched (formula).
        $sheet->setCellValue("L{$row}", null);
        $sheet->setCellValue("M{$row}", null);
        $sheet->setCellValue("N{$row}", null);
        $sheet->setCellValue("O{$row}", null);
        $sheet->setCellValue("P{$row}", null);
        $sheet->setCellValue("Q{$row}", null);
        $sheet->setCellValue("R{$row}", null);
        $sheet->setCellValue("S{$row}", null);
    }

    /**
     * Gather latest checklist state per control for one unit, keyed by kode_klausul.
     */
    protected function resolveControlRows(int $unitId, Collection $sessionIds): array
    {
        $query = \DB::table('checklist_entries')
            ->join('controls', 'checklist_entries.control_id', '=', 'controls.id')
            ->leftJoin('users as pic_user', 'checklist_entries.pic_id', '=', 'pic_user.id')
            ->leftJoin('work_units as pic_unit', 'pic_user.unit_id', '=', 'pic_unit.id')
            ->select(
                'controls.id as control_id',
                'controls.kode_klausul',
                'checklist_entries.id as entry_id',
                'checklist_entries.status',
                'checklist_entries.level_maturity',
                'checklist_entries.catatan',
                'checklist_entries.catatan_admin',
                'checklist_entries.tanggal_verifikasi',
                'checklist_entries.updated_at',
                'pic_user.name as pic_name',
                'pic_unit.nama as pic_unit_name'
            )
            ->where('checklist_entries.unit_id', $unitId)
            ->whereNull('checklist_entries.deleted_at')
            ->whereNull('controls.deleted_at');

        if ($sessionIds->isNotEmpty()) {
            $query->whereIn('checklist_entries.session_id', $sessionIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        $entries = $query->orderBy('checklist_entries.updated_at', 'desc')->get();

        $latestByControl = [];
        $entryIds = [];

        foreach ($entries as $e) {
            if (! isset($latestByControl[$e->control_id])) {
                $latestByControl[$e->control_id] = $e;
                $entryIds[] = $e->entry_id;
            }
        }

        $findingsByControl = Finding::where('unit_id', $unitId)
            ->whereIn('control_id', array_keys($latestByControl))
            ->whereIn('status', [Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS])
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('control_id')
            ->map(fn ($g) => $g->first());

        $evidenceByEntry = ComplianceEvidence::whereIn('checklist_entry_id', $entryIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('checklist_entry_id');

        $rows = [];

        foreach ($latestByControl as $controlId => $e) {
            $isNa = $e->status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU;
            $finding = $findingsByControl->get($controlId);

            $pic = '-';
            if (! empty($e->pic_name)) {
                $pic = $e->pic_name.($e->pic_unit_name ? " ({$e->pic_unit_name})" : '');
            }

            $rows[$e->kode_klausul] = [
                'berlaku' => $isNa ? 'Tidak' : 'Ya',
                'justifikasi' => $isNa ? (string) ($e->catatan_admin ?? '') : '',
                'status_label' => $this->mapStatus($e->status),
                'maturity' => $e->level_maturity !== null ? (int) $e->level_maturity : null,
                'pic' => $e->pic_name ? $pic : '',
                'bukti' => isset($evidenceByEntry[$e->entry_id])
                    ? (string) $evidenceByEntry[$e->entry_id]->nama_file
                    : '',
                'target_tanggal' => $finding?->deadline,
                'tanggal_selesai' => $e->status === ChecklistEntry::WORKFLOW_SELESAI && $e->tanggal_verifikasi
                    ? $e->tanggal_verifikasi
                    : null,
                'prioritas' => $finding ? $this->mapPrioritas($finding->kategori) : '',
                'review_terakhir' => $e->updated_at,
                'catatan' => (string) ($e->catatan ?? ''),
            ];
        }

        return $rows;
    }

    protected function mapStatus(?string $status): string
    {
        return match ($status) {
            ChecklistEntry::WORKFLOW_SELESAI => self::STATUS_DITERAPKAN,
            // The template has no 'Dalam Tinjauan' option; export it as
            // 'Dalam Proses' so the sheet formulas keep working.
            ChecklistEntry::WORKFLOW_DALAM_PROSES,
            ChecklistEntry::WORKFLOW_DALAM_TINJAUAN => self::STATUS_PROSES,
            ChecklistEntry::WORKFLOW_TIDAK_BERLAKU => self::STATUS_NA,
            default => self::STATUS_BELUM,
        };
    }

    protected function mapPrioritas(?string $kategori): string
    {
        return match ($kategori) {
            Finding::KATEGORI_MAJOR => 'Tinggi',
            Finding::KATEGORI_MINOR => 'Sedang',
            Finding::KATEGORI_OBSERVASI => 'Rendah',
            default => '',
        };
    }

    protected function toExcelDate(mixed $value): mixed
    {
        if (empty($value)) {
            return null;
        }

        try {
            $dt = $value instanceof \DateTimeInterface ? $value : Carbon::parse($value);

            return ExcelDate::PHPToExcel($dt);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function singleFilename(?int $unitId, ?string $periode, ?string $startDate, ?string $endDate): string
    {
        return sprintf(
            'Manajemen_Kontrol_SMKI_%s_%s.xlsx',
            $this->unitSlug($unitId),
            $this->periodLabel($periode, $startDate, $endDate)
        );
    }

    protected function zipFilename(?string $periode, ?string $startDate, ?string $endDate): string
    {
        return sprintf(
            'Manajemen_Kontrol_SMKI_Semua_Unit_%s.zip',
            $this->periodLabel($periode, $startDate, $endDate)
        );
    }

    protected function unitSlug(?int $unitId): string
    {
        if ($unitId) {
            $unit = WorkUnit::find($unitId);

            if ($unit && ! empty($unit->nama)) {
                return Str::slug($unit->nama, '_');
            }

            return 'Unit_'.$unitId;
        }

        return 'Semua_Unit';
    }

    protected function periodLabel(?string $periode, ?string $startDate, ?string $endDate): string
    {
        try {
            if ($startDate && $endDate) {
                $s = Carbon::parse($startDate)->isoFormat('MMMM_Y');
                $e = Carbon::parse($endDate)->isoFormat('MMMM_Y');

                return $s === $e ? $s : "{$s}-{$e}";
            }

            if ($periode) {
                return Carbon::parse($periode.'-01')->isoFormat('MMMM_Y');
            }
        } catch (\Throwable) {
            // fallback below
        }

        return now()->isoFormat('MMMM_Y');
    }

    protected function logAudit(string $action, User $user, ?int $unitId): void
    {
        Log::info('report.export', [
            'action' => $action,
            'user_id' => $user->id,
            'report_type' => 'manajemen-kontrol-excel',
            'unit_id' => $unitId,
        ]);
    }

    /**
     * Load the pristine template and fill it for one unit.
     * Used by both the download builder and the test suite.
     */
    public function spreadsheetForUnit(
        int $unitId,
        ?string $periode = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?User $exportedBy = null
    ): Spreadsheet {
        return $this->loadFilledSpreadsheet($unitId, $periode, $startDate, $endDate, $exportedBy);
    }

    protected function loadFilledSpreadsheet(
        int $unitId,
        ?string $periode,
        ?string $startDate,
        ?string $endDate,
        ?User $exportedBy = null
    ): Spreadsheet {
        $templatePath = resource_path(self::TEMPLATE_RELATIVE_PATH);

        if (! is_file($templatePath)) {
            abort(500, 'Template workbook manajemen kontrol tidak ditemukan.');
        }

        $reader = new XlsxReader;
        $reader->setIncludeCharts(true);
        $spreadsheet = $reader->load($templatePath);

        $sessionIds = $this->reportService->resolveLatestSessionIds($unitId, $periode, $startDate, $endDate);

        $this->lastFallback = false;

        if ($sessionIds->isEmpty() && ($periode !== null || $startDate !== null || $endDate !== null)) {
            $sessionIds = $this->reportService->resolveLatestSessionIds($unitId, null, null, null);
            $this->lastFallback = $sessionIds->isNotEmpty();
        }

        $this->lastDataPeriods = $sessionIds->isNotEmpty()
            ? ChecklistSession::whereIn('id', $sessionIds)->distinct()->orderBy('periode')->pluck('periode')
            : collect();

        $rowsByKode = $this->resolveControlRows($unitId, $sessionIds);

        $fw27001 = $this->frameworkByPattern('27001');
        $fw27701 = $this->frameworkByPattern('27701');

        $name27001 = $this->sheetNameFor($fw27001, 'ISO IEC 27001');
        $name27701 = $this->sheetNameFor($fw27701, 'ISO IEC 27701', [$name27001]);

        foreach (self::LEGACY_SCOPE_SHEETS as $legacy) {
            $legacySheet = $spreadsheet->getSheetByName($legacy);

            if ($legacySheet !== null) {
                $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($legacySheet));
            }
        }

        $sheet27001 = $spreadsheet->getSheetByName(self::TEMPLATE_SHEET_27001);
        $sheet27701 = $spreadsheet->getSheetByName(self::TEMPLATE_SHEET_27701_BASE);

        if ($sheet27001 === null || $sheet27701 === null) {
            abort(500, 'Struktur template workbook manajemen kontrol tidak dikenali.');
        }

        $sheet27001->setTitle($name27001);
        $sheet27701->setTitle($name27701);
        $spreadsheet->setActiveSheetIndex(0);

        // 27001: template rows stay intact (1:1 match); only custom controls
        // missing from the template are appended from the database.
        $controls27001 = $this->catalogue27001($fw27001?->id);
        $this->fillControlSheet($sheet27001, $rowsByKode);
        $this->appendMissingRows($sheet27001, $controls27001->map(
            fn ($c) => [$c->kode_klausul, self::DOMAIN_27001[$c->kategori] ?? '', '', $c->judul, $c->deskripsi ?? '']
        )->all());
        $this->fillControlSheet($sheet27001, $rowsByKode);

        // 27701: merged scope sheet fully rewritten from the database in
        // controller → processor → shared order (dashboard rows 7/8/9).
        $grouped27701 = $this->catalogue27701($fw27701?->id);
        $merged = [];

        foreach (['controller', 'processor', 'shared'] as $scope) {
            foreach ($grouped27701[$scope] as $control) {
                $merged[] = [
                    $control->kode_klausul,
                    self::SCOPE_LABELS[$scope],
                    Control::kategoriLabel((string) ($control->kategori ?? '')),
                    $control->judul,
                    $control->deskripsi ?? '',
                ];
            }
        }

        $this->rewriteReferenceColumns($sheet27701, $merged);
        $this->fillControlSheet($sheet27701, $rowsByKode);

        $this->rewriteDashboard(
            $spreadsheet,
            $name27001,
            $this->countVisibleRows($sheet27001),
            $name27701,
            $grouped27701
        );
        $this->updatePanduanStructure(
            $spreadsheet,
            $name27001,
            $this->countVisibleRows($sheet27001),
            $name27701,
            $grouped27701['controller']->count() + $grouped27701['processor']->count() + $grouped27701['shared']->count()
        );
        $this->stampPanduanSheet($spreadsheet, $unitId, $exportedBy);

        return $spreadsheet;
    }

    /**
     * Rewrite Dashboard cross-sheet formulas for the two framework sheets.
     * Scope rows (7/8/9) use COUNTIFS on the merged 27701 sheet's scope
     * column. Charts only read Dashboard cells, so they keep working.
     */
    protected function rewriteDashboard(
        Spreadsheet $spreadsheet,
        string $name27001,
        int $total27001,
        string $name27701,
        array $grouped27701
    ): void {
        $dashboard = $spreadsheet->getSheetByName('Dashboard');

        if ($dashboard === null) {
            return;
        }

        $q1 = "'{$name27001}'";
        $q2 = "'{$name27701}'";
        $last1 = max(self::DATA_FIRST_ROW, self::DATA_FIRST_ROW + $total27001 - 1);
        $counts = [
            $total27001,
            $grouped27701['controller']->count(),
            $grouped27701['processor']->count(),
            $grouped27701['shared']->count(),
        ];

        foreach ($counts as $index => $total) {
            $dashboard->setCellValueExplicit('C'.(6 + $index), (int) $total, DataType::TYPE_NUMERIC);
        }

        $statuses = [
            'D' => ['Ya', 'G'],
            'E' => ['Diterapkan', 'I'],
            'F' => ['Dalam Proses', 'I'],
            'G' => ['Belum Dimulai', 'I'],
            'H' => ['Tidak Berlaku (N/A)', 'I'],
        ];

        foreach ($statuses as $col => [$label, $srcCol]) {
            $dashboard->setCellValue("{$col}6", "=COUNTIF({$q1}!\${$srcCol}\$5:\${$srcCol}\${$last1},\"{$label}\")");
        }

        $last2 = max(self::DATA_FIRST_ROW, self::DATA_FIRST_ROW + array_sum($counts) - $counts[0] - 1);

        foreach (['controller' => 7, 'processor' => 8, 'shared' => 9] as $scope => $dashRow) {
            $scopeLabel = self::SCOPE_LABELS[$scope];
            $dashboard->setCellValue("D{$dashRow}", "=COUNTIFS({$q2}!\$G\$5:\$G\${$last2},\"Ya\",{$q2}!\$C\$5:\$C\${$last2},\"{$scopeLabel}\")");
            $dashboard->setCellValue("E{$dashRow}", "=COUNTIFS({$q2}!\$I\$5:\$I\${$last2},\"Diterapkan\",{$q2}!\$C\$5:\$C\${$last2},\"{$scopeLabel}\")");
            $dashboard->setCellValue("F{$dashRow}", "=COUNTIFS({$q2}!\$I\$5:\$I\${$last2},\"Dalam Proses\",{$q2}!\$C\$5:\$C\${$last2},\"{$scopeLabel}\")");
            $dashboard->setCellValue("G{$dashRow}", "=COUNTIFS({$q2}!\$I\$5:\$I\${$last2},\"Belum Dimulai\",{$q2}!\$C\$5:\$C\${$last2},\"{$scopeLabel}\")");
            $dashboard->setCellValue("H{$dashRow}", "=COUNTIFS({$q2}!\$I\$5:\$I\${$last2},\"Tidak Berlaku (N/A)\",{$q2}!\$C\$5:\$C\${$last2},\"{$scopeLabel}\")");
        }

        foreach (array_values(self::DOMAIN_27001) as $offset => $domain) {
            $dashRow = 54 + $offset;
            $dashboard->setCellValue("C{$dashRow}", "=COUNTIF({$q1}!\$C\$5:\$C\${$last1},\"{$domain}\")");
            $dashboard->setCellValue("D{$dashRow}", "=COUNTIFS({$q1}!\$C\$5:\$C\${$last1},\"{$domain}\",{$q1}!\$I\$5:\$I\${$last1},\"Diterapkan\")");
            $dashboard->setCellValue("E{$dashRow}", "=COUNTIFS({$q1}!\$C\$5:\$C\${$last1},\"{$domain}\",{$q1}!\$I\$5:\$I\${$last1},\"Dalam Proses\")");
            $dashboard->setCellValue("F{$dashRow}", "=COUNTIFS({$q1}!\$C\$5:\$C\${$last1},\"{$domain}\",{$q1}!\$I\$5:\$I\${$last1},\"Belum Dimulai\")");
        }
    }

    /**
     * Update the Panduan purpose/structure rows with the real sheet names and
     * catalogue counts. All other Panduan rows stay byte-identical.
     */
    protected function updatePanduanStructure(
        Spreadsheet $spreadsheet,
        string $name27001,
        int $total27001,
        string $name27701,
        int $total27701
    ): void {
        $sheet = $spreadsheet->getSheetByName('Panduan');

        if ($sheet === null) {
            return;
        }

        $sheet->setCellValue(
            'C4',
            "Workbook ini adalah data master (single source of truth) status implementasi kontrol {$name27001} (Annex A, {$total27001} kontrol) dan {$name27701} ({$total27701} kontrol). Setiap baris pada sheet kontrol adalah satu record yang dapat langsung diimpor (CSV/Excel import) ke aplikasi/GRC-tool untuk membangkitkan laporan monitoring progres."
        );
        $sheet->setCellValue(
            'C5',
            "1) Panduan — halaman ini.  2) {$name27001} — {$total27001} kontrol.  3) {$name27701} — {$total27701} kontrol.  4) Dashboard — ringkasan & grafik progres otomatis (formula, tidak perlu diisi manual)."
        );
    }

    /**
     * Insert a per-file "Informasi Berkas Export" block into the Panduan sheet
     * so every workbook (including each file inside a ZIP) identifies its unit,
     * data period, export date and exporter. Template rows (incl. Dibuat/Versi)
     * are shifted down untouched, never overwritten.
     */
    protected function stampPanduanSheet(Spreadsheet $spreadsheet, int $unitId, ?User $exportedBy = null): void
    {
        $sheet = $spreadsheet->getSheetByName('Panduan');

        if ($sheet === null) {
            return;
        }

        $unit = WorkUnit::find($unitId);
        $periods = $this->lastDataPeriods->isNotEmpty()
            ? $this->lastDataPeriods->join(', ')
            : 'tidak ada sesi penilaian';

        if ($this->lastFallback) {
            $periods .= ' (sesi terakhir yang tersedia)';
        }

        $rows = [
            ['Informasi Berkas Export', null],
            ['Unit Kerja', $unit?->nama ?? "Unit #{$unitId}"],
            ['Periode Data', $periods],
            ['Tanggal Export', now()->isoFormat('D MMMM Y')],
            ['Diekspor Oleh', $exportedBy ? "{$exportedBy->name} ({$exportedBy->role})" : '-'],
        ];

        $at = 16;
        $sheet->insertNewRowBefore($at, count($rows));

        // Title reuses the shifted legend-title style; entries reuse the B14/C14 styles.
        $sheet->setCellValue("A{$at}", $rows[0][0]);
        $sheet->duplicateStyle($sheet->getStyle('A'.($at + count($rows))), "A{$at}");

        for ($i = 1; $i < count($rows); $i++) {
            $r = $at + $i;
            $sheet->setCellValue("B{$r}", $rows[$i][0]);
            $sheet->setCellValue("C{$r}", $rows[$i][1]);
            $sheet->duplicateStyle($sheet->getStyle('B14'), "B{$r}");
            $sheet->duplicateStyle($sheet->getStyle('C14'), "C{$r}");
            $sheet->getStyle("C{$r}")->getAlignment()->setWrapText(true);
        }
    }
}
