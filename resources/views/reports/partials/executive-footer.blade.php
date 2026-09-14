<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    .f-wrap {
        width: 100%;
        font-family: 'Liberation Sans', Arial, Helvetica, sans-serif;
        font-size: 7.5pt;
        color: #718096;
        padding: 0 51pt;
        display: flex;
        justify-content: space-between;
        padding-top: 10pt;
    }
</style>
<div class="f-wrap">
    <span>{{ $tanggal_dibuat ?? now()->isoFormat('D MMMM Y') }} | Rahasia - Dokumen Internal</span>
    <span>Halaman @pageNumber</span>
</div>
