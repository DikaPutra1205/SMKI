<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the non-partial unique index on controls(framework_id, kode_klausul)
 * with a partial index that ignores soft-deleted rows (deleted_at IS NULL).
 *
 * Without this, re-importing a kode that was previously soft-deleted triggers
 * SQLSTATE[23505] because the old row (with deleted_at set) still occupies the
 * unique slot in the non-partial index.
 *
 * The partial index is PostgreSQL-specific syntax; for SQLite (testing) the
 * driver already handles this via the WHERE clause on the query side, and the
 * validation layer (StoreControlRequest / UpdateControlRequest) applies
 * ->whereNull('deleted_at') before checking uniqueness.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop the old non-partial unique constraint
        Schema::table('controls', function ($table) {
            $table->dropUnique('uniq_ctrl_fw_kode');
        });

        // 2. Create a partial unique index using raw SQL
        //    - PostgreSQL: WHERE deleted_at IS NULL  (true partial index)
        //    - SQLite    : WHERE deleted_at IS NULL  (supported since SQLite 3.8.9)
        //    - MySQL     : WHERE clause is NOT supported; fall back to a normal
        //                  unique index (validation layer is the guard there).
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX uniq_ctrl_fw_kode
                 ON controls (framework_id, kode_klausul)
                 WHERE deleted_at IS NULL'
            );
        } else {
            // MySQL / MariaDB fallback — partial indexes are not supported.
            // Validation in StoreControlRequest / UpdateControlRequest already
            // applies ->whereNull('deleted_at'), so this is defence-in-depth.
            DB::statement(
                'ALTER TABLE controls
                 ADD UNIQUE INDEX uniq_ctrl_fw_kode (framework_id, kode_klausul)'
            );
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS uniq_ctrl_fw_kode');
        } else {
            Schema::table('controls', function ($table) {
                $table->dropUnique('uniq_ctrl_fw_kode');
            });
        }

        // Restore the original non-partial unique constraint
        Schema::table('controls', function ($table) {
            $table->unique(['framework_id', 'kode_klausul'], 'uniq_ctrl_fw_kode');
        });
    }
};
