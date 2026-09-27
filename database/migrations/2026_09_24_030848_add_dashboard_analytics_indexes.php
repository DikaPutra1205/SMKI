<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checklist_sessions', function (Blueprint $table) {
            $table->index(['periode', 'deleted_at', 'unit_id', 'framework_id'], 'idx_sessions_dashboard_period');
        });

        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->index(['session_id', 'unit_id'], 'idx_entries_dashboard_session_unit');
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->index('created_at', 'idx_findings_dashboard_created');
            $table->index(['unit_id', 'created_at'], 'idx_findings_dashboard_unit_created');
        });

        Schema::table('risks', function (Blueprint $table) {
            $table->index('created_at', 'idx_risks_dashboard_created');
            $table->index(['unit_id', 'created_at'], 'idx_risks_dashboard_unit_created');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at', 'idx_audit_logs_dashboard_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checklist_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_sessions_dashboard_period');
        });

        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->dropIndex('idx_entries_dashboard_session_unit');
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->dropIndex('idx_findings_dashboard_created');
            $table->dropIndex('idx_findings_dashboard_unit_created');
        });

        Schema::table('risks', function (Blueprint $table) {
            $table->dropIndex('idx_risks_dashboard_created');
            $table->dropIndex('idx_risks_dashboard_unit_created');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_dashboard_created');
        });
    }
};
