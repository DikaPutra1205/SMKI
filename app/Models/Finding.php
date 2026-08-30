<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Finding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'control_id',
        'unit_id',
        'pic_id',
        'admin_id',
        'kategori',
        'status',
        'deadline',
        'catatan_admin',
        'tanggal_verifikasi',
    ];

    protected $casts = [
        'deadline' => 'date:Y-m-d',
        'tanggal_verifikasi' => 'datetime',
    ];

    // kategori: major, minor, observasi
    const KATEGORI_MAJOR = 'major';

    const KATEGORI_MINOR = 'minor';

    const KATEGORI_OBSERVASI = 'observasi';

    // status: open, in_progress, resolved, closed
    const STATUS_OPEN = 'open';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_RESOLVED = 'resolved';

    const STATUS_CLOSED = 'closed';

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

    public function histories()
    {
        return $this->hasMany(FindingStatusHistory::class)->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }
}
