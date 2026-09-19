<?php

namespace App\Policies;

use App\Models\ChecklistEntry;
use App\Models\User;
use App\Models\WorkUnit;

class ChecklistEntryPolicy
{
    private function isUserAuthorizedForEntry(User $user, ChecklistEntry $checklistEntry): bool
    {
        if (! $user->isPic()) {
            return true;
        }

        if ((int) $checklistEntry->unit_id === (int) $user->unit_id) {
            return true;
        }

        if ($user->unit_id !== null) {
            $userUnit = $user->unit()->first();
            $entryUnit = $checklistEntry->unit()->first();

            if ($userUnit && $entryUnit && $userUnit->isAncestorOf($entryUnit)) {
                return true;
            }
        }

        return (int) $checklistEntry->pic_id === (int) $user->id;
    }

    /**
     * Determine whether the user can view any checklist entries.
     */
    public function viewAny(User $user, ?int $targetUnitId = null): bool
    {
        if (! $user->isPic()) {
            return true;
        }

        if ($targetUnitId === null || (int) $targetUnitId === (int) $user->unit_id) {
            return true;
        }

        $userUnit = WorkUnit::find($user->unit_id);
        $targetUnit = WorkUnit::find($targetUnitId);

        if ($userUnit && $targetUnit && $userUnit->isAncestorOf($targetUnit)) {
            return true;
        }

        return false;
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
        if (! $user->isPic()) {
            return true;
        }

        if ($targetUnitId === null || (int) $targetUnitId === (int) $user->unit_id) {
            return true;
        }

        $userUnit = WorkUnit::find($user->unit_id);
        $targetUnit = WorkUnit::find($targetUnitId);

        if ($userUnit && $targetUnit && $userUnit->isAncestorOf($targetUnit)) {
            return true;
        }

        return false;
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
        if ($user->isPic()) {
            return false;
        }

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
