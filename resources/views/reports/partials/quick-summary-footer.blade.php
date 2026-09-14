{{-- resources/views/reports/partials/quick-summary-footer.blade.php --}}
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    .f-wrap {
        width: 100%;
        font-family: Helvetica, Arial, sans-serif;
        font-size: 7.5pt;
        color: #718096;
        padding: 0 12mm;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 0.5pt solid #CBD5E0;
        padding-top: 4pt;
        -webkit-print-color-adjust: exact;
    }
</style>
<div class="f-wrap">
    <span>Klasifikasi: {{ $klasifikasi ?? 'Internal — Terbatas' }} · Dibuat otomatis oleh sistem pada {{ $tanggal_dibuat ?? now()->isoFormat('D MMMM Y') }}</span>
    <span>Halaman @pageNumber</span>
</div>