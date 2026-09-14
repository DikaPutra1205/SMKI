{{-- resources/views/reports/partials/quick-summary-header.blade.php --}}
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    .h-wrap {
        width: 100%;
        font-family: Helvetica, Arial, sans-serif;
        font-size: 8pt;
        color: #1F3864;
        padding: 0 12mm;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 0.5pt solid #CBD5E0;
        padding-bottom: 4pt;
        -webkit-print-color-adjust: exact;
    }
    .h-left {
        font-weight: bold;
    }
    .h-right {
        color: #4A5568;
    }
</style>
<div class="h-wrap">
    <span class="h-left">Laporan Monitoring Progres — ISO/IEC 27001:2022 &amp; ISO/IEC 27701:2025</span>
    <span class="h-right">{{ $organisasi ?? 'Kementerian Komunikasi dan Digital RI' }}</span>
</div>