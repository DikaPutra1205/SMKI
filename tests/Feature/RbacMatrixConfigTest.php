<?php

namespace Tests\Feature;

use Tests\TestCase;

class RbacMatrixConfigTest extends TestCase
{
    public function test_config_defines_modules_and_permissions_matching_the_doc(): void
    {
        $config = config('permissions');

        $this->assertSame([
            'dashboard', 'checklist', 'checklist-session', 'control', 'framework',
            'evidence', 'finding', 'risk', 'work-unit', 'audit-log', 'report', 'user',
            'role',
        ], array_keys($config['permissions']));

        $this->assertSame(63, count($config['permissions'], COUNT_RECURSIVE) - count($config['permissions']));
        $keys = collect($config['permissions'])->flatten()->all();
        $this->assertCount(63, $keys);
        $this->assertCount(63, array_unique($keys));

        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z-]+\.[a-z-]+$/', $key);
        }
    }

    public function test_config_roles_are_defined_and_grants_reference_existing_keys(): void
    {
        $config = config('permissions');
        $defined = collect($config['permissions'])->flatten()->all();

        $this->assertArrayHasKey('roles', $config);
        $this->assertSame(
            ['superadmin', 'admin_kepatuhan', 'koordinator_smki', 'auditor', 'pic'],
            array_keys($config['roles'])
        );

        foreach ($config['roles'] as $role => $grants) {
            foreach ($grants as $grant) {
                $this->assertContains($grant, $defined, "Role {$role} references unknown permission {$grant}");
            }
            $this->assertSame(count($grants), count(array_unique($grants)), "Role {$role} has duplicates");
        }
    }

    public function test_expected_grant_counts_per_role(): void
    {
        $config = config('permissions');

        $this->assertCount(63, $config['roles']['superadmin']);
        $this->assertCount(50, $config['roles']['admin_kepatuhan']);
        $this->assertCount(21, $config['roles']['koordinator_smki']);
        $this->assertCount(21, $config['roles']['auditor']);
        $this->assertCount(34, $config['roles']['pic']);

        $this->assertSame($config['roles']['auditor'], $config['roles']['koordinator_smki']);
        $this->assertContains('checklist.bulk-verify', $config['roles']['admin_kepatuhan']);
        $this->assertNotContains('checklist.bulk-verify', $config['roles']['pic']);
        $this->assertNotContains('dashboard.recent-activities', $config['roles']['pic']);
        $this->assertContains('user.profileview', $config['roles']['pic']);
    }
}
