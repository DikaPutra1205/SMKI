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

        return true;
    }

    public function view(User $user, Finding $finding): bool
    {
        return $this->isUserAuthorizedForFinding($user, $finding);
    }

    /**
     * Initial finding creation is strictly for Compliance Admin and Superadmin.
     * PIC cannot create initial findings.
     */
    public function create(User $user): bool
    {
        if ($user->isPic()) {
            return false;
        }

        return $user->hasPermissionTo('finding.create') || $user->isAdminKepatuhan() || $user->isSuperAdmin();
    }

    public function update(User $user, Finding $finding): bool
    {
        return $this->isUserAuthorizedForFinding($user, $finding);
    }

    public function updateStatus(User $user, Finding $finding): bool
    {
        return $this->isUserAuthorizedForFinding($user, $finding);
    }

    public function delete(User $user, Finding $finding): bool
    {
        if ($user->isPic()) {
            return false;
        }

        return $user->hasPermissionTo('finding.delete') || $user->isAdminKepatuhan() || $user->isSuperAdmin();
    }
}
