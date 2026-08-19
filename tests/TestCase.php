<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // afterRefreshingDatabase seeds once but the per-test transaction
        // rollBack wipes role_permission rows, so reseed inside each test's
        // transaction to keep RBAC grants available for every test.
        $this->seed(RolesAndPermissionsSeeder::class);
    }
}
