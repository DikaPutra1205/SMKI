<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan: migrasi ini hanya menaikkan versi framework. Baris kontrol yang
        // sudah pensiun (mis. klausul `4.1`–`10.2`, kode klausul-5/6 27701 seperti
        // `5.2.1`) TIDAK otomatis dihapus — migrasi maupun seeder bersifat
        // upsert-only. Hapus lewat langkah ops manual, mis.
        // `Control::whereNotIn('kode_klausul', [...])->delete()` per framework.
        DB::table('frameworks')
            ->where('nama', 'ISO/IEC 27701')
            ->where('versi', '2019')
            ->update(['versi' => '2025', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('frameworks')
            ->where('nama', 'ISO/IEC 27701')
            ->where('versi', '2025')
            ->update(['versi' => '2019', 'updated_at' => now()]);
    }
};
