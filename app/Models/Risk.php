<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Risk extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'control_id',
        'level_risiko',
        'pemilik_risiko',
        'rencana_mitigasi',
        'status',
    ];

    // level_risiko: low, medium, high, critical
    const LEVEL_LOW      = 'low';
    const LEVEL_MEDIUM   = 'medium';
    const LEVEL_HIGH     = 'high';
    const LEVEL_CRITICAL = 'critical';

    // status: open, mitigated, accepted
    const STATUS_OPEN      = 'open';
    const STATUS_MITIGATED = 'mitigated';
    const STATUS_ACCEPTED  = 'accepted';

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }
}
