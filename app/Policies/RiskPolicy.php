<?php

namespace App\Policies;

use App\Models\Risk;
use App\Models\User;

class RiskPolicy
{
    private function isUserAuthorizedForRisk(User $user, Risk $risk): bool
    {
        if (! $user->isPic()) {
            return true;
        }

        $scopedUnitIds = $user->accessibleUnitIds();
        if ($scopedUnitIds === null) {
            return true;
        }

        // PIC sees only own-unit risks plus unassigned (NULL unit_id) risks.
        if ($risk->unit_id === null) {
            return true;
        }

        return in_array((int) $risk->unit_id, $scopedUnitIds, true);
    }

    /**
     * Determine whether the user can view any risks.
     */
    public function viewAny(User $user, ?int $targetUnitId = null): bool
    {
        if ($user->isPic()) {
            if ($targetUnitId !== null && (int) $targetUnitId !== (int) $user->unit_id) {
                return false;
            }
        }

        return $user->hasPermissionTo('risk.read') || $user->hasPermissionTo('risk.view') || $user->isAdmin() || $user->isSuperAdmin() || $user->isKoordinator() || $user->isAuditor() || $user->isPic();
    }

    /**
     * Determine whether the user can view the specific risk.
     */
    public function view(User $user, Risk $risk): bool
    {
        if (! ($user->hasPermissionTo('risk.read') || $user->hasPermissionTo('risk.view') || $user->isAdmin() || $user->isSuperAdmin() || $user->isKoordinator() || $user->isAuditor() || $user->isPic())) {
            return false;
        }

        return $this->isUserAuthorizedForRisk($user, $risk);
    }

    /**
     * Determine whether the user can create risks.
     */
    public function create(User $user, ?int $targetUnitId = null): bool
    {
        if ($user->isPic()) {
            return false;
        }

        return $user->hasPermissionTo('risk.create') || $user->isAdmin() || $user->isSuperAdmin() || $user->isKoordinator();
    }

    /**
     * Determine whether the user can update the specific risk.
     */
    public function update(User $user, Risk $risk): bool
    {
        if ($user->isAdmin() || $user->isSuperAdmin() || $user->isKoordinator()) {
            return true;
        }

        if ($user->isPic()) {
            return $this->isUserAuthorizedForRisk($user, $risk);
        }

        if ($user->hasPermissionTo('risk.update')) {
            return $this->isUserAuthorizedForRisk($user, $risk);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the specific risk.
     */
    public function delete(User $user, Risk $risk): bool
    {
        if ($user->isPic()) {
            return false;
        }

        if (! ($user->hasPermissionTo('risk.delete') || $user->isAdmin() || $user->isSuperAdmin() || $user->isKoordinator())) {
            return false;
        }

        return $this->isUserAuthorizedForRisk($user, $risk);
    }
}
