<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\User;

class FindingPolicy
{
    private function isUserAuthorizedForFinding(User $user, Finding $finding): bool
    {
        if (! $user->isPic()) {
            return true;
        }

        if ($user->unit_id !== null) {
            return (int) $finding->unit_id === (int) $user->unit_id;
        }

        return (int) $finding->pic_id === (int) $user->id;
    }

    public function viewAny(User $user, ?int $targetUnitId = null): bool
    {
        if ($user->isPic()) {
            if ($targetUnitId !== null && (int) $targetUnitId !== (int) $user->unit_id) {
                return false;
            }
        }

        return $user->hasPermissionTo('finding.read') || $user->hasPermissionTo('finding.view') || $user->isAdmin() || $user->isSuperAdmin() || $user->isKoordinator() || $user->isAuditor() || $user->isPic();
    }

    public function view(User $user, Finding $finding): bool
    {
        if (! ($user->hasPermissionTo('finding.read') || $user->hasPermissionTo('finding.view') || $user->isAdmin() || $user->isSuperAdmin() || $user->isKoordinator() || $user->isAuditor() || $user->isPic())) {
            return false;
        }

        return $this->isUserAuthorizedForFinding($user, $finding);
    }

    /**
     * Initial finding creation is strictly for Compliance Admin and Superadmin.
     * PIC, Auditor, and Koordinator SMKI cannot create initial findings.
     */
    public function create(User $user): bool
    {
        if ($user->isPic()) {
            return false;
        }

        return $user->hasPermissionTo('finding.create') || $user->isAdmin() || $user->isSuperAdmin();
    }

    /**
     * Update is strictly allowed for Admin Kepatuhan, Super Admin, and the assigned PIC.
     * Auditor and Koordinator SMKI have read-only access.
     */
    public function update(User $user, Finding $finding): bool
    {
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if ($user->isPic()) {
            return $this->isUserAuthorizedForFinding($user, $finding);
        }

        if ($user->hasPermissionTo('finding.update')) {
            return $this->isUserAuthorizedForFinding($user, $finding);
        }

        return false;
    }

    /**
     * Update finding status is strictly allowed for Admin Kepatuhan, Super Admin, and the assigned PIC.
     */
    public function updateStatus(User $user, Finding $finding): bool
    {
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if ($user->isPic()) {
            return $this->isUserAuthorizedForFinding($user, $finding);
        }

        if ($user->hasPermissionTo('finding.update-status') || $user->hasPermissionTo('finding.update')) {
            return $this->isUserAuthorizedForFinding($user, $finding);
        }

        return false;
    }

    /**
     * Delete finding is strictly for Compliance Admin and Superadmin.
     */
    public function delete(User $user, Finding $finding): bool
    {
        if ($user->isPic()) {
            return false;
        }

        return $user->hasPermissionTo('finding.delete') || $user->isAdmin() || $user->isSuperAdmin();
    }
}
