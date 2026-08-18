<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a unique index on (checklist_entry_id, version_number) to prevent
 * race-condition duplicate version numbers in compliance_evidences.
 *
 * The application-level fix (lockForUpdate in a transaction) is the primary
 * guard; this index is the database-level backstop.
 *
 * Note: For PostgreSQL/SQLite this is a plain non-partial unique index because
 * multiple soft-deleted rows for the same (entry_id, version_number) would
 * legitimately collide — each version must be globally unique per entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_evidences', function (Blueprint $table) {
            $table->unique(
                ['checklist_entry_id', 'version_number'],
                'uniq_ev_entry_version'
            );
        });
    }

    public function down(): void
    {
        Schema::table('compliance_evidences', function (Blueprint $table) {
            $table->dropUnique('uniq_ev_entry_version');
        });
    }
};
