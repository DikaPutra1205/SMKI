<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->foreignId('session_id')
                ->nullable()
                ->after('id')
                ->constrained('checklist_sessions')
                ->cascadeOnDelete();

            $table->index(['session_id', 'control_id']);
            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('checklist_entries', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropIndex(['session_id', 'control_id']);
            $table->dropIndex(['session_id', 'status']);
            $table->dropColumn('session_id');
        });
    }
};
