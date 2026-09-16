<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pindahkan data existing risks.control_id → pivot table control_risk
        // sebelum kolom dihapus, agar tidak ada data yang hilang.
        $now = now();
        $risks = DB::table('risks')
            ->whereNotNull('control_id')
            ->select('id as risk_id', 'control_id')
            ->get();

        foreach ($risks as $risk) {
            DB::table('control_risk')->updateOrInsert(
                ['risk_id' => $risk->risk_id, 'control_id' => $risk->control_id],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        // Hapus index, FK constraint dan kolom control_id dari tabel risks
        Schema::table('risks', function (Blueprint $table) {
            $table->dropIndex(['control_id', 'level_risiko']);
            $table->dropConstrainedForeignId('control_id');
        });
    }

    public function down(): void
    {
        // Kembalikan kolom control_id (nullable agar tidak error saat rollback)
        Schema::table('risks', function (Blueprint $table) {
            $table->foreignId('control_id')->nullable()->constrained('controls')->nullOnDelete();
            $table->index(['control_id', 'level_risiko']);
        });

        // Isi ulang dari pivot (ambil control_id pertama per risk, best-effort)
        $records = DB::table('control_risk')
            ->select('risk_id', 'control_id')
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('risk_id');

        foreach ($records as $riskId => $items) {
            DB::table('risks')->where('id', $riskId)->update([
                'control_id' => $items->first()->control_id,
            ]);
        }
    }
};
