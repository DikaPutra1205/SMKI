<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Control extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'framework_id',
        'kode_klausul',
        'judul',
        'deskripsi',
        'kategori',
    ];

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }

    public function checklistEntries(): HasMany
    {
        return $this->hasMany(ChecklistEntry::class, 'control_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'control_id');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'control_id');
    }
}
