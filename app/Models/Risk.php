<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Risk extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'control_id',
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

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
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
