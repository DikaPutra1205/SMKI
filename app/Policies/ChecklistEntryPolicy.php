<?php

namespace App\Policies;

use App\Models\ChecklistEntry;
use App\Models\User;

class ChecklistEntryPolicy
{
    private function isUserAuthorizedForEntry(User $user, ChecklistEntry $checklistEntry): bool
    {
        if (! $user->isPic()) {
            return true;
        }

        if ($user->unit_id !== null) {
            return (int) $checklistEntry->unit_id === (int) $user->unit_id;
        }

        return (int) $checklistEntry->pic_id === (int) $user->id;
    }

    /**
     * Determine whether the user can view any checklist entries.
     */
    public function viewAny(User $user, ?int $targetUnitId = null): bool
    {
        if ($user->isPic()) {
            if ($targetUnitId !== null && (int) $targetUnitId !== (int) $user->unit_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can view the checklist entry.
     */
    public function view(User $user, ChecklistEntry $checklistEntry): bool
    {
        return $this->isUserAuthorizedForEntry($user, $checklistEntry);
    }

    /**
     * Determine whether the user can create checklist entries.
     */
    public function create(User $user, ?int $targetUnitId = null): bool
    {
        if ($user->isPic()) {
            if ($targetUnitId !== null && (int) $targetUnitId !== (int) $user->unit_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can update the checklist entry.
     */
    public function update(User $user, ChecklistEntry $checklistEntry): bool
    {
        return $this->isUserAuthorizedForEntry($user, $checklistEntry);
    }

    /**
     * Determine whether the user can verify the checklist entry.
     */
    public function verify(User $user, ChecklistEntry $checklistEntry): bool
    {
        return $this->isUserAuthorizedForEntry($user, $checklistEntry);
    }

    /**
     * Determine whether the user can delete the checklist entry.
     */
    public function delete(User $user, ChecklistEntry $checklistEntry): bool
    {
        return $this->isUserAuthorizedForEntry($user, $checklistEntry);
    }

    /**
     * Determine whether the user can restore the checklist entry.
     */
    public function restore(User $user, ChecklistEntry $checklistEntry): bool
    {
        return $this->isUserAuthorizedForEntry($user, $checklistEntry);
    }

    /**
     * Determine whether the user can upload evidence for the checklist entry.
     */
    public function uploadEvidence(User $user, ChecklistEntry $checklistEntry, ?int $uploadedBy = null): bool
    {
        if (! $this->isUserAuthorizedForEntry($user, $checklistEntry)) {
            return false;
        }

        if ($user->isPic() && $uploadedBy !== null && (int) $uploadedBy !== (int) $user->id) {
            return false;
        }

        return true;
    }
}
