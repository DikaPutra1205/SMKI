<?php

namespace App\Services;

use App\Models\User;

class NavigationService
{
    private const ROLE_SUPERADMIN = 'superadmin';

    private const ROLE_ADMIN_KEPATUHAN = 'admin_kepatuhan';

    private const ROLE_KOORDINATOR_SMKI = 'koordinator_smki';

    private const ROLE_AUDITOR = 'auditor';

    private const ROLE_PIC = 'pic';

    /**
     * Get navigation entries for the given user, filtered by role.
     *
     * @return array<int, array{label: string, url?: string, icon?: string, roles: array<string>, children?: array<int, array{label: string, url: string, roles: array<string>}>}>
     */
    public function getForUser(User $user): array
    {
        $nav = $this->all();

        return array_values(array_filter($nav, function (array $entry) use ($user) {
            if (! in_array($user->role, $entry['roles'])) {
                return false;
            }

            if (isset($entry['children'])) {
                $entry['children'] = array_values(array_filter($entry['children'], function (array $child) use ($user) {
                    return in_array($user->role, $child['roles']);
                }));

                return count($entry['children']) > 0;
            }

            return true;
        }));
    }

    /**
     * Full navigation definition. Add new items here — frontend picks them up automatically.
     *
     * @return array<int, array{label: string, url?: string, icon?: string, roles: array<string>, children?: array<int, array{label: string, url: string, roles: array<string>}>}>
     */
    private function all(): array
    {
        $allRoles = [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN_KEPATUHAN,
            self::ROLE_KOORDINATOR_SMKI,
            self::ROLE_AUDITOR,
            self::ROLE_PIC,
        ];

        return [
            // ── Superadmin ──────────────────────────────────────────────
            [
                'label' => 'Dashboard',
                'url' => '/admin/superadmin/dashboard',
                'icon' => 'LayoutGrid',
                'roles' => [self::ROLE_SUPERADMIN],
            ],
            [
                'label' => 'Framework Management',
                'url' => '/admin/superadmin/frameworks',
                'icon' => 'Database',
                'roles' => [self::ROLE_SUPERADMIN],
            ],

            // ── Admin Kepatuhan & other roles ───────────────────────────
            [
                'label' => 'Dashboard',
                'url' => '/admin/kepatuhan/dashboard',
                'icon' => 'LayoutGrid',
                'roles' => array_diff($allRoles, [self::ROLE_SUPERADMIN]),
            ],
            [
                'label' => 'Compliance',
                'icon' => 'ShieldCheck',
                'roles' => [self::ROLE_ADMIN_KEPATUHAN],
                'children' => [
                    [
                        'label' => 'Controls Management',
                        'url' => '/admin/kepatuhan/compliance',
                        'roles' => [self::ROLE_ADMIN_KEPATUHAN],
                    ],
                ],
            ],
        ];
    }
}
