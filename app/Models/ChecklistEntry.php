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
        'level_maturity',
        'catatan',
        'catatan_admin',
        'tanggal_input',
        'tanggal_verifikasi',
    ];

    protected $casts = [
        'level_maturity' => 'integer',
        'tanggal_input' => 'datetime',
        'tanggal_verifikasi' => 'datetime',
    ];

    // status: belum_dimulai, dalam_proses, dalam_tinjauan, selesai_diterapkan, tidak_berlaku
    const WORKFLOW_BELUM_DIMULAI = 'belum_dimulai';

    const WORKFLOW_DALAM_PROSES = 'dalam_proses';

    const WORKFLOW_DALAM_TINJAUAN = 'dalam_tinjauan';

    const WORKFLOW_SELESAI = 'selesai_diterapkan';

    const WORKFLOW_TIDAK_BERLAKU = 'tidak_berlaku';

    public static function workflowValues(): array
    {
        return [
            self::WORKFLOW_BELUM_DIMULAI,
            self::WORKFLOW_DALAM_PROSES,
            self::WORKFLOW_DALAM_TINJAUAN,
            self::WORKFLOW_SELESAI,
            self::WORKFLOW_TIDAK_BERLAKU,
        ];
    }

    public static function resolvePicWorkflow(bool $hasCatatan, bool $hasBukti): string
    {
        if ($hasCatatan && $hasBukti) {
            return self::WORKFLOW_DALAM_TINJAUAN;
        }

        if ($hasCatatan || $hasBukti) {
            return self::WORKFLOW_DALAM_PROSES;
        }

        return self::WORKFLOW_BELUM_DIMULAI;
    }

    public function applyPicTouch(?string $catatan, bool $hasBukti, bool $naSelected): string
    {
        if ($naSelected) {
            return self::WORKFLOW_TIDAK_BERLAKU;
        }

        return self::resolvePicWorkflow(trim((string) $catatan) !== '', $hasBukti);
    }

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
