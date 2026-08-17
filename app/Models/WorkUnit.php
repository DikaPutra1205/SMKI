<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['nama', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WorkUnit::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'unit_id');
    }

    public function checklistEntries(): HasMany
    {
        return $this->hasMany(ChecklistEntry::class, 'unit_id');
    }

    public function checklistSessions(): HasMany
    {
        return $this->hasMany(ChecklistSession::class, 'unit_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'unit_id');
    }
}
