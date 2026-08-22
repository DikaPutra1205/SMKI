<?php

namespace App\Routing;

use App\Models\User;

class PageDispatcher
{
    /**
     * Page → [permissions, role_id (by name key) → Inertia component].
     *
     * Keys are flat page slugs (no prefix). '/' = root.
     */
    public const MAP = [
        '/' => [
            'permissions' => [],
            'destinations' => [
                'superadmin' => 'superadmin/dashboard',
                'admin_kepatuhan' => 'kepatuhan/dashboard',
                'koordinator_smki' => 'kepatuhan/dashboard',
                'auditor' => 'auditor/dashboard',
                'pic' => 'pic/assessments',
            ],
            'default' => 'kepatuhan/dashboard',
        ],
        'dashboard' => [
            'permissions' => ['dashboard.read'],
            'destinations' => [
                'superadmin' => 'superadmin/dashboard',
                'admin_kepatuhan' => 'kepatuhan/dashboard',
                'koordinator_smki' => 'kepatuhan/dashboard',
                'auditor' => 'auditor/dashboard',
                'pic' => 'pic/assessments',
            ],
            'default' => 'kepatuhan/dashboard',
        ],
        'frameworks' => [
            'permissions' => ['framework.view'],
            'destinations' => [
                'superadmin' => 'superadmin/frameworks',
                'admin_kepatuhan' => 'superadmin/frameworks',
            ],
            'default' => 'superadmin/frameworks',
        ],
        'users' => [
            'permissions' => ['user.managementview'],
            'destinations' => [
                'superadmin' => 'superadmin/users',
            ],
            'default' => 'superadmin/users',
        ],
        'roles' => [
            'permissions' => ['role.managementview'],
            'destinations' => [
                'superadmin' => 'superadmin/roles',
            ],
            'default' => 'superadmin/roles',
        ],
        'assessments' => [
            'permissions' => ['checklist-session.view'],
            'destinations' => [
                'pic' => 'pic/assessments',
                'superadmin' => 'pic/assessments',
                'admin_kepatuhan' => 'pic/assessments',
                'auditor' => 'pic/assessments',
                'koordinator_smki' => 'pic/assessments',
            ],
            'default' => 'pic/assessments',
        ],
        'compliance' => [
            'permissions' => ['control.view'],
            'destinations' => [
                'admin_kepatuhan' => 'kepatuhan/compliance',
                'superadmin' => 'kepatuhan/compliance',
            ],
            'default' => 'kepatuhan/compliance',
        ],
        'findings' => [
            'permissions' => ['finding.view'],
            'destinations' => [
                'admin_kepatuhan' => 'kepatuhan/findings',
                'superadmin' => 'kepatuhan/findings',
            ],
            'default' => 'kepatuhan/findings',
        ],
        'risks' => [
            'permissions' => ['risk.view'],
            'destinations' => [
                'admin_kepatuhan' => 'kepatuhan/risks',
                'superadmin' => 'kepatuhan/risks',
            ],
            'default' => 'kepatuhan/risks',
        ],
        'audit-logs' => [
            'permissions' => ['audit-log.view'],
            'destinations' => [
                'admin_kepatuhan' => 'kepatuhan/audit-logs',
                'superadmin' => 'kepatuhan/audit-logs',
            ],
            'default' => 'kepatuhan/audit-logs',
        ],
        'reports/export' => [
            'permissions' => ['report.export'],
            'destinations' => [
                'admin_kepatuhan' => 'kepatuhan/reports',
                'superadmin' => 'kepatuhan/reports',
            ],
            'default' => 'kepatuhan/reports',
        ],
    ];

    public function requiredPermissions(string $page): array
    {
        $key = $this->normalize($page);

        return self::MAP[$key]['permissions'] ?? [];
    }

    public function isUnknown(string $page): bool
    {
        return ! isset(self::MAP[$this->normalize($page)]);
    }

    public function resolve(User $user, string $page): PageResolution
    {
        $key = $this->normalize($page);

        if (! isset(self::MAP[$key])) {
            return new PageResolution(allowed: false, requiredPermissions: [], redirectTo: '/');
        }

        $entry = self::MAP[$key];
        $required = $entry['permissions'];

        foreach ($required as $perm) {
            if (! $user->hasPermissionTo($perm)) {
                return new PageResolution(allowed: false, requiredPermissions: $required, redirectTo: '/');
            }
        }

        // Resolve destination by role_id → role name, not legacy string accessor.
        $roleName = $user->role()->value('name');
        $destinations = $entry['destinations'];
        $component = $destinations[$roleName] ?? $entry['default'] ?? null;

        return new PageResolution(allowed: true, component: $component, requiredPermissions: $required);
    }

    public function resolveRoot(User $user): PageResolution
    {
        return $this->resolve($user, '/');
    }

    private function normalize(string $page): string
    {
        $page = trim($page);
        $page = ltrim($page, '/');
        $page = rtrim($page, '/');

        return $page === '' ? '/' : $page;
    }
}
