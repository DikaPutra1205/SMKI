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

        $nav = array_values(array_filter($nav, function (array $entry) use ($user) {
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

        return $this->sortEntries($nav);
    }

    private function sortEntries(array $entries): array
    {
        foreach ($entries as &$entry) {
            if (isset($entry['children'])) {
                $entry['children'] = $this->sortEntries($entry['children']);
            }
        }
        unset($entry);

        usort($entries, function (array $left, array $right): int {
            $order = $this->entryPriority($left) <=> $this->entryPriority($right);

            if ($order !== 0) {
                return $order;
            }

            return strcmp($left['label'] ?? '', $right['label'] ?? '');
        });

        return array_values($entries);
    }

    private function entryPriority(array $entry): int
    {
        $label = strtolower((string) ($entry['label'] ?? ''));

        if (str_contains($label, 'dashboard')) {
            return 0;
        }

        if (str_contains($label, 'verifikasi') || str_contains($label, 'checklist')) {
            return 10;
        }

        if (str_contains($label, 'temuan')) {
            return 20;
        }

        if (str_contains($label, 'risiko') || str_contains($label, 'risk')) {
            return 30;
        }

        if (str_contains($label, 'kontrol') || str_contains($label, 'compliance')) {
            return 40;
        }

        if (str_contains($label, 'audit')) {
            return 50;
        }

        if (str_contains($label, 'framework') || str_contains($label, 'user') || str_contains($label, 'role') || str_contains($label, 'unit') || str_contains($label, 'sesi')) {
            return 100;
        }

        return 200;
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
            [
                'label' => 'Manajemen Unit',
                'url' => '/admin/superadmin/units',
                'icon' => 'Building2',
                'permissions' => ['work-unit.view'],
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
                'label' => 'Verifikasi Checklists',
                'url' => '/admin/kepatuhan/checklist/verify',
                'icon' => 'ClipboardCheck',
                'permissions' => ['checklist.view', 'audit-log.view'],
                'denies' => ['work-unit.view'],
            ],

            // ── PIC Satuan Kerja ──────────────────────────────────────
            [
                'label' => 'Checklist',
                'url' => '/checklist',
                'icon' => 'ClipboardCheck',
                'permissions' => ['checklist-session.read'],
                'denies' => ['control.view', 'audit-log.view'],
            ],

            // ── LANJOOT Satuan Kerja ──────────────────────────────────────
            [
                'label' => 'Temuan',
                'url' => '/temuan',
                'icon' => 'AlertCircle',
                'permissions' => ['finding.view'],
            ],
            [
                'label' => 'Register Risiko',
                'url' => '/risks',
                'icon' => 'AlertTriangle',
                'permissions' => ['risk.view'],
                'denies' => ['work-unit.view'],
            ],

            [
                'label' => 'Manajemen Sesi Checklist',
                'url' => '/admin/kepatuhan/sessions',
                'icon' => 'ClipboardList',
                'permissions' => ['checklist-session.view'],
            ],

            [
                'label' => 'Manajemen Kontrol',
                'url' => '/compliance',
                'icon' => 'ShieldCheck',
                'permissions' => ['control.view'],
            ],

            [
                'label' => 'Audit Log',
                'url' => '/audit-logs',
                'icon' => 'History',
                'permissions' => ['audit-log.view'],
            ],
        ];
    }
}
