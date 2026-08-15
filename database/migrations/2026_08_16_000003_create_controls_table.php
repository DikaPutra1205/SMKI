<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('framework_id')->constrained('frameworks')->cascadeOnDelete();
            $table->string('kode_klausul');
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('kategori'); // 'annex_a' | 'klausul_4_10'
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controls');
    }
};
