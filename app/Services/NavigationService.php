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
     * @return array<int, array{label: string, url?: string, icon?: string, order?: int, permissions: array<string>, denies?: array<string>, children?: array<int, array{label: string, url: string, permissions: array<string>}>}>
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
            $order = ($left['order'] ?? 200) <=> ($right['order'] ?? 200);

            if ($order !== 0) {
                return $order;
            }

            return strcmp($left['label'] ?? '', $right['label'] ?? '');
        });

        return array_values($entries);
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
     * Canonical order: Dashboard, Checklist, Verifikasi Checklists, Temuan,
     * Register Risiko, manajemen block (Sesi, Kontrol, Framework, Role, Unit,
     * User), Audit Log. Filtering preserves relative order per role.
     *
     * @return array<int, array{label: string, url?: string, icon?: string, order?: int, permissions: array<string>, denies?: array<string>, children?: array<int, array{label: string, url: string, permissions: array<string>}>}>
     */
    private function all(): array
    {
        return [
            // ── Superadmin ──────────────────────────────────────────────
            [
                'label' => 'Dashboard',
                'url' => '/dashboard',
                'icon' => 'LayoutGrid',
                'order' => 0,
                'permissions' => ['work-unit.view'],
            ],
            [
                'label' => 'Manajemen Framework',
                'url' => '/frameworks',
                'icon' => 'Database',
                'order' => 102,
                'permissions' => ['framework.view', 'work-unit.view'],
            ],
            [
                'label' => 'Manajemen User',
                'url' => '/users',
                'icon' => 'Users',
                'order' => 105,
                'permissions' => ['user.managementview'],
            ],
            [
                'label' => 'Manajemen Role',
                'url' => '/roles',
                'icon' => 'Shield',
                'order' => 103,
                'permissions' => ['role.managementview'],
            ],
            [
                'label' => 'Manajemen Unit',
                'url' => '/admin/superadmin/units',
                'icon' => 'Building2',
                'order' => 104,
                'permissions' => ['work-unit.view'],
            ],

            // ── PIC / generic dashboard (requires only dashboard.read) ──
            [
                'label' => 'Dashboard',
                'url' => '/dashboard',
                'icon' => 'LayoutGrid',
                'order' => 0,
                'permissions' => ['dashboard.read'],
                'denies' => ['work-unit.view', 'audit-log.view'],
            ],
            // ── Admin Kepatuhan & other roles ───────────────────────────
            [
                'label' => 'Dashboard',
                'url' => '/dashboard',
                'icon' => 'LayoutGrid',
                'order' => 0,
                'permissions' => ['dashboard.read', 'audit-log.view'],
                'denies' => ['work-unit.view'],
            ],
            [
                'label' => 'Verifikasi Checklists',
                'url' => '/admin/kepatuhan/checklist/verify',
                'icon' => 'ClipboardCheck',
                'order' => 11,
                'permissions' => ['checklist.view', 'audit-log.view'],
                'denies' => ['work-unit.view'],
            ],

            // ── PIC Satuan Kerja ──────────────────────────────────────
            [
                'label' => 'Checklist',
                'url' => '/checklist',
                'icon' => 'ClipboardCheck',
                'order' => 10,
                'permissions' => ['checklist-session.read'],
                'denies' => ['control.view', 'audit-log.view'],
            ],

            // ── LANJOOT Satuan Kerja ──────────────────────────────────────
            [
                'label' => 'Temuan',
                'url' => '/temuan',
                'icon' => 'AlertCircle',
                'order' => 20,
                'permissions' => ['finding.view'],
            ],
            [
                'label' => 'Register Risiko',
                'url' => '/risks',
                'icon' => 'AlertTriangle',
                'order' => 30,
                'permissions' => ['risk.view'],
            ],

            [
                'label' => 'Manajemen Sesi Checklist',
                'url' => '/admin/kepatuhan/sessions',
                'icon' => 'ClipboardList',
                'order' => 100,
                'permissions' => ['checklist-session.view'],
            ],

            [
                'label' => 'Manajemen Kontrol',
                'url' => '/compliance',
                'icon' => 'ShieldCheck',
                'order' => 101,
                'permissions' => ['control.view'],
            ],

            [
                'label' => 'Audit Log',
                'url' => '/audit-logs',
                'icon' => 'History',
                'order' => 200,
                'permissions' => ['audit-log.view'],
            ],
        ];
    }
}
