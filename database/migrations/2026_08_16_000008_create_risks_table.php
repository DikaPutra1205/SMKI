<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete();
            $table->string('level_risiko')->default('low');
            // level_risiko: low, medium, high, critical
            $table->string('pemilik_risiko');
            $table->text('rencana_mitigasi')->nullable();
            $table->string('status')->default('open');
            // status: open, mitigated, accepted
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};
