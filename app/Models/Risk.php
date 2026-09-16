<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Risk extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'level_risiko',
        'pemilik_risiko',
        'rencana_mitigasi',
        'status',
        'deadline',
        'catatan_admin',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    // level_risiko: low, medium, high, critical
    const LEVEL_LOW = 'low';

    const LEVEL_MEDIUM = 'medium';

    const LEVEL_HIGH = 'high';

    const LEVEL_CRITICAL = 'critical';

    // status: open, mitigated, accepted
    const STATUS_OPEN = 'open';

    const STATUS_MITIGATED = 'mitigated';

    const STATUS_ACCEPTED = 'accepted';

    /**
     * Kontrol-kontrol yang terkait dengan risiko ini (many-to-many via pivot control_risk).
     * Satu risiko dapat dipetakan ke lebih dari satu kontrol sesuai PRD.
     */
    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_risk')
            ->withTimestamps();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class, 'unit_id');
    }

    public function getIsOverdueAttribute(): bool
    {
        if (! $this->deadline || $this->status === self::STATUS_MITIGATED || $this->status === self::STATUS_ACCEPTED) {
            return false;
        }

        return now()->startOfDay()->gt($this->deadline);
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->deadline) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->deadline, false);
    }
}
