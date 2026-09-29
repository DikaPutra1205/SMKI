<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class WorkUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['nama', 'parent_id'];

    protected static function booted(): void
    {
        static::saving(function (WorkUnit $unit) {
            $parentId = $unit->parent_id ? (int) $unit->parent_id : null;
            if ($parentId && $unit->wouldCreateCycle($parentId)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Unit tidak dapat dijadikan induk dari dirinya sendiri atau turunannya.',
                ]);
            }
        });
    }

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

    public function getDescendantIds(): array
    {
        $ids = [];
        $this->loadMissing('children');

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }

        return $ids;
    }

    public function isAncestorOf(WorkUnit $unit): bool
    {
        if ($this->id === $unit->id) {
            return false;
        }

        return in_array($unit->id, $this->getDescendantIds(), true);
    }

    /** True jika $parentId adalah $unit itu sendiri atau salah satu turunannya. */
    public function wouldCreateCycle(int $parentId): bool
    {
        if ($this->id !== null && $parentId === (int) $this->id) {
            return true;
        }

        $candidate = $parentId ? self::find($parentId) : null;
        while ($candidate?->parent_id) {
            if ($this->id !== null && (int) $candidate->parent_id === (int) $this->id) {
                return true;
            }
            $candidate = $candidate->parent;
        }

        return false;
    }
}
