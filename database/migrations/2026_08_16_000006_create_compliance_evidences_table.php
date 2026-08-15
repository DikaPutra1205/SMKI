<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_entry_id')->constrained('checklist_entries')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_url');
            $table->integer('version_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_evidences');
    }
};
