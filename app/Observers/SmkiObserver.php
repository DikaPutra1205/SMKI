<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SmkiObserver
{
    /**
     * Dipakai untuk semua model SMKI agar tidak perlu buat observer terpisah.
     * Cukup register sekali per model.
     */
    public function created(Model $model): void
    {
        AuditLog::catat(
            entityType: class_basename($model),
            entityId: $model->getKey(),
            aksi: 'create',
            actorId: Auth::id(),
            detail: ['data' => $model->toArray()],
        );
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']); // abaikan perubahan timestamp saja

        if (empty($changes)) {
            return;
        }

        AuditLog::catat(
            entityType: class_basename($model),
            entityId: $model->getKey(),
            aksi: 'update',
            actorId: Auth::id(),
            detail: [
                'before' => array_intersect_key($model->getOriginal(), $changes),
                'after' => $changes,
            ],
        );
    }

    public function deleted(Model $model): void
    {
        AuditLog::catat(
            entityType: class_basename($model),
            entityId: $model->getKey(),
            aksi: 'delete',
            actorId: Auth::id(),
        );
    }
}
