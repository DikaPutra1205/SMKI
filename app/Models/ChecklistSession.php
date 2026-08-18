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
        $total = $this->entries()->count();
        $compliant = $this->entries()->where('status', ChecklistEntry::STATUS_COMPLIANT)->count();
        $partial = $this->entries()->where('status', ChecklistEntry::STATUS_PARTIAL)->count();
        $nonCompliant = $this->entries()->where('status', ChecklistEntry::STATUS_NON_COMPLIANT)->count();
        $na = $this->entries()->where('status', ChecklistEntry::STATUS_NA)->count();
        $verified = $this->entries()->whereNotNull('tanggal_verifikasi')->count();

        $percentage = $total > 0 ? (int) round(($compliant / $total) * 100) : 0;

        return [
            'total_entries' => $total,
            'compliant' => $compliant,
            'partial' => $partial,
            'non_compliant' => $nonCompliant,
            'na' => $na,
            'verified_entries' => $verified,
            'compliance_percentage' => $percentage,
        ];
    }
}
