<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'session_id',
        'control_id',
        'unit_id',
        'pic_id',
        'admin_id',
        'status',
        'catatan',
        'catatan_admin',
        'tanggal_input',
        'tanggal_verifikasi',
    ];

    protected $casts = [
        'tanggal_input' => 'datetime',
        'tanggal_verifikasi' => 'datetime',
    ];

    // status: compliant, partial, non_compliant, na
    const STATUS_COMPLIANT = 'compliant';

    const STATUS_PARTIAL = 'partial';

    const STATUS_NON_COMPLIANT = 'non_compliant';

    const STATUS_NA = 'na';

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChecklistSession::class, 'session_id');
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class, 'unit_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(ComplianceEvidence::class, 'checklist_entry_id');
    }

    public function activeEvidence()
    {
        return $this->hasOne(ComplianceEvidence::class, 'checklist_entry_id')
            ->where('is_active', true)
            ->latest('version_number');
    }
}
