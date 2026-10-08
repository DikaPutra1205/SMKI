<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\User;
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
        $rawChanges = $model->getChanges();
        unset($rawChanges['updated_at']); // abaikan perubahan timestamp saja

        // Password-only updates vanish below (password is hidden), so detect
        // the credential change before stripping hidden attributes.
        $passwordChanged = $model instanceof User && array_key_exists('password', $rawChanges);

        $changes = $rawChanges;
        if (! empty($model->getHidden())) {
            $hidden = array_flip($model->getHidden());
            $changes = array_diff_key($changes, $hidden);
        }

        if (empty($changes)) {
            if ($passwordChanged) {
                $this->auditPasswordChange($model);
            }

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

        if ($passwordChanged) {
            $this->auditPasswordChange($model);
        }
    }

    /**
     * Credential changes never carry the hash — only the fact that a
     * password was changed, by whom, and from where.
     */
    private function auditPasswordChange(User $user): void
    {
        AuditLog::catat(
            entityType: 'User',
            entityId: $user->getKey(),
            aksi: 'password_changed',
            actorId: Auth::id(),
            detail: ['ip_address' => request()->ip()],
        );
    }

    /**
     * Reassign a unit's unowned checklist entries to a newly-assigned PIC.
     * Entries are only stamped with pic_id at generation time, so a PIC created
     * after generation leaves those entries NULL and uneditable by the PIC
     * (the edit/evidence gates filter on pic_id = auth user). When a PIC is
     * saved with a unit, claim the unit's still-NULL entries so the PIC can
     * edit/upload/submit them. Entries already owned by another PIC are left.
     */
    public function saved(Model $model): void
    {
        if (! $model instanceof User) {
            return;
        }

        if ($model->role === User::ROLE_PIC && $model->unit_id) {
            $claimed = ChecklistEntry::where('unit_id', $model->unit_id)
                ->whereNull('pic_id')
                ->update(['pic_id' => $model->id]);

            // Builder updates fire no model events, so the claim would be
            // invisible in the trail without this explicit row.
            if ($claimed > 0) {
                AuditLog::catat(
                    entityType: 'ChecklistEntry',
                    entityId: 0,
                    aksi: 'reassign_pic',
                    actorId: Auth::id(),
                    detail: [
                        'pic_user_id' => $model->id,
                        'unit_id' => $model->unit_id,
                        'entries_claimed' => $claimed,
                    ],
                );
            }
        }
    }

    public function deleted(Model $model): void
    {
        $data = $model->toArray();
        if (! empty($model->getHidden())) {
            $hidden = array_flip($model->getHidden());
            $data = array_diff_key($data, $hidden);
        }

        AuditLog::catat(
            entityType: class_basename($model),
            entityId: $model->getKey(),
            aksi: 'delete',
            actorId: Auth::id(),
            detail: ['data' => $data],
        );
    }
}
