{{-- resources/views/reports/partials/quick-summary-unit-body.blade.php --}}

{{-- ===================== HALAMAN 1: COVER UNIT ===================== --}}
<h1 class="doc-title">LAPORAN MONITORING PROGRES</h1>
<p class="doc-subtitle">Implementasi Kontrol ISO/IEC 27001:2022 (ISMS) &amp; ISO/IEC 27701:2025 (PIMS)</p>

<div class="title-divider"></div>

<table class="info-block">
    <tr>
        <td class="lbl">Organisasi / Satuan Kerja</td>
        <td><strong>{{ $organisasi }}</strong></td>
    </tr>
    <tr>
        <td class="lbl">Periode Laporan</td>
        <td>{{ $periode_laporan }}</td>
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
        <td class="lbl">Tanggal Dibuat</td>
        <td>{{ $tanggal_dibuat }}</td>
    </tr>
</table>

<div class="kpi-row">
    <div class="kpi-card">
        <div class="num">{{ $kpi['total_kontrol'] }}</div>
        <div class="lbl">Total Kontrol</div>
    </div>
    <div class="kpi-card">
        <div class="num">{{ $kpi['kontrol_berlaku'] }}</div>
        <div class="lbl">Kontrol Berlaku</div>
    </div>
    <div class="kpi-card">
        <div class="num">{{ $kpi['sudah_diterapkan'] }}</div>
        <div class="lbl">Sudah Diterapkan</div>
    </div>
    <div class="kpi-card">
        <div class="num">{{ $kpi['progres_keseluruhan_persen'] }}%</div>
        <div class="lbl">Progres Keseluruhan</div>
    </div>
</div>

<p class="cover-note">Dokumen ini dibangkitkan otomatis oleh sistem berdasarkan data status kontrol terkini pada unit kerja terkait. Gunakan sebagai bahan monitoring progres implementasi ISMS/PIMS dan tinjauan manajemen.</p>

<div class="page-break"></div>

{{-- ===================== HALAMAN 2: RINGKASAN EKSEKUTIF ===================== --}}
<h2 class="section-title">1. Ringkasan Eksekutif</h2>
<p class="narrative">{{ $ringkasan_eksekutif['narasi_ringkasan'] }}</p>

@php
    $bar = $ringkasan_eksekutif['chart_bar_per_standar'];
    $donut = $ringkasan_eksekutif['chart_donut_distribusi'];
    $totalDonut = array_sum($donut) ?: 1;
    $segments = [
        ['value' => $donut['diterapkan'], 'color' => 'var(--donut-green)'],
        ['value' => $donut['dalam_proses'], 'color' => 'var(--donut-orange)'],
        ['value' => $donut['belum_dimulai'], 'color' => 'var(--donut-red)'],
        ['value' => $donut['tidak_berlaku'], 'color' => 'var(--donut-gray)'],
    ];
    $gradientParts = [];
    $cursor = 0;
    foreach ($segments as $seg) {
        $start = $cursor;
        $cursor += ($seg['value'] / $totalDonut) * 360;
        $gradientParts[] = "{$seg['color']} {$start}deg {$cursor}deg";
    }
    $gradientCss = implode(', ', $gradientParts);
@endphp

