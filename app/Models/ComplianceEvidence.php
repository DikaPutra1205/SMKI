<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ComplianceEvidence extends Model
{
    use SoftDeletes;

    protected $table = 'compliance_evidences';

    protected $fillable = [
        'checklist_entry_id',
        'uploaded_by',
        'file_url',
        'version_number',
        'is_active',
        'uploaded_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'uploaded_at' => 'datetime',
    ];

    public function checklistEntry(): BelongsTo
    {
        return $this->belongsTo(ChecklistEntry::class, 'checklist_entry_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected $appends = ['label_revisi', 'file_url', 'nama_file'];

    public function getLabelRevisiAttribute(): string
    {
        if ($this->version_number === 1) {
            return 'Dokumen Awal (Revisi ke-1)';
        }

        return "Dokumen Pembaruan (Revisi ke-{$this->version_number})";
    }

    public function getNamaFileAttribute(): string
    {
        return rawurldecode(basename($this->attributes['file_url'] ?? ''));
    }

    public function getFileUrlAttribute(): string
    {
        $key = $this->attributes['file_url'] ?? '';

        if (! $key) {
            return '';
        }

        if (filter_var($key, FILTER_VALIDATE_URL)) {
            return $key;
        }

        try {
            return Storage::disk('supabase')->temporaryUrl($key, now()->addMinutes(30));
        } catch (\Throwable $e) {
            report($e);

            return $key;
        }
    }

    /**
     * Sebelum menyimpan versi baru, nonaktifkan (is_active = false) versi sebelumnya
     * tanpa menghapusnya, sehingga seluruh riwayat dokumen tetap utuh dan aman.
     */
    protected static function booted(): void
    {
        static::creating(function (ComplianceEvidence $evidence) {
            if ($evidence->is_active) {
                static::where('checklist_entry_id', $evidence->checklist_entry_id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }
}
