<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Hanya created_at, tidak pakai updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'actor_id',
        'aksi',
        'detail_perubahan',
    ];

    protected $casts = [
        'detail_perubahan' => 'array',
        'created_at'       => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Helper static method untuk mencatat log dengan mudah dari manapun.
     */
    public static function catat(
        string $entityType,
        int $entityId,
        string $aksi,
        ?int $actorId = null,
        ?array $detail = null
    ): self {
        return static::create([
            'entity_type'      => $entityType,
            'entity_id'        => $entityId,
            'actor_id'         => $actorId,
            'aksi'             => $aksi,
            'detail_perubahan' => $detail,
        ]);
    }
}
