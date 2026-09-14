<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controls', function (Blueprint $table) {
            $table->string('domain_peran')->nullable()->after('kategori');
            $table->index('domain_peran');
        });
    }

    public function down(): void
    {
        Schema::table('controls', function (Blueprint $table) {
            $table->dropIndex(['domain_peran']);
            $table->dropColumn('domain_peran');
        });
    }
};
