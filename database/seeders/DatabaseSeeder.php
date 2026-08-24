<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            FrameworkSeeder::class,
            ControlSeeder::class,
            UserSeeder::class,
            WorkUnitSeeder::class,
            FindingSeeder::class,
            RiskSeeder::class,
        ]);
    }
}