<div style="display: flex; gap: 20pt; margin-bottom: 18pt; align-items: flex-start;">
    {{-- Vertical Bar Chart --}}
    <div class="v-chart-container">
        <div class="v-chart-title">Progres Implementasi per Standar</div>
        <div class="v-chart-plot">
            <div class="v-chart-axis-y">
                <span>100</span>
                <span>80</span>
                <span>60</span>
                <span>40</span>
                <span>20</span>
                <span>0</span>
            </div>
            <div class="v-chart-bars-area">
                <div class="v-chart-gridline" style="bottom: 20%;"></div>
                <div class="v-chart-gridline" style="bottom: 40%;"></div>
                <div class="v-chart-gridline" style="bottom: 60%;"></div>
                <div class="v-chart-gridline" style="bottom: 80%;"></div>
                <div class="v-chart-gridline" style="bottom: 100%;"></div>

                @foreach ($bar as $b)
                    @php $barHeight = max(2, (int) round(($b['persen'] / 100) * 85)); @endphp
                    <div class="v-bar-col">
                        <div class="v-bar-pct">{{ $b['persen'] }}%</div>
                        <div class="v-bar-fill" style="height: {{ $barHeight }}px;"></div>
                        <div class="v-bar-label">{{ $b['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Donut Chart --}}
    <div class="donut-container">
        <div class="donut-title">
            Distribusi Status<br>
            <span style="font-size: 8pt; font-weight: normal; color: #475569;">(Seluruh Kontrol)</span>
        </div>
        <div class="donut-graphic" style="background: conic-gradient({{ $gradientCss }});">
            <div class="donut-hole">
                <div class="donut-pct">{{ $kpi['progres_keseluruhan_persen'] }}%</div>
                <div class="donut-lbl">Selesai</div>
            </div>
        </div>
        <div class="donut-legend">
            <div class="donut-legend-item">
                <span class="donut-swatch" style="background: var(--donut-green);"></span>
                <span>Diterapkan ({{ $donut['diterapkan'] }})</span>
            </div>
            <div class="donut-legend-item">
                <span class="donut-swatch" style="background: var(--donut-orange);"></span>
                <span>Dalam Proses ({{ $donut['dalam_proses'] }})</span>
            </div>
            <div class="donut-legend-item">
                <span class="donut-swatch" style="background: var(--donut-red);"></span>
                <span>Belum Dimulai ({{ $donut['belum_dimulai'] }})</span>
            </div>
            <div class="donut-legend-item">
                <span class="donut-swatch" style="background: var(--donut-gray);"></span>
                <span>Tidak Berlaku ({{ $donut['tidak_berlaku'] }})</span>
            </div>
        </div>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Standar / Ruang Lingkup</th>
            <th class="text-center" style="width: 45pt;">Total</th>
            <th class="text-center" style="width: 50pt;">Berlaku</th>
            <th class="text-center" style="width: 55pt;">Diterapkan</th>
            <th class="text-center" style="width: 45pt;">Proses</th>
            <th class="text-center" style="width: 45pt;">Belum</th>
            <th class="text-center" style="width: 40pt;">N/A</th>
            <th class="text-center" style="width: 55pt;">% Progres</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($ringkasan_eksekutif['tabel_ringkasan'] as $r)
            <tr>
                <td>{{ $r['standar'] }}</td>
                <td class="text-center">{{ $r['total'] }}</td>
                <td class="text-center">{{ $r['berlaku'] }}</td>
                <td class="text-center">{{ $r['diterapkan'] }}</td>
                <td class="text-center">{{ $r['proses'] }}</td>
                <td class="text-center">{{ $r['belum'] }}</td>
                <td class="text-center">{{ $r['na'] }}</td>
                <td class="text-center" style="font-weight: bold;">{{ number_format($r['persen_progres'], 1) }}%</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="page-break"></div>

{{-- ===================== HALAMAN 3: PROGRES PER DOMAIN ===================== --}}
<h2 class="section-title">2. Progres per Domain — ISO/IEC 27001:2022</h2>

<div class="h-chart-wrapper">
    <div class="h-chart-title">Progres per Domain — ISO/IEC 27001:2022</div>
    
    @php
        $orderedDomains = collect($progres_per_domain)->sortBy(function($d) {
            return match($d['domain']) {
                'Teknologi' => 1,
                'Fisik' => 2,
                'SDM' => 3,
                'Organisasi' => 4,
                default => 5,
            };
        });
    @endphp

    @foreach ($orderedDomains as $d)
        <div class="h-chart-row">
            <div class="h-chart-label">{{ $d['domain'] }}</div>
            <div class="h-chart-track">
                <div class="h-chart-fill" style="width: {{ $d['persen_progres'] }}%;"></div>
                <span class="h-chart-val">{{ (int) round($d['persen_progres']) }}%</span>
            </div>
        </div>
    @endforeach

    <div class="h-chart-scale">
        <span>0</span>
        <span>20</span>
        <span>40</span>
        <span>60</span>
        <span>80</span>
        <span>100</span>
    </div>
    <div class="h-chart-scale-label">% Progres</div>
</div>

<table class="data-table alt-header">
    <thead>
        <tr>
            <th>Domain</th>
            <th class="text-center" style="width: 100pt;">Total Berlaku</th>
            <th class="text-center" style="width: 100pt;">Diterapkan</th>
            <th class="text-center" style="width: 100pt;">% Progres</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($progres_per_domain as $d)
            <tr>
                <td>{{ $d['domain'] }}</td>
                <td class="text-center">{{ $d['total_berlaku'] }}</td>
                <td class="text-center">{{ $d['diterapkan'] }}</td>
                <td class="text-center" style="font-weight: bold;">{{ number_format($d['persen_progres'], 1) }}%</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="page-break"></div>

{{-- ===================== HALAMAN 4: KONTROL PRIORITAS TINGGI ===================== --}}
<h2 class="section-title">3. Kontrol Prioritas Tinggi yang Masih Terbuka</h2>
<p class="narrative" style="margin-bottom: 12pt;">
    Ditemukan {{ count($kontrol_prioritas_tinggi) }} kontrol berprioritas Tinggi yang belum sepenuhnya diterapkan. Kontrol berikut direkomendasikan menjadi fokus tindak lanjut periode berikutnya.
</p>

<table class="data-table alert-header">
    <thead>
        <tr>
            <th style="width: 50pt;">ID</th>
            <th>Nama Kontrol</th>
            <th style="width: 110pt;">Standar</th>
            <th class="text-center" style="width: 75pt;">Status</th>
            <th style="width: 100pt;">PIC</th>
            <th class="text-center" style="width: 65pt;">Target</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($kontrol_prioritas_tinggi as $k)
            <tr>
                <td style="font-weight: bold;">{{ $k['id'] }}</td>
                <td>{{ $k['nama_kontrol'] }}</td>
                <td>{{ $k['standar'] }}</td>
                <td class="text-center">
                    <span class="badge {{ $k['status'] === 'Dalam Proses' ? 'badge-proses' : 'badge-belum' }}">
                        {{ $k['status'] }}
                    </span>
                </td>
                <td>{{ $k['pic'] }}</td>
                <td class="text-center">{{ $k['target'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 12pt; color: #64748B;">
                    Semua kontrol prioritas tinggi telah diterapkan.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="page-break"></div>

{{-- ===================== DETAIL STATUS KONTROL ===================== --}}
<h2 class="section-title">4. Detail Status Kontrol</h2>
<p class="narrative" style="margin-bottom: 14pt;">
    Tabel berikut menampilkan status seluruh kontrol yang berlaku (applicable) per standar pada satuan kerja ini.
</p>

@foreach ($detail_status_kontrol as $standar => $kontrolList)
    <h3 class="sub-heading">{{ $standar }}</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 55pt;">ID</th>
                <th>Nama Kontrol</th>
                <th class="text-center" style="width: 75pt;">Status</th>
                <th class="text-center" style="width: 50pt;">Maturity</th>
                <th class="text-center" style="width: 60pt;">% Progres</th>
                <th style="width: 110pt;">PIC</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($kontrolList as $k)
                @php
                    $badgeClass = match ($k['status']) {
                        'Diterapkan' => 'badge-diterapkan',
                        'Dalam Proses' => 'badge-proses',
                        'Tidak Berlaku' => 'badge-na',
                        default => 'badge-belum',
                    };
                @endphp
                <tr>
                    <td style="font-weight: bold;">{{ $k['id'] }}</td>
                    <td>{{ $k['nama_kontrol'] }}</td>
                    <td class="text-center">
                        <span class="badge {{ $badgeClass }}">{{ $k['status'] }}</span>
                    </td>
                    <td class="text-center">{{ $k['maturity'] }}</td>
                    <td class="text-center" style="font-weight: bold;">{{ $k['persen_progres'] }}%</td>
                    <td>{{ $k['pic'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach

<div class="page-break"></div>

{{-- ===================== ACTION PLAN & SIGN-OFF ===================== --}}
<h2 class="section-title">5. Rencana Tindak Lanjut (Action Plan)</h2>
<table class="data-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 30pt;">No</th>
            <th>Tindakan</th>
            <th style="width: 100pt;">PIC</th>
            <th class="text-center" style="width: 80pt;">Target Selesai</th>
            <th class="text-center" style="width: 80pt;">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($action_plan as $a)
            <tr>
                <td class="text-center">{{ $a['no'] }}</td>
                <td>{{ $a['tindakan'] }}</td>
                <td>{{ $a['pic'] }}</td>
                <td class="text-center">{{ $a['target_selesai'] ?? '-' }}</td>
                <td class="text-center">
                    <span class="badge {{ $a['status'] === 'Dalam Proses' ? 'badge-proses' : 'badge-belum' }}">
                        {{ $a['status'] }}
                    </span>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="font-size: 7.5pt; font-style: italic; color: #64748B; margin-top: -6pt; margin-bottom: 24pt;">
    Catatan: Rencana tindak lanjut disusun berdasarkan asesmen terkini untuk pemenuhan kontrol yang belum lengkap.
</p>

<h2 class="section-title">6. Persetujuan (Sign-off)</h2>
<table class="signoff-table">
    <tr>
        <td>
            <div class="signoff-title">Disusun oleh</div>
            <div style="height: 50pt;"></div>
        </td>
        <td>
            <div class="signoff-title">Direview oleh</div>
            <div style="height: 50pt;"></div>
        </td>
        <td>
            <div class="signoff-title">Disetujui oleh</div>
            <div style="height: 50pt;"></div>
        </td>
    </tr>
    <tr class="signoff-border-row">
        <td>
            <div class="signoff-name">{{ $signoff['disusun']['nama'] ?? 'Tim ISMS/PIMS — Information Security Office' }}</div>
            @if(!empty($signoff['disusun']['jabatan']))
                <div class="signoff-role">{{ $signoff['disusun']['jabatan'] }}</div>
            @endif
            <div class="signoff-date">Tanggal: ______________</div>
        </td>
        <td>
            <div class="signoff-name">{{ $signoff['direview']['nama'] ?? '________________________' }}</div>
            @if(!empty($signoff['direview']['jabatan']))
                <div class="signoff-role">{{ $signoff['direview']['jabatan'] }}</div>
            @endif
            <div class="signoff-date">Tanggal: ______________</div>
        </td>
        <td>
            <div class="signoff-name">{{ $signoff['disetujui']['nama'] ?? 'Chief Information Security Officer (CISO)' }}</div>
            @if(!empty($signoff['disetujui']['jabatan']))
                <div class="signoff-role">{{ $signoff['disetujui']['jabatan'] }}</div>
            @endif
            <div class="signoff-date">Tanggal: ______________</div>
        </td>
    </tr>
</table>
