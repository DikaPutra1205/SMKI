<?php

namespace App\Services;

use App\Exceptions\ReportNotImplementedException;
use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\Finding;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ReportGeneratorService
{
    public function __construct(
        protected ?DashboardAnalyticsService $analyticsService = null
    ) {
        $this->analyticsService = $analyticsService ?? app(DashboardAnalyticsService::class);
    }

    /**
     * Generate laporan PDF sesuai tipe yang diminta.
     *
     * @param  string  $reportType  'quick-summary' | 'executive' | 'audit-ready'
     *
     * @throws ReportNotImplementedException|AuthorizationException
     */
    public function export(
        string $reportType,
        User $user,
        ?int $unitId = null,
        ?string $periode = null,
        ?string $startDate = null,
        ?string $endDate = null,
        string $printMode = 'latest'
    ): \Symfony\Component\HttpFoundation\Response {
        // Enforce RBAC per role
        $role = $user->role;
        $allowedReports = match ($role) {
            'superadmin' => ['quick-summary', 'executive', 'audit-ready'],
            'admin_kepatuhan' => ['quick-summary'],
            'koordinator_smki' => ['executive'],
            'auditor' => ['quick-summary', 'executive'],
            default => [],
        };

        if (! in_array($reportType, $allowedReports, true)) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengekspor laporan tipe ini.');
        }

        $config = $this->resolveConfig($reportType);

        // Force header_view to null for executive and quick-summary reports to bypass any config caching issues
        if (in_array($reportType, ['executive', 'quick-summary'], true)) {
            $config['header_view'] = null;
        }

        $unitSlug = $this->getReportUnitName($unitId);
        $typePrefix = $this->getReportTypePrefix($reportType);
        $months = $this->resolveMonthsList($periode, $startDate, $endDate);

        if ($printMode === 'per_month' && count($months) > 1) {
            return $this->exportMultiMonthZip($reportType, $user, $unitId, $months, $config, $typePrefix, $unitSlug);
        }

        $effectivePeriod = ($printMode === 'per_month' && count($months) === 1) ? $months[0] : $periode;

        return $this->exportSinglePdf($reportType, $user, $unitId, $effectivePeriod, $startDate, $endDate, $config, $typePrefix, $unitSlug);
    }

    protected function exportMultiMonthZip(
        string $reportType,
        User $user,
        ?int $unitId,
        array $months,
        array $config,
        string $typePrefix,
        string $unitSlug
    ): \Symfony\Component\HttpFoundation\Response {
        $this->logAudit($config['audit_action'].'_zip', $user, $reportType, $unitId);

        $firstMonth = Carbon::parse($months[0].'-01');
        $lastMonth = Carbon::parse(end($months).'-01');

        $rangeLabel = $firstMonth->format('Y') === $lastMonth->format('Y')
            ? $firstMonth->isoFormat('MMMM').'-'.$lastMonth->isoFormat('MMMM_Y')
            : $firstMonth->isoFormat('MMMM_Y').'-'.$lastMonth->isoFormat('MMMM_Y');

        $zipFilename = "{$typePrefix}_{$unitSlug}_{$rangeLabel}.zip";
        $tempZipPath = tempnam(sys_get_temp_dir(), 'smki_zip_');

        $zip = new ZipArchive;
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Gagal membuat arsip ZIP untuk laporan kepatuhan.');
        }

        foreach ($months as $idx => $m) {
            $mDt = Carbon::parse($m.'-01');
            $num = sprintf('%02d', $idx + 1);
            $monthLabel = $mDt->isoFormat('MMMM_Y');
            $pdfNameInZip = "{$num}_{$typePrefix}_{$unitSlug}_{$monthLabel}.pdf";

            $singleData = match ($reportType) {
                'quick-summary' => $this->buildQuickSummaryData($unitId, $m, null, null),
                'executive' => $this->buildExecutiveData($unitId, $m, null, null),
                default => [],
            };

            $pdfInstance = $this->createPdfInstance($config, $singleData);
            $pdfContent = $pdfInstance->generatePdfContent();

            $zip->addFromString($pdfNameInZip, $pdfContent);
        }

        $zip->close();

        $zipContent = file_get_contents($tempZipPath);
        @unlink($tempZipPath);

        return response($zipContent, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$zipFilename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    protected function exportSinglePdf(
        string $reportType,
        User $user,
        ?int $unitId,
        ?string $periode,
        ?string $startDate,
        ?string $endDate,
        array $config,
        string $typePrefix,
        string $unitSlug
    ): \Symfony\Component\HttpFoundation\Response {
        $data = match ($reportType) {
            'quick-summary' => $this->buildQuickSummaryData($unitId, $periode, $startDate, $endDate),
            'executive' => $this->buildExecutiveData($unitId, $periode, $startDate, $endDate),
            default => [],
        };

        $this->logAudit($config['audit_action'], $user, $reportType, $unitId);

        $periodLabel = $periode
            ? Carbon::parse($periode.'-01')->isoFormat('MMMM_Y')
            : ($startDate && $endDate
                ? Carbon::parse($startDate)->isoFormat('MMMM_Y')
                : now()->isoFormat('MMMM_Y'));

        $filename = "{$typePrefix}_{$unitSlug}_{$periodLabel}.pdf";

        $pdf = $this->createPdfInstance($config, $data);
        $response = $pdf->inline($filename)->toResponse(request());

        if (empty($response->headers->get('Content-Type')) || str_starts_with($response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'inline; filename="'.$filename.'"');
        }

        // Prevent browser caching for generated PDFs
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Cache-Control', 'post-check=0, pre-check=0', false);
        $response->headers->set('Pragma', 'no-cache');

        if (app()->runningUnitTests() && empty($response->getContent())) {
            $response->setContent($pdf->generatePdfContent());
        }

        return $response;
    }

    protected function createPdfInstance(array $config, array $data): PdfBuilder
    {
        $pdf = Pdf::view($config['view'], $data)
            ->format($config['paper'] ?? 'a4')
            ->portrait();

        $chromePath = $this->resolveChromePath();
        if ($chromePath) {
            $pdf->withBrowsershot(function ($browsershot) use ($chromePath) {
                $browsershot->setChromePath($chromePath)
                    ->noSandbox()
                    ->timeout(120)
                    ->setOption('args', ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox']);
            });
        }

        if (! empty($config['margins'])) {
            $pdf->margins(
                $config['margins']['top'] ?? 15,
                $config['margins']['right'] ?? 12,
                $config['margins']['bottom'] ?? 15,
                $config['margins']['left'] ?? 12,
                $config['margins']['unit'] ?? 'mm'
            );
        }

        if (! empty($config['header_view'])) {
            $pdf->headerView($config['header_view'], $data);
        }

        if (! empty($config['footer_view'])) {
            $pdf->footerView($config['footer_view'], $data);
        }

        return $pdf;
    }

    protected function resolveMonthsList(?string $periode, ?string $startDate, ?string $endDate): array
    {
        $months = [];
        if ($startDate && $endDate) {
            try {
                $start = Carbon::parse($startDate)->startOfMonth();
                $end = Carbon::parse($endDate)->startOfMonth();
                while ($start->lte($end)) {
                    $months[] = $start->format('Y-m');
                    $start->addMonth();
                }
            } catch (\Exception) {
                // fallback
            }
        } elseif ($periode) {
            $months[] = $periode;
        }

        if (empty($months)) {
            $months[] = now()->format('Y-m');
        }

        return $months;
    }

    protected function getReportTypePrefix(string $reportType): string
    {
        return match ($reportType) {
            'executive' => 'Laporan_Eksekutif_SMKI',
            'quick-summary' => 'Laporan_Progres_Kepatuhan_SMKI',
            'audit-ready' => 'Laporan_Audit_SMKI',
            default => 'Laporan_SMKI',
        };
    }

    protected function getReportUnitName(?int $unitId): string
    {
        if ($unitId) {
            $unit = WorkUnit::find($unitId);
            if ($unit && ! empty($unit->nama)) {
                return Str::slug($unit->nama, '_');
            }

            return 'Unit_'.$unitId;
        }

        return 'Kementerian_Komdigi';
    }

    /**
     * Get available monthly reporting periods (last 12 months up to current month).
     */
    public function getAvailablePeriods(): array
    {
        $periods = [];
        $current = Carbon::now()->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $dt = $current->copy()->subMonths($i);
            $periods[] = [
                'value' => $dt->format('Y-m'),
                'label' => $dt->isoFormat('MMMM Y'),
                'year' => (int) $dt->format('Y'),
                'month' => (int) $dt->format('n'),
                'start_date' => $dt->copy()->startOfMonth()->format('Y-m-d'),
                'end_date' => $dt->copy()->endOfMonth()->format('Y-m-d'),
            ];
        }

        return $periods;
    }

    /**
     * Get structured compliance report summary data for API.
     */
    public function getComplianceReportData(User $user, ?int $unitId = null): array
    {
        if (Gate::forUser($user)->denies('report.export')) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengekspor laporan kepatuhan.');
        }

        $scopedUnitIds = $this->analyticsService->resolveScopedUnitIds($user, array_merge($unitId !== null ? ['unit_id' => $unitId] : []));

        $summary = $this->analyticsService->getSummary($user, $unitId);
        $unitComparisons = $this->analyticsService->getUnitComparisons($user);

        $findingsQuery = Finding::with(['control', 'unit']);
        $risksQuery = Risk::with(['controls']);

        if ($scopedUnitIds !== null) {
            $findingsQuery->whereIn('unit_id', $scopedUnitIds);
            $risksQuery->whereHas('controls.checklistEntries', fn ($q) => $q->whereIn('unit_id', $scopedUnitIds));
        }

        $openFindings = $findingsQuery->whereIn('status', [Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS])->count();
        $totalRisks = $risksQuery->count();
        $highRisks = $risksQuery->whereIn('level_risiko', [Risk::LEVEL_HIGH, Risk::LEVEL_CRITICAL])->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'generated_by' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
            'scoped_unit' => $scopedUnitIds !== null
                ? (count($scopedUnitIds) === 1 ? WorkUnit::find($scopedUnitIds[0])?->nama ?? 'Unit Kerja Terpilih' : 'Unit Kerja Terpilih')
                : 'Semua Unit Kerja',
            'summary' => $summary,
            'unit_metrics' => $unitComparisons,
            'audit_metrics' => [
                'open_findings' => $openFindings,
                'total_risks' => $totalRisks,
                'critical_high_risks' => $highRisks,
            ],
        ];
    }

    /**
     * Export compliance summary PDF for API.
     */
    public function exportComplianceSummaryPdf(User $user, ?int $unitId = null): Response
    {
        if (Gate::forUser($user)->denies('report.export')) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengekspor laporan kepatuhan.');
        }

        $scopedUnitIds = $this->analyticsService->resolveScopedUnitIds($user, array_merge($unitId !== null ? ['unit_id' => $unitId] : []));
        $data = $this->analyticsService->getSummary($user, $unitId);
        $unitName = $scopedUnitIds !== null ? 'Unit Kerja' : 'Seluruh Satuan Unit Kerja (Komdigi)';
        $overallRate = $data['overall_completion_rate'] ?? 0;
        $findings = $data['findings'] ?? [];
        $risks = $data['risks'] ?? [];
        $generatedAt = now()->isoFormat('D MMMM Y, HH:mm [WIB]');
        $generatedBy = "{$user->name} ({$user->role})";

        AuditLog::catat(
            'Report',
            0,
            'export',
            $user->id,
            [
                'report_type' => 'compliance_summary_pdf',
                'scoped_unit_id' => count($scopedUnitIds) === 1 ? $scopedUnitIds[0] : $scopedUnitIds,
                'exported_at' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
            ]
        );

        $frameworkRows = '';
        foreach ($data['frameworks_breakdown'] ?? [] as $fw) {
            $name = htmlspecialchars($fw['nama'] ?? '-');
            $total = (int) ($fw['total_controls'] ?? 0);
            $compliant = (int) ($fw['selesai_count'] ?? 0);
            $partial = (int) ($fw['proses_count'] ?? 0);
            $nonCompliant = (int) ($fw['tinjauan_count'] ?? 0);
            $rate = (float) ($fw['completion_rate'] ?? 0);

            $frameworkRows .= "<tr>
                <td style='padding: 8px 12px; border: 1px solid #cbd5e1;'>{$name}</td>
                <td style='padding: 8px 12px; text-align: center; border: 1px solid #cbd5e1;'>{$total}</td>
                <td style='padding: 8px 12px; text-align: center; border: 1px solid #cbd5e1; color: #16a34a; font-weight: 600;'>{$compliant}</td>
                <td style='padding: 8px 12px; text-align: center; border: 1px solid #cbd5e1; color: #d97706;'>{$partial}</td>
                <td style='padding: 8px 12px; text-align: center; border: 1px solid #cbd5e1; color: #dc2626;'>{$nonCompliant}</td>
                <td style='padding: 8px 12px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold;'>{$rate}%</td>
            </tr>";
        }

        $html = "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <title>Laporan Kepatuhan SMKI - {$unitName}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; line-height: 1.5; font-size: 13px; margin: 0; padding: 20px; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; color: #0f172a; margin: 0; }
        .subtitle { font-size: 13px; color: #64748b; margin-top: 4px; }
        .badge-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .stat-item { text-align: center; flex: 1; }
        .stat-value { font-size: 22px; font-weight: bold; color: #0f172a; }
        .stat-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        th { background: #f1f5f9; color: #334155; font-weight: 600; text-align: left; padding: 8px 12px; border: 1px solid #cbd5e1; font-size: 12px; }
        .section-title { font-size: 15px; font-weight: bold; color: #0f172a; margin-top: 20px; margin-bottom: 8px; border-left: 4px solid #2563eb; padding-left: 8px; }
        .footer { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 11px; color: #94a3b8; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div class='header'>
        <div class='title'>KEMENTERIAN KOMUNIKASI DAN DIGITAL RI</div>
        <div class='subtitle'>Sistem Kepatuhan Digital SMKI (ISO 27001 &amp; ISO 27701)</div>
        <div style='margin-top: 8px; font-size: 14px; font-weight: 600; color: #2563eb;'>LAPORAN AUDIT &amp; KEPATUHAN EKSEKUTIF</div>
    </div>

    <div class='badge-box'>
        <div class='stat-item'>
            <div class='stat-value' style='color: #2563eb;'>{$overallRate}%</div>
            <div class='stat-label'>Skor Kepatuhan Global</div>
        </div>
        <div class='stat-item'>
            <div class='stat-value'>".($findings['total_active'] ?? 0)."</div>
            <div class='stat-label'>Temuan Terbuka (Open)</div>
        </div>
        <div class='stat-item'>
            <div class='stat-value' style='color: #dc2626;'>".($findings['overdue'] ?? 0)."</div>
            <div class='stat-label'>Temuan Lewat Deadline</div>
        </div>
        <div class='stat-item'>
            <div class='stat-value'>".($risks['total_active'] ?? 0)."</div>
            <div class='stat-label'>Total Register Risiko</div>
        </div>
    </div>

    <div class='section-title'>1. Ringkasan Kepatuhan per Kerangka Kerja (Framework)</div>
    <table>
        <thead>
            <tr>
                <th>Nama Standar / Framework</th>
                <th style='text-align: center;'>Total Kontrol</th>
                <th style='text-align: center;'>Compliant</th>
                <th style='text-align: center;'>Partial</th>
                <th style='text-align: center;'>Non-Compliant</th>
                <th style='text-align: center;'>Tingkat Kepatuhan</th>
            </tr>
        </thead>
        <tbody>
            {$frameworkRows}
        </tbody>
    </table>

    <div class='section-title'>2. Parameter Audit &amp; Metadata</div>
    <table>
        <tr>
            <td style='padding: 6px 12px; width: 25%; font-weight: 600; background: #f8fafc; border: 1px solid #cbd5e1;'>Cakupan Unit Kerja</td>
            <td style='padding: 6px 12px; border: 1px solid #cbd5e1;'>{$unitName}</td>
        </tr>
        <tr>
            <td style='padding: 6px 12px; font-weight: 600; background: #f8fafc; border: 1px solid #cbd5e1;'>Tanggal Dibuat</td>
            <td style='padding: 6px 12px; border: 1px solid #cbd5e1;'>{$generatedAt}</td>
        </tr>
        <tr>
            <td style='padding: 6px 12px; font-weight: 600; background: #f8fafc; border: 1px solid #cbd5e1;'>Diekspor Oleh</td>
            <td style='padding: 6px 12px; border: 1px solid #cbd5e1;'>{$generatedBy}</td>
        </tr>
        <tr>
            <td style='padding: 6px 12px; font-weight: 600; background: #f8fafc; border: 1px solid #cbd5e1;'>Status Keamanan</td>
            <td style='padding: 6px 12px; border: 1px solid #cbd5e1;'>Audit-Ready &amp; Verified Anti-Tamper Logged</td>
        </tr>
    </table>

    <div class='footer'>
        <span>Dicetak secara otomatis melalui Sistem Kepatuhan Digital SMKI Komdigi</span>
        <span>ID Laporan: SMKI-AUDIT-".now()->format('YmdHis').'</span>
    </div>
</body>
</html>';

        $pdfContent = Pdf::html($html)->format('a4')->portrait()->generatePdfContent();
        $filename = 'SMKI_Compliance_Report_'.now()->format('Ymd_His').'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Report-Format' => 'PDF-Audit-Ready',
        ]);
    }

    /**
     * Stream CSV export of compliance controls & status (Audit-Ready format).
     */
    public function exportComplianceSummaryCsv(User $user, ?int $unitId = null): StreamedResponse
    {
        if (Gate::forUser($user)->denies('report.export')) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengekspor laporan kepatuhan.');
        }

        $scopedUnitIds = $this->analyticsService->resolveScopedUnitIds($user, array_merge($unitId !== null ? ['unit_id' => $unitId] : []));

        AuditLog::catat(
            'Report',
            0,
            'export',
            $user->id,
            [
                'report_type' => 'compliance_summary_csv',
                'scoped_unit_id' => count($scopedUnitIds) === 1 ? $scopedUnitIds[0] : $scopedUnitIds,
                'exported_at' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
            ]
        );

        $filename = 'SMKI_Compliance_Report_'.now()->format('Ymd_His').'.csv';

        $entriesQuery = ChecklistEntry::with(['control.framework', 'unit', 'admin']);
        if ($scopedUnitIds !== null) {
            $entriesQuery->whereIn('unit_id', $scopedUnitIds);
        }
        $entries = $entriesQuery->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($entries) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID Entri',
                'Kode Klausul',
                'Judul Kontrol',
                'Framework',
                'Unit Kerja',
                'Status Kepatuhan',
                'Catatan Admin',
                'Diverifikasi Oleh',
                'Tanggal Verifikasi',
                'Terakhir Diperbarui',
            ]);

            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->id,
                    $entry->control?->kode_klausul ?? '-',
                    $entry->control?->judul ?? '-',
                    $entry->control?->framework?->nama ?? '-',
                    $entry->unit?->nama ?? '-',
                    strtoupper($entry->status ?? 'NOT_EVALUATED'),
                    $entry->catatan_admin ?? '-',
                    $entry->admin?->name ?? '-',
                    $entry->tanggal_verifikasi ? $entry->tanggal_verifikasi->format('Y-m-d H:i') : '-',
                    $entry->updated_at ? $entry->updated_at->format('Y-m-d H:i') : '-',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Ambil & validasi config untuk 1 jenis laporan.
     */
    protected function resolveConfig(string $reportType): array
    {
        $config = config("report_templates.{$reportType}");

        if (! $config) {
            abort(404, "Jenis laporan '{$reportType}' tidak dikenal.");
        }

        if (($config['enabled'] ?? true) === false || empty($config['view'])) {
            abort(501, "Jenis laporan '{$reportType}' belum diimplementasikan.");
        }

        return $config;
    }

    /**
     * Kumpulkan data sesuai skema masing-masing report type.
     */
    protected function buildDataFor(
        string $reportType,
        ?int $unitId,
        ?string $periode = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        return match ($reportType) {
            'quick-summary' => $this->buildQuickSummaryData($unitId, $periode, $startDate, $endDate),
            'executive' => $this->buildExecutiveData($unitId, $periode, $startDate, $endDate),
            default => [],
        };
    }

    protected function formatPeriodString(?string $periode, ?string $startDate, ?string $endDate): string
    {
        if ($startDate && $endDate) {
            try {
                $s = Carbon::parse($startDate)->isoFormat('D MMMM Y');
                $e = Carbon::parse($endDate)->isoFormat('D MMMM Y');

                return "{$s} — {$e}";
            } catch (\Exception) {
                return "{$startDate} s/d {$endDate}";
            }
        }

        if ($periode) {
            try {
                return Carbon::parse($periode.'-01')->isoFormat('MMMM Y');
            } catch (\Exception) {
                return $periode;
            }
        }

        return 'Semester Ini ('.now()->isoFormat('MMMM Y').')';
    }

    /**
     * Resolve the relevant checklist session IDs based on unit_id, periode, or date range.
     * Selects the latest session per (unit_id, framework_id) within the filtered timeframe.
     */
    protected function resolveSessionIds(
        ?int $unitId,
        ?string $periode = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): Collection {
        $startPeriod = $startDate ? substr($startDate, 0, 7) : null;
        $endPeriod = $endDate ? substr($endDate, 0, 7) : null;
        if ($periode) {
            $startPeriod = $periode;
            $endPeriod = $periode;
        }

        $q = \DB::table('checklist_sessions')->whereNull('deleted_at');

        if ($unitId) {
            $q->where('unit_id', $unitId);
        }

        if ($startPeriod) {
            $q->where('periode', '>=', $startPeriod);
        }

        if ($endPeriod) {
            $q->where('periode', '<=', $endPeriod);
        }

        $subSql = $q->select('id', 'unit_id', 'framework_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY unit_id, framework_id ORDER BY periode DESC, id DESC) as rn')
            ->toSql();

        $bindings = $q->getBindings();

        return \DB::table(\DB::raw("({$subSql}) as latest_sessions"))
            ->setBindings($bindings)
            ->where('rn', 1)
            ->pluck('id');
    }

    protected function buildQuickSummaryData(
        ?int $unitId,
        ?string $periode = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $periodString = $this->formatPeriodString($periode, $startDate, $endDate);
        $allControls = \DB::table('controls')->whereNull('deleted_at')->orderBy('id')->get();

        if ($unitId) {
            $unitData = $this->buildSingleUnitQuickSummaryData($unitId, $periode, $startDate, $endDate, $allControls);
            $unitData['is_multi_unit'] = false;

            return $unitData;
        }

        // Consolidated multi-unit report for all 10 registered work units
        $workUnits = WorkUnit::orderBy('id')->get();
        $unitsData = [];
        $unitComparisons = [];
        $totalRates = [];
        $unitsWithAssessments = 0;

        foreach ($workUnits as $unit) {
            $uData = $this->buildSingleUnitQuickSummaryData($unit->id, $periode, $startDate, $endDate, $allControls);
            $unitsData[] = $uData;

            $diterapkan = $uData['kpi']['sudah_diterapkan'];
            $berlaku = $uData['kpi']['kontrol_berlaku'];
            $pct = $berlaku > 0 ? round(($diterapkan / $berlaku) * 100, 1) : 0;
            $totalRates[] = $pct;

            if ($diterapkan > 0 || $uData['kpi']['progres_keseluruhan_persen'] > 0) {
                $unitsWithAssessments++;
            }

            $picName = $uData['signoff']['disusun']['nama'] ?? '-';

            $unitComparisons[] = [
                'id' => $unit->id,
                'nama' => $unit->nama,
                'pic' => $picName,
                'berlaku' => $berlaku,
                'diterapkan' => $diterapkan,
                'persen_kepatuhan' => $pct,
            ];
        }

        $avgRate = count($totalRates) > 0 ? round(array_sum($totalRates) / count($totalRates), 1) : 0;

        return [
            'is_multi_unit' => true,
            'organisasi' => 'Kementerian Komunikasi dan Digital RI',
            'periode_laporan' => $periodString,
            'disusun_oleh' => 'Tim ISMS/PIMS — Information Security Office',
            'direview_disetujui_oleh' => 'Chief Information Security Officer (CISO)',
            'klasifikasi' => 'Internal — Terbatas',
            'tanggal_dibuat' => now()->isoFormat('D MMMM Y'),
            'ministry_summary' => [
                'total_kontrol' => $allControls->count(),
                'total_unit' => $workUnits->count(),
                'total_unit_aktif' => $unitsWithAssessments,
                'rata_rata_kepatuhan' => $avgRate,
            ],
            'unit_comparisons' => $unitComparisons,
            'units_data' => $unitsData,
        ];
    }

    protected function buildSingleUnitQuickSummaryData(
        int $unitId,
        ?string $periode,
        ?string $startDate,
        ?string $endDate,
        Collection $allControls
    ): array {
        $unit = WorkUnit::find($unitId);
        $unitName = $unit?->nama ?? "Unit Kerja #{$unitId}";
        $periodString = $this->formatPeriodString($periode, $startDate, $endDate);

        $sessionIds = $this->resolveSessionIds($unitId, $periode, $startDate, $endDate);

        // Query checklist entries for this unit
        $query = \DB::table('checklist_entries')
            ->join('controls', 'checklist_entries.control_id', '=', 'controls.id')
            ->leftJoin('users as pic_user', 'checklist_entries.pic_id', '=', 'pic_user.id')
            ->leftJoin('work_units as pic_unit', 'pic_user.unit_id', '=', 'pic_unit.id')
            ->select(
                'controls.id as control_id',
                'controls.framework_id',
                'controls.kode_klausul',
                'controls.judul',
                'controls.kategori',
                'controls.domain_peran',
                'checklist_entries.status',
                'checklist_entries.level_maturity',
                'pic_user.name as pic_name',
                'pic_unit.nama as pic_unit_name',
                'checklist_entries.updated_at'
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

        // Get latest entry per control
        $uniqueControls = collect();
        foreach ($entries as $e) {
            if (! $uniqueControls->has($e->control_id)) {
                $uniqueControls->put($e->control_id, $e);
            }
        }

        // Group definitions matching reference report
        $standardGroups = [
            'ISO/IEC 27001:2022 (Annex A)' => $allControls->where('framework_id', 1)->where('kategori', 'annex_a'),
            'ISO/IEC 27701:2025 - A.1 Controller' => $allControls->where('framework_id', 2)->where('domain_peran', 'controller'),
            'ISO/IEC 27701:2025 - A.2 Processor' => $allControls->where('framework_id', 2)->where('domain_peran', 'processor'),
            'ISO/IEC 27701:2025 - A.3 Shared' => $allControls->where('framework_id', 2)->where(fn ($c) => empty($c->domain_peran)),
            'ISO/IEC 27001:2022 (Klausul 4-10)' => $allControls->where('framework_id', 1)->where('kategori', 'klausul_4_10'),
        ];

        $tabelRingkasan = [];
        $chartBarPerStandar = [];
        $totalDiterapkan = 0;
        $totalDalamProses = 0;
        $totalDalamTinjauan = 0;
        $totalBelumDimulai = 0;
        $totalTidakBerlaku = 0;
        $totalKontrolCount = 0;
        $totalBerlakuCount = 0;

        $detailStatusKontrol = [];
        $openControls = [];

        foreach ($standardGroups as $groupLabel => $controlsInGroup) {
            $groupTotal = $controlsInGroup->count();
            $groupBerlaku = 0;
            $groupDiterapkan = 0;
            $groupProses = 0;
            $groupTinjauan = 0;
            $groupBelum = 0;
            $groupNa = 0;

            $groupDetails = [];

            foreach ($controlsInGroup as $ctrl) {
                $entry = $uniqueControls->get($ctrl->id);
                $status = $entry?->status ?? ChecklistEntry::WORKFLOW_BELUM_DIMULAI;

                $picText = '-';
                if (! empty($entry?->pic_name)) {
                    $picText = $entry->pic_name.($entry->pic_unit_name ? " ({$entry->pic_unit_name})" : '');
                }

                if ($status === ChecklistEntry::WORKFLOW_SELESAI) {
                    $groupDiterapkan++;
                    $groupBerlaku++;
                    $statusLabel = 'Diterapkan';
                    $pct = 100;
                    $maturity = $entry?->level_maturity ?? 3;
                } elseif ($status === ChecklistEntry::WORKFLOW_DALAM_PROSES) {
                    $groupProses++;
                    $groupBerlaku++;
                    $statusLabel = 'Dalam Proses';
                    $pct = 50;
                    $maturity = $entry?->level_maturity ?? 2;
                } elseif ($status === ChecklistEntry::WORKFLOW_DALAM_TINJAUAN) {
                    $groupTinjauan++;
                    $groupBerlaku++;
                    $statusLabel = 'Dalam Tinjauan';
                    $pct = 75;
                    $maturity = $entry?->level_maturity ?? 2;
                } elseif ($status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU) {
                    $groupNa++;
                    $statusLabel = 'Tidak Berlaku';
                    $pct = 0;
                    $maturity = $entry?->level_maturity ?? 0;
                } else {
                    $groupBelum++;
                    $groupBerlaku++;
                    $statusLabel = 'Belum Dimulai';
                    $pct = 0;
                    $maturity = $entry?->level_maturity ?? 1;
                }

                $detailItem = [
                    'id' => $ctrl->kode_klausul,
                    'nama_kontrol' => $ctrl->judul,
                    'status' => $statusLabel,
                    'maturity' => $maturity,
                    'persen_progres' => $pct,
                    'pic' => $picText,
                ];
                $groupDetails[] = $detailItem;

                if (in_array($statusLabel, ['Belum Dimulai', 'Dalam Proses', 'Dalam Tinjauan'], true)) {
                    $openControls[] = [
                        'id' => $ctrl->kode_klausul,
                        'nama_kontrol' => $ctrl->judul,
                        'standar' => $groupLabel,
                        'status' => $statusLabel,
                        'pic' => $picText,
                        'target' => now()->addMonths(2)->format('d/m/Y'),
                    ];
                }
            }

            $detailStatusKontrol[$groupLabel] = $groupDetails;

            $groupPct = $groupBerlaku > 0 ? round(($groupDiterapkan / $groupBerlaku) * 100, 1) : 0;

            $tabelRingkasan[] = [
                'standar' => $groupLabel,
                'total' => $groupTotal,
                'berlaku' => $groupBerlaku,
                'diterapkan' => $groupDiterapkan,
                'proses' => $groupProses,
                'tinjauan' => $groupTinjauan,
                'belum' => $groupBelum,
                'na' => $groupNa,
                'persen_progres' => $groupPct,
            ];

            $shortLabel = match ($groupLabel) {
                'ISO/IEC 27001:2022 (Annex A)' => 'ISO 27001:2022',
                'ISO/IEC 27701:2025 - A.1 Controller' => '27701 A.1 Controller',
                'ISO/IEC 27701:2025 - A.2 Processor' => '27701 A.2 Processor',
                'ISO/IEC 27701:2025 - A.3 Shared' => '27701 A.3 Shared',
                default => '27001 Klausul 4-10',
            };

            $chartBarPerStandar[] = [
                'label' => $shortLabel,
                'persen' => (int) round($groupPct),
            ];

            $totalKontrolCount += $groupTotal;
            $totalBerlakuCount += $groupBerlaku;
            $totalDiterapkan += $groupDiterapkan;
            $totalDalamProses += $groupProses;
            $totalDalamTinjauan += $groupTinjauan;
            $totalBelumDimulai += $groupBelum;
            $totalTidakBerlaku += $groupNa;
        }

        $overallPct = $totalBerlakuCount > 0 ? round(($totalDiterapkan / $totalBerlakuCount) * 100) : 0;

        // Domain breakdown for ISO 27001 Annex A (A.5, A.6, A.7, A.8)
        $annexAControls = $standardGroups['ISO/IEC 27001:2022 (Annex A)'];
        $domainDefs = [
            'Organisasi' => 'A.5',
            'SDM' => 'A.6',
            'Fisik' => 'A.7',
            'Teknologi' => 'A.8',
        ];

        $progresPerDomain = [];
        foreach ($domainDefs as $dName => $prefix) {
            $ctrls = $annexAControls->filter(fn ($c) => str_starts_with($c->kode_klausul, $prefix));
            $dTotal = $ctrls->count();
            $dDiterapkan = 0;
            foreach ($ctrls as $c) {
                $entry = $uniqueControls->get($c->id);
                if (($entry?->status ?? ChecklistEntry::WORKFLOW_BELUM_DIMULAI) === ChecklistEntry::WORKFLOW_SELESAI) {
                    $dDiterapkan++;
                }
            }
            $dPct = $dTotal > 0 ? round(($dDiterapkan / $dTotal) * 100, 1) : 0;
            $progresPerDomain[] = [
                'domain' => $dName,
                'total_berlaku' => $dTotal,
                'diterapkan' => $dDiterapkan,
                'persen_progres' => $dPct,
            ];
        }

        // PIC name resolution
        $picUser = User::where('unit_id', $unitId)->first();
        $picName = $picUser ? $picUser->name : "PIC {$unitName}";

        return [
            'organisasi' => $unitName,
            'periode_laporan' => $periodString,
            'disusun_oleh' => "PIC {$unitName} — {$picName}",
            'direview_disetujui_oleh' => 'Chief Information Security Officer (CISO)',
            'klasifikasi' => 'Internal — Terbatas',
            'tanggal_dibuat' => now()->isoFormat('D MMMM Y'),
            'kpi' => [
                'total_kontrol' => $totalKontrolCount,
                'kontrol_berlaku' => $totalBerlakuCount,
                'sudah_diterapkan' => $totalDiterapkan,
                'progres_keseluruhan_persen' => $overallPct,
            ],
            'ringkasan_eksekutif' => [
                'narasi_ringkasan' => 'Per '.now()->isoFormat('D MMMM Y').", dari total {$totalKontrolCount} kontrol yang dicakup pada {$unitName}, sebanyak {$totalBerlakuCount} kontrol dinyatakan berlaku (applicable) sesuai Statement of Applicability. Dari jumlah tersebut, {$totalDiterapkan} kontrol (".($totalBerlakuCount > 0 ? round(($totalDiterapkan / $totalBerlakuCount) * 100, 1) : 0)."%) telah diterapkan sepenuhnya, {$totalDalamProses} kontrol dalam proses implementasi, {$totalDalamTinjauan} dalam tinjauan, dan {$totalBelumDimulai} kontrol belum dimulai.",
                'chart_bar_per_standar' => $chartBarPerStandar,
                'chart_donut_distribusi' => [
                    'diterapkan' => $totalDiterapkan,
                    'dalam_proses' => $totalDalamProses,
                    'dalam_tinjauan' => $totalDalamTinjauan,
                    'belum_dimulai' => $totalBelumDimulai,
                    'tidak_berlaku' => $totalTidakBerlaku,
                ],
                'tabel_ringkasan' => $tabelRingkasan,
            ],
            'progres_per_domain' => $progresPerDomain,
            'kontrol_prioritas_tinggi' => $openControls,
            'detail_status_kontrol' => $detailStatusKontrol,
            'action_plan' => [
                ['no' => 1, 'tindakan' => "Selesaikan implementasi & pemenuhan bukti untuk kontrol prioritas pada {$unitName}.", 'pic' => $picName, 'target_selesai' => '-', 'status' => 'Dalam Proses'],
                ['no' => 2, 'tindakan' => 'Lakukan internal review kepatuhan berkala sebelum audit eksternal.', 'pic' => 'Tim ISMS/PIMS', 'target_selesai' => '-', 'status' => 'Belum Dimulai'],
                ['no' => 3, 'tindakan' => 'Jadwalkan review triwulanan bersama pimpinan unit untuk membahas kendala pemenuhan kontrol.', 'pic' => $picName, 'target_selesai' => '-', 'status' => 'Belum Dimulai'],
            ],
            'signoff' => [
                'disusun' => ['nama' => $picName, 'jabatan' => "Person in Charge (PIC) {$unitName}"],
                'direview' => ['nama' => 'Tim ISMS/PIMS — Information Security Office', 'jabatan' => 'Internal Reviewer / Auditor'],
                'disetujui' => ['nama' => 'Chief Information Security Officer (CISO)', 'jabatan' => 'Kepala Pusat Data & Sarana Informatika'],
            ],
        ];
    }

    protected function buildExecutiveData(
        ?int $unitId,
        ?string $periode = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $unitName = $unitId ? (WorkUnit::find($unitId)?->nama ?? 'Unit Kerja Terpilih') : 'Kementerian Komunikasi dan Digital RI';
        $periodString = $this->formatPeriodString($periode, $startDate, $endDate);

        $sessionIds = $this->resolveSessionIds($unitId, $periode, $startDate, $endDate);

        // Fetch checklist entries joined with controls
        $query = \DB::table('checklist_entries')
            ->join('controls', 'checklist_entries.control_id', '=', 'controls.id')
            ->select(
                'controls.id as control_id',
                'controls.framework_id',
                'controls.kode_klausul',
                'controls.judul',
                'controls.kategori',
                'controls.domain_peran',
                'checklist_entries.status'
            )
            ->whereNull('checklist_entries.deleted_at')
            ->whereNull('controls.deleted_at');

        if ($sessionIds->isNotEmpty()) {
            $query->whereIn('checklist_entries.session_id', $sessionIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($unitId) {
            $query->where('checklist_entries.unit_id', $unitId);
        } else {
            // When aggregating across multiple units for an organization-wide report,
            // prioritize implemented/in-progress controls
            $query->orderByRaw("CASE
                WHEN checklist_entries.status = '".ChecklistEntry::WORKFLOW_SELESAI."' THEN 1
                WHEN checklist_entries.status = '".ChecklistEntry::WORKFLOW_DALAM_TINJAUAN."' THEN 2
                WHEN checklist_entries.status = '".ChecklistEntry::WORKFLOW_DALAM_PROSES."' THEN 3
                WHEN checklist_entries.status = '".ChecklistEntry::WORKFLOW_BELUM_DIMULAI."' THEN 4
                ELSE 5
            END");
        }

        $entries = $query->orderBy('checklist_entries.updated_at', 'desc')->get();

        // Group by control to get unique statuses per control
        $uniqueControls = collect();
        foreach ($entries as $e) {
            if (! $uniqueControls->has($e->control_id)) {
                $uniqueControls->put($e->control_id, $e);
            }
        }

        $allControls = \DB::table('controls')->whereNull('deleted_at')->get();

        $iso27001Controls = $allControls->where('framework_id', 1);
        $iso27701Controls = $allControls->where('framework_id', 2);

        $calculateProgress = function ($controls, $uniqueEntries) {
            $totalApplicable = 0;
            $totalCompliant = 0;
            foreach ($controls as $c) {
                $entry = $uniqueEntries->get($c->id);
                if ($entry) {
                    if (in_array($entry->status, [
                        ChecklistEntry::WORKFLOW_SELESAI,
                        ChecklistEntry::WORKFLOW_DALAM_PROSES,
                        ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
                        ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                    ], true)) {
                        $totalApplicable++;
                        if ($entry->status === ChecklistEntry::WORKFLOW_SELESAI) {
                            $totalCompliant += 1;
                        } elseif ($entry->status === ChecklistEntry::WORKFLOW_DALAM_TINJAUAN) {
                            $totalCompliant += 0.75;
                        } elseif ($entry->status === ChecklistEntry::WORKFLOW_DALAM_PROSES) {
                            $totalCompliant += 0.5;
                        }
                    }
                }
            }

            return $totalApplicable > 0 ? (int) round(($totalCompliant / $totalApplicable) * 100) : 0;
        };

        $iso27001Progress = $calculateProgress($iso27001Controls, $uniqueControls);
        $iso27701Progress = $calculateProgress($iso27701Controls, $uniqueControls);

        $mapStatus = function ($status) {
            if ($status === ChecklistEntry::WORKFLOW_SELESAI) {
                return ['progress' => 100, 'label' => 'Selesai', 'badge' => 'diterapkan'];
            }
            if ($status === ChecklistEntry::WORKFLOW_DALAM_TINJAUAN) {
                return ['progress' => 75, 'label' => 'Tinjauan', 'badge' => 'tinjauan'];
            }
            if ($status === ChecklistEntry::WORKFLOW_DALAM_PROSES) {
                return ['progress' => 50, 'label' => 'Proses', 'badge' => 'proses'];
            }
            if ($status === ChecklistEntry::WORKFLOW_BELUM_DIMULAI) {
                return ['progress' => 0, 'label' => 'Belum', 'badge' => 'belum'];
            }
            if ($status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU) {
                return ['progress' => 0, 'label' => 'N/A', 'badge' => 'belum'];
            }

            return ['progress' => 0, 'label' => 'Belum', 'badge' => 'belum'];
        };

        $kategoriLabels = [
            'organisasional' => 'Organisasional',
            'orang' => 'Orang',
            'fisik' => 'Fisik',
            'teknologi' => 'Teknologi',
            'klausul_4_10' => 'Klausul Wajib',
            'annex_a' => 'Annex A',
        ];

        $detail27001 = $iso27001Controls->map(function ($c) use ($uniqueControls, $mapStatus, $kategoriLabels) {
            $entry = $uniqueControls->get($c->id);
            $statusInfo = $mapStatus($entry?->status);

            return [
                'klausul' => $c->kode_klausul,
                'nama_kontrol' => $c->judul,
                'kategori' => $kategoriLabels[$c->kategori] ?? $c->kategori,
                'progress' => $statusInfo['progress'],
                'status_label' => $statusInfo['label'],
                'status_badge' => $statusInfo['badge'],
            ];
        })->values()->toArray();

        $detail27701 = $iso27701Controls->map(function ($c) use ($uniqueControls, $mapStatus) {
            $entry = $uniqueControls->get($c->id);
            $statusInfo = $mapStatus($entry?->status);

            $peranLabel = '-';
            if ($c->domain_peran === 'controller') {
                $peranLabel = 'PII Controller';
            } elseif ($c->domain_peran === 'processor') {
                $peranLabel = 'PII Processor';
            }

            return [
                'klausul' => $c->kode_klausul,
                'nama_kontrol' => $c->judul,
                'peran' => $peranLabel,
                'progress' => $statusInfo['progress'],
                'status_label' => $statusInfo['label'],
                'status_badge' => $statusInfo['badge'],
            ];
        })->values()->toArray();

        return [
            'organisasi' => 'Kementerian Komunikasi dan Digital RI',
            'nama_satuan_kerja' => $unitName,
            'tanggal_laporan' => now()->isoFormat('D MMMM Y'),
            'periode_tinjauan' => $periodString,
            'iso27001_progress' => $iso27001Progress,
            'iso27701_progress' => $iso27701Progress,
            'detail_27001' => $detail27001,
            'detail_27701' => $detail27701,
            'signoff' => [
                'disusun' => ['nama' => 'Tim ISMS/PIMS', 'jabatan' => 'Compliance Coordinator'],
                'direview' => ['nama' => 'DPO Internal', 'jabatan' => 'Data Protection Officer'],
                'disetujui' => ['nama' => 'Dewi Lestari', 'jabatan' => 'Compliance Officer'],
            ],
        ];
    }

    protected function resolveChromePath(): ?string
    {
        $possiblePaths = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function logAudit(string $action, User $user, string $reportType, ?int $unitId): void
    {
        Log::info('report.export', [
            'action' => $action,
            'user_id' => $user->id,
            'report_type' => $reportType,
            'unit_id' => $unitId,
        ]);
    }
}
