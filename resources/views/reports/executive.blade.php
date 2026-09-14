<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Progress Kontrol</title>
<style>
    :root {
        --navy-header: #1F3864;
        --navy-header-end: #2B6CB0;
        --navy-table-alt: #2E5395;
        --blue-section: #2B5F9E;
        --gray-row-alt: #F7FAFC;
        --border-color: #E2E8F0;
        --text-main: #1A202C;
        --text-muted: #718096;
        --bar-track: #E2E8F0;
        --bar-fill: #2E5395;
        
        --badge-selesai-bg: #C6E0B4;
        --badge-selesai-text: #375623;
        --badge-proses-bg: #FCE4D6;
        --badge-proses-text: #C55A11;
        --badge-belum-bg: #F8CBAD;
        --badge-belum-text: #C00000;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    @page {
        size: 595.92pt 841.92pt;
        margin-top: 15mm;
        margin-bottom: 15mm;
        margin-left: 0;
        margin-right: 0;
    }
    @page :first {
        margin-top: 0;
        margin-bottom: 15mm;
        margin-left: 0;
        margin-right: 0;
    }
    body {
        font-family: 'Liberation Sans', Arial, Helvetica, sans-serif;
        font-size: 8.5pt;
        color: var(--text-main);
        line-height: 1.4;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Header Block - Page 1 Full Bleed */
    .header-block {
        background-color: #1A365D;
        border-bottom: 3pt solid #2B6CB0;
        padding: 16pt 12mm 14pt 12mm;
        color: #fff;
        margin: 0 -10mm 12pt -5mm;
        width: calc(100% + 20mm);
    }
    .header-block h1 { font-size: 15pt; font-weight: bold; margin-bottom: 4pt; color: #fff; letter-spacing: 0.2px; }
    .header-block p { font-size: 8.5pt; color: #e2e8f0; font-weight: normal; }

    .content-wrap {
        padding: 0 12mm;
    }

    /* Info Table */
    table.info-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 12pt;
        font-size: 8.5pt;
    }
    table.info-table td {
        border: 1px solid var(--border-color);
        padding: 6pt;
        vertical-align: middle;
    }
    table.info-table td.lbl { width: 15%; background: var(--gray-row-alt); font-weight: bold; }
    table.info-table td.val { width: 35%; background: #fff; }

    /* Section Bar */
    .section-bar {
        background: transparent;
        color: var(--navy-header);
        font-size: 10pt;
        font-weight: bold;
        padding: 5pt 0;
        margin-top: 16pt;
        margin-bottom: 10pt;
        display: flex;
        align-items: center;
    }
    
    .section-bar .bullet {
        width: 4pt;
        height: 12pt;
        background: var(--blue-section);
        margin-right: 6pt;
        display: inline-block;
    }

    /* Overall Progress Box */
    .progress-box {
        border: 1px solid var(--border-color);
        border-radius: 4pt;
        padding: 10pt;
        margin-bottom: 10pt;
        background: #fff;
    }
    .progress-box .title {
        font-weight: bold;
        font-size: 9pt;
        margin-bottom: 6pt;
    }
    .bar-track {
        background: var(--bar-track);
        height: 10pt;
        width: 100%;
        border-radius: 5pt;
        overflow: hidden;
    }
    .bar-fill {
        background: var(--bar-fill);
        height: 100%;
    }

    /* Data Tables */
    table.data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4pt;
        margin-bottom: 12pt;
        font-size: 8pt;
    }
    table.data-table th {
        color: #fff;
        font-weight: bold;
        text-align: left;
        padding: 6pt;
        border: 1px solid var(--border-color);
    }
    table.data-table.iso27001 th { background: var(--navy-header); }
    table.data-table.iso27701 th { background: var(--navy-table-alt); }
    
    table.data-table td {
        padding: 6pt;
        border: 1px solid var(--border-color);
        vertical-align: top;
    }
    table.data-table tr:nth-child(even) td { background: var(--gray-row-alt); }
    
    /* Badges */
    .badge {
        display: inline-block;
        padding: 2pt 6pt;
        border-radius: 2pt;
        font-size: 7.5pt;
        font-weight: bold;
        text-align: center;
    }
    .badge.diterapkan, .badge.selesai { background: var(--badge-selesai-bg); color: var(--badge-selesai-text); }
    .badge.proses { background: var(--badge-proses-bg); color: var(--badge-proses-text); }
    .badge.belum { background: var(--badge-belum-bg); color: var(--badge-belum-text); }

    /* Signoff */
    .signoff-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 12pt 0;
        margin-top: 24pt;
        font-size: 8.5pt;
        text-align: center;
        page-break-inside: avoid;
    }
    .signoff-table td {
        width: 33.33%;
        vertical-align: top;
    }
    .sig-line {
        margin-top: 40pt;
        border-bottom: 1px solid #000;
        padding-bottom: 4pt;
        font-weight: bold;
    }
    .sig-role {
        margin-top: 4pt;
        color: var(--text-muted);
    }
</style>
</head>
<body>

    <!-- Header Block -->
    <div class="header-block">
        <h1>LAPORAN PROGRESS KONTROL ISO 27001 &amp; ISO 27701</h1>
        <p>Sistem Manajemen Keamanan Informasi (SMKI) &amp; Informasi Privasi (PIMS)</p>
    </div>

    <div class="content-wrap">
        <!-- Info Table -->
        <table class="info-table">
            <tr>
                <td class="lbl">Nama Organisasi:</td>
                <td class="val">{{ $organisasi }}</td>
                <td class="lbl">Nama Satuan Kerja:</td>
                <td class="val">{{ $nama_satuan_kerja }}</td>
            </tr>
            <tr>
                <td class="lbl">Tanggal Laporan:</td>
                <td class="val">{{ $tanggal_laporan }}</td>
                <td class="lbl">Periode Tinjauan:</td>
                <td class="val">{{ $periode_tinjauan }}</td>
            </tr>
        </table>

        <!-- Section 1 -->
        <div class="section-bar">
            <span class="bullet"></span> 1. RINGKASAN EKSEKUTIF PROGRESS KONTROL
        </div>

        <div class="progress-box">
            <div class="title">ISO/IEC 27001:2022 (Keamanan Informasi) — Overall Progress: {{ $iso27001_progress }}%</div>
            <div class="bar-track">
                <div class="bar-fill" style="width: {{ $iso27001_progress }}%;"></div>
            </div>
        </div>

        <div class="progress-box">
            <div class="title">ISO/IEC 27701:2025 (Manajemen Informasi Privasi) — Overall Progress: {{ $iso27701_progress }}%</div>
            <div class="bar-track">
                <div class="bar-fill" style="width: {{ $iso27701_progress }}%; background: #48BB78;"></div>
            </div>
        </div>

        <!-- Section 2 -->
        <div class="section-bar">
            <span class="bullet"></span> 2. DETAIL KONTROL ISO/IEC 27001:2022
        </div>
        
        <table class="data-table iso27001">
            <thead>
                <tr>
                    <th style="width:10%">Klausul</th>
                    <th style="width:40%">Nama Kontrol</th>
                    <th style="width:20%">Kategori</th>
                    <th style="width:15%">Progress</th>
                    <th style="width:15%">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail_27001 as $c)
                <tr>
                    <td style="font-weight:bold;">{{ $c['klausul'] }}</td>
                    <td>{{ $c['nama_kontrol'] }}</td>
                    <td>{{ $c['kategori'] }}</td>
                    <td style="text-align:right;">{{ $c['progress'] }}%</td>
                    <td><span class="badge {{ $c['status_badge'] }}">{{ $c['status_label'] }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Section 3 -->
        <div class="section-bar">
            <span class="bullet"></span> 3. DETAIL KONTROL ISO/IEC 27701:2025
        </div>
        
        <table class="data-table iso27701">
            <thead>
                <tr>
                    <th style="width:10%">Klausul</th>
                    <th style="width:40%">Nama Kontrol PIMS</th>
                    <th style="width:20%">Peran</th>
                    <th style="width:15%">Progress</th>
                    <th style="width:15%">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail_27701 as $c)
                <tr>
                    <td style="font-weight:bold;">{{ $c['klausul'] }}</td>
                    <td>{{ $c['nama_kontrol'] }}</td>
                    <td>{{ $c['peran'] }}</td>
                    <td style="text-align:right;">{{ $c['progress'] }}%</td>
                    <td><span class="badge {{ $c['status_badge'] }}">{{ $c['status_label'] }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Signoff -->
        <table class="signoff-table">
            <tr>
                <td>
                    Disiapkan oleh,
                    <div class="sig-line">{{ $signoff['disusun']['nama'] ?? '' }}</div>
                    <div class="sig-role">{{ $signoff['disusun']['jabatan'] ?? '' }}</div>
                </td>
                <td>
                    Ditinjau oleh,
                    <div class="sig-line">{{ $signoff['direview']['nama'] ?? '' }}</div>
                    <div class="sig-role">{{ $signoff['direview']['jabatan'] ?? '' }}</div>
                </td>
                <td>
                    Disetujui oleh,
                    <div class="sig-line">{{ $signoff['disetujui']['nama'] ?? '' }}</div>
                    <div class="sig-role">{{ $signoff['disetujui']['jabatan'] ?? '' }}</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>