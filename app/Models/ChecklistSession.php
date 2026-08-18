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
        'nama_sesi',
        'periode',
        'konteks_penilaian',
        'unit_id',
        'framework_id',
        'created_by',
        'auditor_id',
        'start_date',
        'end_date',
        'status',
        'catatan',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    const STATUS_DRAFT = 'draft';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_VERIFIED = 'verified';

    const STATUS_CLOSED = 'closed';

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

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ChecklistEntry::class, 'session_id');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_VERIFIED, self::STATUS_CLOSED]);
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
