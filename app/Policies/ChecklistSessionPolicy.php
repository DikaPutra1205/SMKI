<?php

namespace App\Policies;

use App\Models\ChecklistSession;
use App\Models\User;

class ChecklistSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('checklist-session.view') || $user->hasPermissionTo('checklist-session.read');
    }

    public function view(User $user, ChecklistSession $session): bool
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        if ((int) $session->unit_id === (int) $user->unit_id) {
            return true;
        }

        $userUnit = $user->unit()->first();
        $sessionUnit = $session->unit()->first();

        return $userUnit && $sessionUnit && $userUnit->isAncestorOf($sessionUnit);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('checklist-session.create');
    }

    public function update(User $user, ChecklistSession $session): bool
    {
        if (! $user->hasPermissionTo('checklist-session.update')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        return (int) $session->unit_id === (int) $user->unit_id;
    }

    public function delete(User $user, ChecklistSession $session): bool
    {
        if (! $user->hasPermissionTo('checklist-session.delete')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        return (int) $session->unit_id === (int) $user->unit_id;
    }

    public function restore(User $user, ChecklistSession $session): bool
    {
        if (! $user->hasPermissionTo('checklist-session.restore')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        return (int) $session->unit_id === (int) $user->unit_id;
    }
}
