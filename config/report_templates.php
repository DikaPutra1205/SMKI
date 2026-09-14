<?php

// config/report_templates.php
//
// MAPPING FINAL (dikonfirmasi 13 Sep):
// - quick-summary -> Admin Kepatuhan -> chart+donut+KPI, bisa multi-halaman
// - executive     -> Koordinator SMKI -> 1 halaman ringkas (KPI + narasi)
// - audit-ready   -> Auditor -> TODO, nunggu konfirmasi Bu Kaut

return [

    // Admin Kepatuhan -- pemantauan rutin, DETAIL dgn chart/donut/KPI
    // supaya gampang spot domain yg bermasalah. Bisa multi-halaman kalau
    // jumlah kontrolnya banyak (repeating <thead> per section).
    'quick-summary' => [
        'label' => 'Quick Compliance Summary',
        'view' => 'reports.quick-summary',
        'header_view' => null,
        'footer_view' => 'reports.partials.quick-summary-footer',
        'audit_action' => 'quick_summary_pdf',
        'paper' => 'a4',
        'margins' => [
            'top' => 15,
            'right' => 12,
            'bottom' => 15,
            'left' => 12,
            'unit' => 'mm',
        ],
    ],

    // Koordinator SMKI -- 1 halaman ringkas buat bahan rapat/keputusan.
    'executive' => [
        'label' => 'Executive Compliance Report',
        'view' => 'reports.executive',
        'header_view' => null,
        'footer_view' => 'reports.partials.executive-footer',
        'audit_action' => 'executive_report_pdf',
        'paper' => 'a4',
    ],

    // Auditor -- TODO, menunggu konfirmasi spesifikasi dari Bu Kaut.
    // Draft awal ada di resources/views/reports/audit-ready-draft.blade.php
    // (styling Progress-Kontrol asli, per-klausul + badge status) --
    // kemungkinan besar jadi basis audit-ready begitu dikonfirmasi.
    'audit-ready' => [
        'label' => 'Audit-Ready Compliance Report',
        'view' => null,
        'header_view' => null,
        'footer_view' => null,
        'audit_action' => 'audit_ready_pdf',
        'paper' => 'a4',
        'enabled' => false,
    ],

];
