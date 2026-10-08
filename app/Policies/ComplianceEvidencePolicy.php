<?php

namespace App\Policies;

use App\Models\ChecklistEntry;
use App\Models\ComplianceEvidence;
use App\Models\User;

class ComplianceEvidencePolicy
{
    private function isUserAuthorizedForEntry(User $user, ChecklistEntry $checklistEntry, string $permission): bool
    {
        if ($user->isPic()) {
            if ($user->unit_id !== null) {
                if ((int) $checklistEntry->unit_id === (int) $user->unit_id) {
                    return true;
                }
                $userUnit = $user->unit()->first();
                $entryUnit = $checklistEntry->unit()->first();
                if ($userUnit && $entryUnit && $userUnit->isAncestorOf($entryUnit)) {
                    return true;
                }

                return false;
            }

            return (int) $checklistEntry->pic_id === (int) $user->id;
        }

        // Non-PIC roles need an explicit grant — never an implicit allow.
        return $user->hasPermissionTo($permission);
    }

    private function getChecklistEntry(ComplianceEvidence $complianceEvidence): ?ChecklistEntry
    {
        if ($complianceEvidence->relationLoaded('checklistEntry')) {
            return $complianceEvidence->checklistEntry;
        }

        return ChecklistEntry::withTrashed()->find($complianceEvidence->checklist_entry_id);
    }

    /**
     * Determine whether the user can view evidences for a checklist entry.
     */
    public function viewAny(User $user, ChecklistEntry $checklistEntry): bool
    {
        return $this->isUserAuthorizedForEntry($user, $checklistEntry, 'evidence.read');
    }

    /**
     * Determine whether the user can view/download a single evidence.
     */
    public function view(User $user, ComplianceEvidence $complianceEvidence): bool
    {
        $entry = $this->getChecklistEntry($complianceEvidence);

        // Orphaned rows fail closed: a missing entry never grants access.
        return $entry !== null && $this->isUserAuthorizedForEntry($user, $entry, 'evidence.read');
    }

    /**
     * Determine whether the user can create evidence for a checklist entry.
     */
    public function create(User $user, ChecklistEntry $checklistEntry, ?int $uploadedBy = null): bool
    {
        if (! $this->isUserAuthorizedForEntry($user, $checklistEntry, 'evidence.upload')) {
            return false;
        }

        if ($user->isPic() && $uploadedBy !== null && (int) $uploadedBy !== (int) $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete evidence.
     */
    public function delete(User $user, ComplianceEvidence $complianceEvidence): bool
    {
        $entry = $this->getChecklistEntry($complianceEvidence);

        return $entry !== null && $this->isUserAuthorizedForEntry($user, $entry, 'evidence.delete');
    }

    /**
     * Determine whether the user can restore evidence.
     */
    public function restore(User $user, ComplianceEvidence $complianceEvidence): bool
    {
        $entry = $this->getChecklistEntry($complianceEvidence);

        return $entry !== null && $this->isUserAuthorizedForEntry($user, $entry, 'evidence.restore');
    }
}
