{{-- resources/views/reports/quick-summary.blade.php
     Laporan Monitoring Progres Kontrol ISO 27001 & ISO 27701
     Berdasarkan spesifikasi desain Contoh_Laporan_Monitoring_Progres_v2.pdf --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Monitoring Progres</title>
<style>
    :root {
        --navy-header: #1F3864;
        --navy-table-alt: #2E5395;
        --red-alert: #C00000;
        --gray-row-alt: #F2F2F2;
        --gray-border: #D9D9D9;
        --bar-blue: #2E5395;
        --bar-green: #548235;
        --donut-green: #375623;
        --donut-orange: #C55A11;
        --donut-red: #C00000;
        --donut-gray: #A6A6A6;
        
        --badge-diterapkan-bg: #C6E0B4;
        --badge-diterapkan-text: #375623;
        --badge-proses-bg: #FCE4D6;
        --badge-proses-text: #C55A11;
        --badge-belum-bg: #F8CBAD;
        --badge-belum-text: #C00000;
        --badge-na-bg: #E2E8F0;
        --badge-na-text: #64748B;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Liberation Sans', Arial, Helvetica, sans-serif;
        font-size: 8.5pt;
        color: #1A1A1A;
        line-height: 1.4;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    h1.doc-title {
        font-size: 24pt;
        font-weight: bold;
        color: var(--navy-header);
        margin-top: 36pt;
        margin-bottom: 6pt;
        letter-spacing: -0.3px;
    }

    p.doc-subtitle {
        font-size: 11pt;
        color: var(--navy-header);
        margin-bottom: 16pt;
    }

    .title-divider {
        height: 2pt;
        background-color: var(--navy-header);
        margin-bottom: 26pt;
    }

    h2.section-title {
        font-size: 14pt;
        font-weight: bold;
        color: var(--navy-header);
        margin-top: 8pt;
        margin-bottom: 10pt;
    }

    h3.sub-heading {
        font-size: 10pt;
        font-weight: bold;
        color: var(--navy-header);
        margin-top: 14pt;
        margin-bottom: 6pt;
    }

    p.narrative {
        font-size: 8.8pt;
        line-height: 1.5;
        color: #1A1A1A;
        margin-bottom: 16pt;
        text-align: justify;
    }

    /* Cover Info Block */
    .info-block {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 26pt;
        font-size: 9pt;
    }

    .info-block td {
        padding: 7pt 4pt;
        border-bottom: 0.5pt solid #E2E8F0;
        vertical-align: middle;
    }

    .info-block td.lbl {
        width: 35%;
        font-weight: bold;
        color: #000;
    }

    /* Cover KPI Cards */
    .kpi-row {
        display: flex;
        gap: 4pt;
        margin-bottom: 24pt;
    }

    .kpi-card {
        flex: 1;
        background: #F2F2F2;
        padding: 18pt 8pt;
        text-align: center;
        border-radius: 2px;
    }

    .kpi-card .num {
        font-size: 26pt;
        font-weight: bold;
        color: var(--navy-header);
        line-height: 1;
    }

    .kpi-card .lbl {
        font-size: 8.5pt;
        color: #475569;
        margin-top: 8pt;
    }

    p.cover-note {
        font-size: 8pt;
        color: #64748B;
        font-style: italic;
        line-height: 1.4;
    }

    /* Data Tables */
    table.data-table {
        width: 100%;
        border-collapse: collapse;
        margin: 8pt 0 16pt;
        font-size: 8.5pt;
        page-break-inside: auto;
    }

    table.data-table thead {
        display: table-header-group;
    }

    table.data-table tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    table.data-table th {
        background: var(--navy-header);
        color: #FFFFFF;
        font-weight: bold;
        text-align: left;
        padding: 5.5pt 6pt;
        border: 0.5pt solid var(--gray-border);
    }

    table.data-table.alt-header th {
        background: var(--navy-table-alt);
    }

    table.data-table.alert-header th {
        background: var(--red-alert);
    }

    table.data-table td {
        padding: 5.5pt 6pt;
        border: 0.5pt solid var(--gray-border);
        color: #1A1A1A;
        vertical-align: middle;
    }

    table.data-table tbody tr:nth-child(even) td {
        background: var(--gray-row-alt);
    }

    .text-center { text-align: center !important; }
    .text-right { text-align: right !important; }

    /* Badges */
    .badge {
        display: inline-block;
        padding: 2.5pt 6pt;
        border-radius: 3pt;
        font-size: 7.5pt;
        font-weight: bold;
        white-space: nowrap;
    }

    .badge-diterapkan { background: var(--badge-diterapkan-bg); color: var(--badge-diterapkan-text); }
    .badge-proses { background: var(--badge-proses-bg); color: var(--badge-proses-text); }
    .badge-belum { background: var(--badge-belum-bg); color: var(--badge-belum-text); }
    .badge-na { background: var(--badge-na-bg); color: var(--badge-na-text); }

    /* Vertical Bar Chart (Page 2) */
    .v-chart-container {
        flex: 1;
    }

    .v-chart-title {
        font-size: 9.5pt;
        font-weight: bold;
        color: var(--navy-header);
        text-align: center;
        margin-bottom: 22pt;
    }

    .v-chart-plot {
        display: flex;
        align-items: stretch;
        height: 140px;
        position: relative;
    }

    .v-chart-axis-y {
        width: 28px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: flex-end;
        padding-right: 6px;
        font-size: 7pt;
        color: #64748B;
        height: 100px;
    }

    .v-chart-bars-area {
        flex: 1;
        position: relative;
        height: 100px;
        border-bottom: 1.5px solid #1A1A1A;
        border-left: 1.5px solid #1A1A1A;
        display: flex;
        justify-content: space-around;
        align-items: flex-end;
        padding: 0 4px;
    }

    .v-chart-gridline {
        position: absolute;
        left: 0;
        right: 0;
        border-top: 0.5pt dashed #E2E8F0;
        z-index: 1;
    }

    .v-bar-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 55px;
        z-index: 2;
    }

    .v-bar-pct {
        font-size: 7.5pt;
        font-weight: bold;
        color: #1A1A1A;
        margin-bottom: 3px;
    }

    .v-bar-fill {
        width: 32px;
        background: var(--bar-blue);
        border-radius: 1px 1px 0 0;
    }

    .v-bar-label {
        font-size: 6.5pt;
        color: #1A1A1A;
        text-align: center;
        line-height: 1.2;
        margin-top: 6px;
        width: 60px;
    }

    /* Donut Chart (Page 2) */
    .donut-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .donut-title {
        font-size: 9.5pt;
        font-weight: bold;
        color: var(--navy-header);
        text-align: center;
        margin-bottom: 10pt;
        line-height: 1.2;
    }

    .donut-graphic {
        width: 125px;
        height: 125px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12pt;
    }

    .donut-hole {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        background: #FFFFFF;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .donut-pct {
        font-size: 13pt;
        font-weight: bold;
        color: var(--navy-header);
        line-height: 1;
    }

    .donut-lbl {
        font-size: 7pt;
        font-weight: 600;
        color: var(--navy-header);
        margin-top: 2px;
    }

    .donut-legend {
        font-size: 7.5pt;
        display: flex;
        flex-direction: column;
        gap: 3pt;
    }

    .donut-legend-item {
        display: flex;
        align-items: center;
        gap: 5pt;
    }

    .donut-swatch {
        width: 9pt;
        height: 9pt;
        display: inline-block;
        border-radius: 1px;
    }

    /* Horizontal Domain Chart (Page 3) */
    .h-chart-wrapper {
        width: 90%;
        margin: 10pt auto 20pt;
    }

    .h-chart-title {
        font-size: 9.5pt;
        font-weight: bold;
        color: var(--navy-header);
        text-align: center;
        margin-bottom: 16pt;
    }

    .h-chart-row {
        display: flex;
        align-items: center;
        margin-bottom: 10pt;
        font-size: 8.5pt;
    }

    .h-chart-label {
        width: 80px;
        text-align: right;
        padding-right: 12px;
        font-weight: 500;
        color: #1A1A1A;
    }

    .h-chart-track {
        flex: 1;
        height: 18px;
        position: relative;
        display: flex;
        align-items: center;
        border-left: 1.5px solid #1A1A1A;
    }

    .h-chart-fill {
        background: var(--bar-green);
        height: 100%;
    }

    .h-chart-val {
        margin-left: 8px;
        font-weight: bold;
        font-size: 8.5pt;
        color: #1A1A1A;
    }

    .h-chart-scale {
        margin-left: 80px;
        border-top: 1.5px solid #1A1A1A;
        display: flex;
        justify-content: space-between;
        padding-top: 4px;
        font-size: 7.5pt;
        color: #1A1A1A;
        position: relative;
    }

    .h-chart-scale-label {
        text-align: center;
        margin-top: 4px;
        font-size: 8pt;
        color: #1A1A1A;
    }

    /* Signoff block */
    .signoff-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14pt;
        font-size: 8.5pt;
    }

    .signoff-table td {
        padding: 4pt 8pt;
        vertical-align: top;
        width: 33.333%;
        border: none;
        background: transparent !important;
    }

    .signoff-title {
        font-weight: bold;
        color: #000;
        font-size: 8.5pt;
    }

    .signoff-border-row td {
        border-top: 0.75pt solid #1A1A1A !important;
        padding-top: 8pt;
    }

    .signoff-name {
        font-weight: bold;
        color: #1A1A1A;
        font-size: 8.5pt;
        line-height: 1.35;
    }

    .signoff-role {
        color: #4A5568;
        font-size: 8pt;
        line-height: 1.35;
    }

    .signoff-date {
        margin-top: 6pt;
        color: #1A1A1A;
        font-size: 7.5pt;
        font-style: italic;
    }

    .page-break {
        page-break-after: always;
    }

    /* Matrix Cover compact styles */
    .matrix-title {
        font-size: 20pt;
        font-weight: bold;
        color: var(--navy-header);
        margin-top: 14pt;
        margin-bottom: 4pt;
        letter-spacing: -0.2px;
    }
    .matrix-subtitle {
        font-size: 10pt;
        color: var(--navy-header);
        margin-bottom: 12pt;
    }
    .matrix-divider {
        height: 1.5pt;
        background-color: var(--navy-header);
        margin-bottom: 14pt;
    }
    .matrix-info-block td {
        padding: 4pt 4pt !important;
        font-size: 8pt;
    }
    .matrix-kpi-row {
        display: flex;
        gap: 4pt;
        margin-bottom: 14pt;
    }
    .matrix-kpi-card {
        flex: 1;
        background: #F2F2F2;
        padding: 10pt 6pt;
        text-align: center;
        border-radius: 2px;
    }
    .matrix-kpi-card .num {
        font-size: 18pt;
        font-weight: bold;
        color: var(--navy-header);
        line-height: 1;
    }
    .matrix-kpi-card .lbl {
        font-size: 7.5pt;
        color: #475569;
        margin-top: 4pt;
    }
    .matrix-table th {
        padding: 5pt 6pt !important;
        font-size: 8pt !important;
    }
    .matrix-table td {
        padding: 4.5pt 6pt !important;
        font-size: 7.5pt !important;
    }
</style>
</head>
<body>

@if(!empty($is_multi_unit))
    {{-- ========================================================================= --}}
    {{-- BAGIAN I: RINGKASAN EKSEKUTIF KEMENTERIAN & MATRIKS KOMPARASI 10 BIRO    --}}
    {{-- ========================================================================= --}}
    <h1 class="matrix-title">LAPORAN MONITORING PROGRES SMKI</h1>
    <p class="matrix-subtitle">Konsolidasi Implementasi Kontrol Seluruh Satuan Kerja — ISO/IEC 27001:2022 &amp; ISO/IEC 27701:2025</p>

    <div class="matrix-divider"></div>

    <table class="info-block matrix-info-block">
        <tr>
            <td class="lbl">Organisasi / Kementerian</td>
            <td><strong>{{ $organisasi }}</strong></td>
        </tr>
        <tr>
            <td class="lbl">Periode Penilaian</td>
            <td>{{ $periode_laporan }}</td>
        </tr>
        <tr>
            <td class="lbl">Cakupan Laporan</td>
            <td><strong>Konsolidasi Seluruh Satuan Kerja ({{ count($units_data) }} Satuan Kerja Terdaftar)</strong></td>
        </tr>
        <tr>
            <td class="lbl">Disusun oleh</td>
            <td>{{ $disusun_oleh }}</td>
        </tr>
        <tr>
            <td class="lbl">Direview / Disetujui oleh</td>
            <td>{{ $direview_disetujui_oleh }}</td>
        </tr>
        <tr>
            <td class="lbl">Klasifikasi Dokumen</td>
            <td>{{ $klasifikasi }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Terbit</td>
            <td>{{ $tanggal_dibuat }}</td>
        </tr>
    </table>

    <div class="matrix-kpi-row">
        <div class="matrix-kpi-card">
            <div class="num">{{ $ministry_summary['total_kontrol'] ?? 150 }}</div>
            <div class="lbl">Katalog Standar Kontrol</div>
        </div>
        <div class="matrix-kpi-card">
            <div class="num">{{ count($units_data) }}</div>
            <div class="lbl">Satuan Unit Kerja</div>
        </div>
        <div class="matrix-kpi-card">
            <div class="num">{{ $ministry_summary['total_unit_aktif'] ?? 0 }}</div>
            <div class="lbl">Unit Telah Asesmen</div>
        </div>
        <div class="matrix-kpi-card">
            <div class="num">{{ $ministry_summary['rata_rata_kepatuhan'] ?? 0 }}%</div>
            <div class="lbl">Rata-Rata Kepatuhan Global</div>
        </div>
    </div>

    <h2 class="section-title" style="font-size: 11pt; margin-top: 4pt; margin-bottom: 6pt;">Ringkasan Eksekutif &amp; Matriks Komparasi Satuan Kerja</h2>
    <p class="narrative" style="font-size: 8pt; margin-bottom: 10pt;">
        Dokumen ini menghimpun profil kepatuhan Sistem Manajemen Keamanan Informasi (SMKI) dan Sistem Manajemen Informasi Privasi (PIMS) di lingkungan Kementerian Komunikasi dan Digital RI. Tabel berikut menyajikan ikhtisar capaian kepatuhan untuk masing-masing satuan unit kerja:
    </p>

    <table class="data-table alt-header matrix-table" style="margin-bottom: 10pt;">
        <thead>
            <tr>
                <th class="text-center" style="width: 25pt;">No</th>
                <th>Satuan Unit Kerja</th>
                <th style="width: 130pt;">PIC Asesmen</th>
                <th class="text-center" style="width: 60pt;">Berlaku</th>
                <th class="text-center" style="width: 60pt;">Diterapkan</th>
                <th class="text-center" style="width: 70pt;">% Kepatuhan</th>
                <th class="text-center" style="width: 85pt;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($unit_comparisons as $index => $u)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong>{{ $u['nama'] }}</strong></td>
                    <td>{{ $u['pic'] }}</td>
                    <td class="text-center">{{ $u['berlaku'] }}</td>
                    <td class="text-center">{{ $u['diterapkan'] }}</td>
                    <td class="text-center" style="font-weight: bold;">{{ number_format($u['persen_kepatuhan'], 1) }}%</td>
                    <td class="text-center">
                        <span class="badge {{ $u['persen_kepatuhan'] >= 80 ? 'badge-diterapkan' : ($u['persen_kepatuhan'] > 0 ? 'badge-proses' : 'badge-belum') }}">
                            {{ $u['persen_kepatuhan'] >= 80 ? 'Sangat Baik' : ($u['persen_kepatuhan'] > 0 ? 'Dalam Proses' : 'Belum Dimulai') }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="cover-note" style="margin-bottom: 15pt;">
        Rincian profil kepatuhan, pemenuhan domain (Organisasi, SDM, Fisik, Teknologi), rincian kontrol, serta lembar pengesahan masing-masing satuan unit kerja disajikan secara lengkap pada bagian berikut.
    </p>

    {{-- ========================================================================= --}}
    {{-- BAGIAN II: PROFIL LENGKAP TIAP UNIT KERJA (DARI COVER SAMPAI TTD)        --}}
    {{-- ========================================================================= --}}
    @foreach ($units_data as $uData)
        <div class="page-break"></div>
        @include('reports.partials.quick-summary-unit-body', $uData)
    @endforeach

@else
    {{-- Single Unit Report --}}
    @include('reports.partials.quick-summary-unit-body')
@endif

</body>
</html>