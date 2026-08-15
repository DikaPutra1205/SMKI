<?php

namespace Database\Seeders;

use App\Models\Framework;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            FrameworkSeeder::class,
            ControlSeeder::class,
            UserSeeder::class,
            WorkUnitSeeder::class,
        ]);
    }
}
