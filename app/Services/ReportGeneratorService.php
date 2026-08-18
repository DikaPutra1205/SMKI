<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\Finding;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportGeneratorService
{
    public function __construct(
        protected DashboardAnalyticsService $analyticsService
    ) {}

    /**
     * Get structured compliance report summary data.
     */
    public function getComplianceReportData(User $user, ?int $unitId = null): array
    {
        if (Gate::forUser($user)->denies('export-reports')) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengekspor laporan kepatuhan.');
        }

        $scopedUnitId = $this->analyticsService->resolveScopedUnitId($user, $unitId);

        $summary = $this->analyticsService->getSummary($user, $scopedUnitId);
        $unitComparisons = $this->analyticsService->getUnitComparisons($user);

        $findingsQuery = Finding::with(['control', 'unit']);
        $risksQuery = Risk::with(['control']);

        if ($scopedUnitId) {
            $findingsQuery->where('unit_id', $scopedUnitId);
            $risksQuery->whereHas('control.checklistEntries', fn ($q) => $q->where('unit_id', $scopedUnitId));
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
            'scoped_unit' => $scopedUnitId ? WorkUnit::find($scopedUnitId)?->nama : 'Semua Unit Kerja',
            'summary' => $summary,
            'unit_metrics' => $unitComparisons,
            'governance' => [
                'open_findings' => $openFindings,
                'total_risks' => $totalRisks,
                'critical_high_risks' => $highRisks,
            ],
        ];
    }

    /**
     * Stream CSV export of compliance controls & status (Audit-Ready format).
     * Automatically registers an anti-tamper log entry into audit_logs.
     */
    public function exportComplianceSummaryCsv(User $user, ?int $unitId = null): StreamedResponse
    {
        if (Gate::forUser($user)->denies('export-reports')) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengekspor laporan kepatuhan.');
        }

        $scopedUnitId = $this->analyticsService->resolveScopedUnitId($user, $unitId);

        // Record immutable audit trail for report export
        AuditLog::catat(
            'Report',
            0,
            'export',
            $user->id,
            [
                'report_type' => 'compliance_summary_csv',
                'scoped_unit_id' => $scopedUnitId,
                'exported_at' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
            ]
        );

        $filename = 'SMKI_Compliance_Report_'.now()->format('Ymd_His').'.csv';

        $entriesQuery = ChecklistEntry::with(['control.framework', 'unit', 'admin']);
        if ($scopedUnitId) {
            $entriesQuery->where('unit_id', $scopedUnitId);
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
            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header Row
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
}
