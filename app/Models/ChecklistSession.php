<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'konteks_penilaian',
        'periode',
        'unit_id',
        'framework_id',
        'created_by',
        'updated_by',
        'catatan',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class, 'unit_id');
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class, 'framework_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ChecklistEntry::class, 'session_id');
    }

    public function getSummaryAttribute(): array
    {
        $stats = $this->entries()
            ->selectRaw('
                COUNT(*) as total_entries,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as selesai,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as tinjauan,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as proses,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as belum,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as na_terverifikasi,
                SUM(CASE WHEN tanggal_verifikasi IS NOT NULL THEN 1 ELSE 0 END) as verified_entries,
                SUM(CASE WHEN status = ? OR (status IN (?, ?, ?) AND catatan IS NOT NULL AND catatan != ?) THEN 1 ELSE 0 END) as completed_entries
            ', [
                ChecklistEntry::WORKFLOW_SELESAI,
                ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
                ChecklistEntry::WORKFLOW_DALAM_PROSES,
                ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
                ChecklistEntry::WORKFLOW_SELESAI,
                ChecklistEntry::WORKFLOW_DALAM_PROSES,
                ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
                ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
                '',
            ])
            ->first();

        $total = (int) $stats->total_entries;
        $selesai = (int) $stats->selesai;
        $completed = (int) $stats->completed_entries;

        return [
            'total_entries' => $total,
            'selesai_entries' => $selesai,
            'tinjauan_entries' => (int) $stats->tinjauan,
            'proses_entries' => (int) $stats->proses,
            'belum_entries' => (int) $stats->belum,
            'na_entries' => (int) $stats->na_terverifikasi,
            'verified_entries' => (int) $stats->verified_entries,
            'completed' => $completed,
            'completion_percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }
}
