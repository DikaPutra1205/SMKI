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

            // ── Admin Kepatuhan & other roles ───────────────────────────
            [
                'label' => 'Dashboard',
                'url' => '/admin/kepatuhan/dashboard',
                'icon' => 'LayoutGrid',
                'permissions' => ['dashboard.read', 'audit-log.view'],
                'denies' => ['work-unit.view'],
            ],
            [
                'label' => 'Compliance',
                'icon' => 'ShieldCheck',
                'permissions' => ['control.view'],
                'denies' => ['work-unit.view'],
                'children' => [
                    [
                        'label' => 'Controls Management',
                        'url' => '/admin/kepatuhan/compliance',
                        'permissions' => ['control.view'],
                    ],
                    [
                        'label' => 'Assessment PIC',
                        'url' => '/admin/kepatuhan/sessions',
                        'permissions' => ['control.view'],
                    ],
                ],
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
