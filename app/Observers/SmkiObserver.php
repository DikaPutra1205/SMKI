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
        $data = $model->toArray();
        if (! empty($model->getHidden())) {
            $hidden = array_flip($model->getHidden());
            $data = array_diff_key($data, $hidden);
        }

        AuditLog::catat(
            entityType: class_basename($model),
            entityId: $model->getKey(),
            aksi: 'create',
            actorId: Auth::id(),
            detail: ['data' => $data],
        );
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']); // abaikan perubahan timestamp saja

        if (! empty($model->getHidden())) {
            $hidden = array_flip($model->getHidden());
            $changes = array_diff_key($changes, $hidden);
        }

        if (empty($changes)) {
            return;
        }

        $original = $model->getOriginal();
        if (! empty($model->getHidden())) {
            $hidden = array_flip($model->getHidden());
            $original = array_diff_key($original, $hidden);
        }

        AuditLog::catat(
            entityType: class_basename($model),
            entityId: $model->getKey(),
            aksi: 'update',
            actorId: Auth::id(),
            detail: [
                'before' => array_intersect_key($original, $changes),
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
