<?php

namespace App\Services;

use App\Models\User;

class NavigationService
{
    /**
     * Get navigation entries for the given user, filtered by permission.
     *
     * An entry is shown when ALL its `permissions` are granted and NONE of
     * its `denies` are present.
     *
     * @return array<int, array{label: string, url?: string, icon?: string, permissions: array<string>, denies?: array<string>, children?: array<int, array{label: string, url: string, permissions: array<string>}>}>
     */
    public function getForUser(User $user): array
    {
        $nav = $this->all();

        return array_values(array_filter($nav, function (array $entry) use ($user) {
            if (! $this->hasAll($user, $entry['permissions'])) {
                return false;
            }

            if ($this->hasAny($user, $entry['denies'] ?? [])) {
                return false;
            }

            if (isset($entry['children'])) {
                $entry['children'] = array_values(array_filter($entry['children'], function (array $child) use ($user) {
                    return $this->hasAll($user, $child['permissions']);
                }));

                return count($entry['children']) > 0;
            }

            return true;
        }));
    }

    private function hasAll(User $user, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! $user->hasPermissionTo($key)) {
                return false;
            }
        }

        return true;
    }

    private function hasAny(User $user, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($user->hasPermissionTo($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Full navigation definition. Add new items here — frontend picks them up automatically.
     *
     * @return array<int, array{label: string, url?: string, icon?: string, permissions: array<string>, denies?: array<string>, children?: array<int, array{label: string, url: string, permissions: array<string>}>}>
     */
    private function all(): array
    {
        return [
            // ── Superadmin ──────────────────────────────────────────────
            [
                'label' => 'Dashboard',
                'url' => '/admin/superadmin/dashboard',
                'icon' => 'LayoutGrid',
                'permissions' => ['work-unit.view'],
            ],
            [
                'label' => 'Framework Management',
                'url' => '/admin/superadmin/frameworks',
                'icon' => 'Database',
                'permissions' => ['framework.view', 'work-unit.view'],
            ],
            [
                'label' => 'User Management',
                'url' => '/admin/superadmin/users',
                'icon' => 'Users',
                'permissions' => ['user.managementview'],
            ],
            [
                'label' => 'Role Management',
                'url' => '/admin/superadmin/roles',
                'icon' => 'Shield',
                'permissions' => ['role.managementview'],
            ],

            // ── Admin Kepatuhan & other roles ───────────────────────────
            [
                'label' => 'Dashboard',
                'url' => '/admin/kepatuhan/dashboard',
                'icon' => 'LayoutGrid',
                'permissions' => ['dashboard.read', 'audit-log.view'],
                'denies' => ['work-unit.view'],
            ],
            [
                'label' => 'Controls Management',
                'url' => '/admin/kepatuhan/compliance',
                'icon' => 'ShieldCheck',
                'permissions' => ['control.view'],
                'denies' => ['work-unit.view'],
            ],
            [
                'label' => 'Verifikasi Entri',
                'url' => '/admin/kepatuhan/checklist/verify',
                'icon' => 'ClipboardCheck',
                'permissions' => ['checklist.bulk-verify'],
                'denies' => ['work-unit.view'],
            ],
            [
                'label' => 'Bulk Verify',
                'url' => '/admin/kepatuhan/checklist/bulk-verify',
                'icon' => 'CheckSquare',
                'permissions' => ['checklist.bulk-verify'],
                'denies' => ['work-unit.view'],
            ],

            [
                'label'       => 'Audit Log',
                'url'         => '/admin/kepatuhan/audit-logs',
                'icon'        => 'History',
                'permissions' => ['audit-log.view'],
                'denies'      => ['work-unit.view'],
            ],
            [
                'label'       => 'Findings',
                'url'         => '/admin/kepatuhan/findings',
                'icon'        => 'AlertCircle',
                'permissions' => ['finding.view'],
                'denies'      => ['work-unit.view'],
            ],
            [
                'label'       => 'Risks',
                'url'         => '/admin/kepatuhan/risks',
                'icon'        => 'AlertTriangle',
                'permissions' => ['risk.view'],
                'denies'      => ['work-unit.view'],
            ],

            // ── PIC Satuan Kerja ──────────────────────────────────────
            [
                'label' => 'Assessment',
                'url' => '/admin/pic/assessments',
                'icon' => 'ClipboardCheck',
                'permissions' => ['checklist-session.create'],
                'denies' => ['control.view'],
            ],

        ];
    }
}
