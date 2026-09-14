<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->unsignedTinyInteger('level_maturity')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->dropColumn('level_maturity');
        });
    }
};
