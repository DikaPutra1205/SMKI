<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('pic')->after('email');
            // role: superadmin, admin_kepatuhan, koordinator_smki, auditor, pic
            $table->foreignId('unit_id')
                  ->nullable()
                  ->after('role')
                  ->constrained('work_units')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['role', 'unit_id']);
        });
    }
};
