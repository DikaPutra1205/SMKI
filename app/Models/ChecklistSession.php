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
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as compliant,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as partial,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as non_compliant,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as na,
                SUM(CASE WHEN tanggal_verifikasi IS NOT NULL THEN 1 ELSE 0 END) as verified_entries
            ', [
                ChecklistEntry::STATUS_COMPLIANT,
                ChecklistEntry::STATUS_PARTIAL,
                ChecklistEntry::STATUS_NON_COMPLIANT,
                ChecklistEntry::STATUS_NA,
            ])
            ->first();

        $total = (int) $stats->total_entries;
        $compliant = (int) $stats->compliant;

        return [
            'total_entries' => $total,
            'compliant' => $compliant,
            'partial' => (int) $stats->partial,
            'non_compliant' => (int) $stats->non_compliant,
            'na' => (int) $stats->na,
            'verified_entries' => (int) $stats->verified_entries,
            'compliance_percentage' => $total > 0 ? (int) round(($compliant / $total) * 100) : 0,
        ];
    }
}
