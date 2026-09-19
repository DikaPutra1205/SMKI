<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Map legacy status values to new workflow values
        DB::table('checklist_entries')->where('status', 'compliant')->update(['status' => 'selesai_diterapkan']);
        DB::table('checklist_entries')->where('status', 'partial')->update(['status' => 'dalam_proses']);
        DB::table('checklist_entries')->where('status', 'non_compliant')->update(['status' => 'belum_dimulai']);
        DB::table('checklist_entries')->where('status', 'na')->update(['status' => 'tidak_berlaku']);

        // Fix column default from legacy 'non_compliant' to 'belum_dimulai'
        try {
            Schema::table('checklist_entries', function ($table) {
                $table->string('status')->default('belum_dimulai')->change();
            });
        } catch (Throwable $e) {
            // SQLite fallback: no-op if change() not supported
        }
    }

    public function down(): void
    {
        DB::table('checklist_entries')->where('status', 'selesai_diterapkan')->update(['status' => 'compliant']);
        DB::table('checklist_entries')->where('status', 'dalam_proses')->update(['status' => 'partial']);
        DB::table('checklist_entries')->where('status', 'belum_dimulai')->update(['status' => 'non_compliant']);
        DB::table('checklist_entries')->where('status', 'tidak_berlaku')->update(['status' => 'na']);

        try {
            Schema::table('checklist_entries', function ($table) {
                $table->string('status')->default('non_compliant')->change();
            });
        } catch (Throwable $e) {
        }
    }
};
