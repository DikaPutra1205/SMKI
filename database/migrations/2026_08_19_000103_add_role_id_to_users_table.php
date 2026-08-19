<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_ROLES = [
        'superadmin' => 'Super Admin',
        'admin_kepatuhan' => 'Admin Kepatuhan',
        'koordinator_smki' => 'Koordinator SMKI',
        'auditor' => 'Auditor',
        'pic' => 'PIC',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('email')
                ->constrained('roles')
                ->restrictOnDelete();
        });

        $now = now();
        foreach (self::LEGACY_ROLES as $name => $label) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                ['label' => $label, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        DB::statement('UPDATE users SET role_id = (SELECT id FROM roles WHERE roles.name = users.role)');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('pic')->after('email');
        });

        DB::statement('UPDATE users SET role = (SELECT name FROM roles WHERE roles.id = users.role_id)');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
