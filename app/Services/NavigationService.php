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
                'url' => '/dashboard',
                'icon' => 'LayoutGrid',
                'permissions' => ['work-unit.view'],
            ],
            [
                'label' => 'Manajemen Framework',
                'url' => '/frameworks',
                'icon' => 'Database',
                'permissions' => ['framework.view', 'work-unit.view'],
            ],
            [
                'label' => 'Manajemen User',
                'url' => '/users',
                'icon' => 'Users',
                'permissions' => ['user.managementview'],
            ],
            [
                'label' => 'Manajemen Role',
                'url' => '/roles',
                'icon' => 'Shield',
                'permissions' => ['role.managementview'],
            ],

            // ── PIC / generic dashboard (requires only dashboard.read) ──
            [
                'label' => 'Dashboard',
                'url' => '/dashboard',
                'icon' => 'LayoutGrid',
                'permissions' => ['dashboard.read'],
                'denies' => ['work-unit.view', 'audit-log.view'],
            ],
            // ── Admin Kepatuhan & other roles ───────────────────────────
            [
                'label' => 'Dashboard',
                'url' => '/dashboard',
                'icon' => 'LayoutGrid',
                'permissions' => ['dashboard.read', 'audit-log.view'],
                'denies' => ['work-unit.view'],
            ],
            [
                'label' => 'Manajemen Kontrol',
                'url' => '/compliance',
                'icon' => 'ShieldCheck',
                'permissions' => ['control.view'],
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
                'label' => 'Audit Log',
                'url' => '/audit-logs',
                'icon' => 'History',
                'permissions' => ['audit-log.view'],
            ],

            // ── PIC Satuan Kerja ──────────────────────────────────────
            [
                'label' => 'Assessment',
                'url' => '/assessments',
                'icon' => 'ClipboardCheck',
                'permissions' => ['checklist-session.create'],
                'denies' => ['control.view'],
            ],

            // ── LANJOOT Satuan Kerja ──────────────────────────────────────
            [
                'label' => 'Findings',
                'url' => '/findings',
                'icon' => 'AlertCircle',
                'permissions' => ['finding.view'],
                'denies' => ['work-unit.view'],
            ],
            [
                'label' => 'Risks',
                'url' => '/risks',
                'icon' => 'AlertTriangle',
                'permissions' => ['risk.view'],
                'denies' => ['work-unit.view'],
            ],
        ];
    }
}
