<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('konteks_penilaian');
            $table->string('periode')->nullable();
            $table->foreignId('unit_id')->constrained('work_units')->cascadeOnDelete();
            $table->foreignId('framework_id')->nullable()->constrained('frameworks')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'periode']);
            $table->index(['framework_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_sessions');
    }
};
