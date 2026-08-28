<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pic_id is seeded from the unit's PIC (falling back to any global PIC),
     * but resolution can still fail when a unit has no PIC and none exists globally.
     * Keep the column nullable so session creation never fails on an unassigned PIC.
     */
    public function up(): void
    {
        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->foreignId('pic_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->foreignId('pic_id')->constrained('users')->cascadeOnDelete()->change();
        });
    }
};
