<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('work_units')->cascadeOnDelete();
            $table->foreignId('pic_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('non_compliant');
            // status: compliant, partial, non_compliant, na
            $table->text('catatan')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamp('tanggal_input')->nullable();
            $table->timestamp('tanggal_verifikasi')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_entries');
    }
};
