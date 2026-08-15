<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->index(['unit_id', 'status'], 'idx_cl_unit_status');
            $table->index(['unit_id', 'control_id'], 'idx_cl_unit_control');
            $table->index('tanggal_input', 'idx_cl_tgl_input');
        });

        Schema::table('controls', function (Blueprint $table) {
            $table->index('kode_klausul', 'idx_ctrl_kode');
            $table->index('kategori', 'idx_ctrl_kategori');
        });

        Schema::table('compliance_evidences', function (Blueprint $table) {
            $table->index(['checklist_entry_id', 'is_active'], 'idx_ev_cl_active');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->dropIndex('idx_cl_unit_status');
            $table->dropIndex('idx_cl_unit_control');
            $table->dropIndex('idx_cl_tgl_input');
        });

        Schema::table('controls', function (Blueprint $table) {
            $table->dropIndex('idx_ctrl_kode');
            $table->dropIndex('idx_ctrl_kategori');
        });

        Schema::table('compliance_evidences', function (Blueprint $table) {
            $table->dropIndex('idx_ev_cl_active');
        });
    }
};
