<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risks', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('control_id')->constrained('work_units')->nullOnDelete();
            $table->date('deadline')->nullable()->after('rencana_mitigasi');
            $table->text('catatan_admin')->nullable()->after('deadline');

            $table->index(['unit_id', 'status']);
            $table->index(['control_id', 'level_risiko']);
        });
    }

    public function down(): void
    {
        Schema::table('risks', function (Blueprint $table) {
            $table->dropIndex(['unit_id', 'status']);
            $table->dropIndex(['control_id', 'level_risiko']);
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['deadline', 'catatan_admin']);
        });
    }
};
